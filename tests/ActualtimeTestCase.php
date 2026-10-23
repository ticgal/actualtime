<?php

/**
 * -------------------------------------------------------------------------
 * ActualTime plugin for GLPI
 * Copyright (C) 2018-2026 by the TICGAL Team.
 * https://www.tic.gal/
 * -------------------------------------------------------------------------
 * LICENSE
 * This file is part of the ActualTime plugin.
 * ActualTime plugin is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * ActualTime plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with ActualTime. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @package   ActualTime
 * @author    the TICGAL team
 * @copyright Copyright (c) 2018-2026 TICGAL team
 * @license   AGPL License 3.0 or (at your option) any later version
 *            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @link      https://www.tic.gal/
 * @since     2018
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Actualtime\Tests;

use Auth;
use Entity;
use GlpiPlugin\Actualtime\Config;
use GlpiPlugin\Actualtime\Running;
use GlpiPlugin\Actualtime\Sourcetimer;
use GlpiPlugin\Actualtime\Task;
use Planning;
use PHPUnit\Framework\TestCase;
use Profile;
use Profile_User;
use ProfileRight;
use ReflectionProperty;
use Session;
use Ticket;
use TicketTask;
use User;

/**
 * Base class: every test runs inside a DB transaction rolled back afterwards, and starts and ends
 * logged in as the super-admin 'glpi'. Use asUser() to run code as a restricted user: the
 * suite is a single process, so a leaked restricted session would make other tests pass or fail
 * for the wrong reason.
 */
abstract class ActualtimeTestCase extends TestCase
{
    protected function setUp(): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        parent::setUp();
        $DB->beginTransaction();
        $this->loginAs('glpi');
    }

    protected function tearDown(): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $this->loginAs('glpi');
        $DB->rollBack();
        // settings changed by a test were rolled back: drop the cached copy too
        (new ReflectionProperty(Config::class, 'instance'))->setValue(null, null);
        parent::tearDown();
    }

    /**
     * Open a real GLPI session (profiles, active entities, rights) for a user, without password.
     */
    protected function loginAs(string $login): void
    {
        $auth = new Auth();
        $auth->user = new User();
        if (!$auth->user->getFromDBbyName($login)) {
            $this->fail("User '$login' not found");
        }
        $auth->auth_succeded = true;
        $auth->setAuthType((int) $auth->user->fields['authtype']);
        Session::init($auth);
    }

    /**
     * Run $fn as $login, then always go back to the super-admin.
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    protected function asUser(string $login, callable $fn): mixed
    {
        $this->loginAs($login);
        try {
            return $fn();
        } finally {
            $this->loginAs('glpi');
        }
    }

    protected function createEntity(string $name = ''): int
    {
        $entity = new Entity();
        $id = $entity->add([
            'name'        => $name ?: 'AT test entity ' . uniqid(),
            'entities_id' => 0,
        ]);
        $this->assertGreaterThan(0, (int) $id, 'Entity not created');

        return (int) $id;
    }

    /**
     * Profile id by name (default profiles of a fresh install: Super-Admin, Technician, Self-Service...).
     */
    protected function getProfileId(string $name): int
    {
        $profile = new Profile();
        $this->assertTrue($profile->getFromDBByCrit(['name' => $name]), "Profile '$name' not found");

        return (int) $profile->getID();
    }

    /**
     * Set exact rights on a profile (rolled back with the test transaction).
     *
     * @param array<string, int> $rights
     */
    protected function setProfileRights(int $profiles_id, array $rights): void
    {
        ProfileRight::updateProfileRights($profiles_id, $rights);
    }

    /**
     * Create a user with a single profile in a single entity.
     */
    protected function createUser(string $login, string $profile, int $entities_id, bool $recursive = false, array $fields = []): int
    {
        // _profiles_id replaces the default profile (Self-Service) that User::add() would grant
        // otherwise: with both, Session::init() opens the session on Self-Service
        $profiles_id = $this->getProfileId($profile);
        $user = new User();
        $id = $user->add([
            'name'          => $login,
            'is_active'     => 1,
            '_profiles_id'  => $profiles_id,
            '_entities_id'  => $entities_id,
            '_is_recursive' => (int) $recursive,
        ] + $fields);
        $this->assertGreaterThan(0, (int) $id, "User $login not created");
        $this->assertSame(
            [$profiles_id],
            array_values(array_unique(array_map('intval', array_column(
                iterator_to_array((new Profile_User())->find(['users_id' => $id]), false),
                'profiles_id',
            )))),
            "$login must have only the $profile profile",
        );

        return (int) $id;
    }

    /**
     * Grant the plugin rights to a profile, as an administrator would do from the Profile tab.
     */
    protected function grantPluginRights(string $profile): void
    {
        $this->setProfileRights($this->getProfileId($profile), [
            Running::$rightname     => READ,
            Sourcetimer::$rightname => Sourcetimer::TICKET | Sourcetimer::CHANGE | Sourcetimer::PROBLEM | Sourcetimer::PROJECT,
        ]);
    }

    /**
     * Ticket with one "to do" task assigned to $users_id_tech.
     *
     * @return array{0: int, 1: int} [tickets_id, tickettasks_id]
     */
    protected function createTicketWithTask(int $users_id_tech, int $entities_id = 0, string $name = ''): array
    {
        $ticket = new Ticket();
        $tickets_id = $ticket->add([
            'name'        => $name ?: 'AT test ticket ' . uniqid(),
            'content'     => 'content',
            'entities_id' => $entities_id,
            // a technician only sees the tickets they take part in (default Technician profile)
            '_actors'     => ['assign' => [['itemtype' => User::class, 'items_id' => $users_id_tech]]],
        ]);
        $this->assertGreaterThan(0, (int) $tickets_id, 'Ticket not created');

        $task = new TicketTask();
        $tasks_id = $task->add([
            'tickets_id'    => $tickets_id,
            'content'       => 'task',
            'state'         => Planning::TODO,
            'users_id_tech' => $users_id_tech,
            'actiontime'    => 0,
        ]);
        $this->assertGreaterThan(0, (int) $tasks_id, 'Task not created');

        return [(int) $tickets_id, (int) $tasks_id];
    }

    /**
     * Change plugin settings for this test (rolled back) and drop the per-request cached copy.
     *
     * @param array<string, mixed> $fields
     */
    protected function setConfig(array $fields): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $DB->update(Config::getTable(), $fields, ['id' => 1]);
        (new ReflectionProperty(Config::class, 'instance'))->setValue(null, null);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getTimers(string $itemtype, int $items_id): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        return iterator_to_array($DB->request([
            'FROM'  => Task::getTable(),
            'WHERE' => ['itemtype' => $itemtype, 'items_id' => $items_id],
            'ORDER' => 'id',
        ]), false);
    }

    protected function countOpenTimers(string $itemtype, int $items_id): int
    {
        return count(array_filter(
            $this->getTimers($itemtype, $items_id),
            static fn(array $row): bool => $row['actual_end'] === null,
        ));
    }

    /**
     * Close a running segment in the past, so that it has a non-zero duration without sleeping.
     */
    protected function backdateOpenTimer(string $itemtype, int $items_id, int $seconds): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $DB->update(
            Task::getTable(),
            ['actual_begin' => date('Y-m-d H:i:s', time() - $seconds)],
            ['itemtype' => $itemtype, 'items_id' => $items_id, 'actual_end' => null],
        );
    }
}
