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

namespace GlpiPlugin\Actualtime\Tests\Integration;

use GlpiPlugin\Actualtime\Provider;
use GlpiPlugin\Actualtime\Running;
use GlpiPlugin\Actualtime\Sourcetimer;
use GlpiPlugin\Actualtime\Task;
use GlpiPlugin\Actualtime\Tests\ActualtimeTestCase;
use Planning;
use Session;
use TicketTask;

/**
 * Rights and entity isolation. The suite logs in as the super-admin by default, so every check
 * here switches to a restricted user (glpi-plugin-testing, rights-and-entity-tests).
 */
class AccessControlTest extends ActualtimeTestCase
{
    private int $root_task;
    private int $root_ticket;
    private string $other_entity_tech;
    private string $root_tech;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grantPluginRights('Technician');
        $entity_b = $this->createEntity();
        $this->other_entity_tech = 'at_tech_b_' . uniqid();
        $this->createUser($this->other_entity_tech, 'Technician', $entity_b, false);
        $this->root_tech = 'at_tech_root_' . uniqid();
        $root_tech_id = $this->createUser($this->root_tech, 'Technician', 0, true);

        // ticket of the root entity, task assigned to a root technician
        [$this->root_ticket, $this->root_task] = $this->createTicketWithTask($root_tech_id);
    }

    private function startAs(string $login, int $task_id): void
    {
        $this->asUser($login, function () use ($task_id) {
            $result = Task::startTimer($task_id, 'TicketTask', Task::WEB);
            $this->assertSame('info', $result['type'], json_encode($result));
        });
    }

    public function testOtherEntityTechnicianCannotDriveTheTimer(): void
    {
        $this->startAs($this->root_tech, $this->root_task);
        $before = $this->getTimers('TicketTask', $this->root_task);

        $this->asUser($this->other_entity_tech, function () {
            foreach (['startTimer', 'pauseTimer', 'stopTimer'] as $method) {
                $result = Task::$method($this->root_task, 'TicketTask', Task::WEB);
                $this->assertSame('warning', $result['type'], "$method allowed for another entity");
            }
            $this->assertNull(Task::getAuthorizedTask('TicketTask', $this->root_task, READ));
            $this->assertFalse(Sourcetimer::canModify('TicketTask', $this->root_task));
        });

        $this->assertSame($before, $this->getTimers('TicketTask', $this->root_task), 'timers changed');
    }

    public function testSameEntityTechnicianIsAllowed(): void
    {
        // positive control: the denials above are not caused by a broken fixture
        $this->startAs($this->root_tech, $this->root_task);
        $this->asUser($this->root_tech, function () {
            $this->assertNotNull(Task::getAuthorizedTask('TicketTask', $this->root_task, UPDATE));
            $this->assertTrue(Sourcetimer::checkItemtypeRight('TicketTask'));
            $this->assertTrue(Sourcetimer::canModify('TicketTask', $this->root_task));
            $this->assertSame('info', Task::pauseTimer($this->root_task, 'TicketTask', Task::WEB)['type']);
        });
    }

    public function testAutomaticOriginStillRequiresAnAllowedItemtype(): void
    {
        $this->asUser($this->other_entity_tech, function () {
            // AUTO is what GLPI hooks use; it must not open arbitrary classes
            $result = Task::startTimer(1, 'User', Task::AUTO);
            $this->assertSame('warning', $result['type']);
        });
        $this->assertSame([], $this->getTimers('User', 1));
    }

    public function testSegmentsCannotBeModifiedOnAClosedTicket(): void
    {
        $this->startAs($this->root_tech, $this->root_task);
        $task = new TicketTask();
        $task->getFromDB($this->root_task);
        $ticket = new \Ticket();
        $this->assertTrue($ticket->update(['id' => $this->root_ticket, 'status' => \CommonITILObject::CLOSED]));

        $this->asUser($this->root_tech, function () {
            $this->assertFalse(Sourcetimer::canModify('TicketTask', $this->root_task));
        });
    }

    public function testSelfServiceNeverGetsPluginRights(): void
    {
        $login = 'at_selfservice_' . uniqid();
        $this->createUser($login, 'Self-Service', 0, true);
        // even if an administrator grants them on the helpdesk profile, GLPI drops them from the
        // session (Profile::cleanProfile): the plugin has no feature for the simplified interface
        $this->grantPluginRights('Self-Service');

        $this->asUser($login, function () {
            $this->assertFalse(Session::haveRight(Running::$rightname, READ));
            foreach (['TicketTask', 'ChangeTask', 'ProblemTask', 'ProjectTask'] as $itemtype) {
                $this->assertFalse(Sourcetimer::checkItemtypeRight($itemtype), $itemtype);
            }
            $this->assertNull(Task::getAuthorizedTask('TicketTask', $this->root_task, UPDATE));
            $this->assertSame('warning', Task::startTimer($this->root_task, 'TicketTask', Task::WEB)['type']);
        });
    }

    public function testRunningTimersListHidesOtherEntities(): void
    {
        $this->startAs($this->root_tech, $this->root_task);
        $marker = "ticket.form.php?id={$this->root_ticket}";

        $this->assertStringContainsString($marker, Running::listRunning(), 'positive control');
        $html = $this->asUser($this->other_entity_tech, static fn() => Running::listRunning());
        $this->assertStringNotContainsString($marker, $html);
    }

    public function testPlanningHidesTimersOfTasksTheUserCannotSee(): void
    {
        $user = new \User();
        $user->getFromDBbyName($this->root_tech);
        $this->startAs($this->root_tech, $this->root_task);
        $this->backdateOpenTimer('TicketTask', $this->root_task, 60);
        $this->asUser($this->root_tech, function () {
            Task::stopTimer($this->root_task, 'TicketTask', Task::WEB);
        });

        $options = [
            'who'      => $user->getID(),
            'whogroup' => 0,
            'begin'    => date('Y-m-d H:i:s', time() - DAY_TIMESTAMP),
            'end'      => date('Y-m-d H:i:s', time() + DAY_TIMESTAMP),
        ];
        $marker = "id={$this->root_ticket}";
        $mine = static fn(array $events): array => array_filter(
            $events,
            static fn(array $event): bool => str_contains((string) ($event['url'] ?? ''), $marker),
        );

        // positive control: a user of the ticket's entity sees the timer
        $this->assertNotEmpty($mine($this->asUser($this->root_tech, static fn() => Task::populatePlanning($options))));
        // core lets anyone add any user to their planning filters (Planning::sendAddUserForm()):
        // the other entity must still not see that user's timers on tickets it cannot view
        $this->assertSame([], $mine($this->asUser($this->other_entity_tech, static fn() => Task::populatePlanning($options))));
    }

    /**
     * Timer rows are reachable through core's generic routes (/plugins/actualtime/front/task.form.php)
     * and the planning AJAX actions, which only call can(). Security audit 2026-10-22, SEC-01.
     */
    public function testTimerRowsCannotBeWrittenFromAnotherEntity(): void
    {
        $this->startAs($this->root_tech, $this->root_task);
        $row = $this->getTimers('TicketTask', $this->root_task)[0];

        $allowed = $this->asUser($this->other_entity_tech, function () use ($row) {
            $timer = new Task();
            $input = ['itemtype' => 'TicketTask', 'items_id' => $this->root_task];
            return array_filter([
                'create' => $timer->can(-1, CREATE, $input),
                'read'   => $timer->can($row['id'], READ),
                'update' => $timer->can($row['id'], UPDATE),
                'purge'  => $timer->can($row['id'], PURGE),
            ]);
        });

        // SEC-01 fix: Task denies every generic write and gates reads on the linked task's entity
        $this->assertSame([], $allowed, 'another entity can ' . implode(', ', array_keys($allowed)) . ' timer rows');
    }

    /**
     * SEC-01, read side: GLPI's search engine (generic list /plugins/actualtime/front/task.php,
     * ajax/search.php) only checks the static canView(), which the planning needs, so a search of
     * timer rows returns nothing (plugin_actualtime_addDefaultWhere())
     */
    public function testTimerRowsAreNotListedBySearch(): void
    {
        $this->startAs($this->root_tech, $this->root_task);
        $this->assertNotEmpty($this->getTimers('TicketTask', $this->root_task));

        $count = static fn(): int => (int) \Search::getDatas(Task::class, ['criteria' => []])['data']['totalcount'];
        $this->assertSame(0, $this->asUser($this->other_entity_tech, $count), 'another entity');
        $this->assertSame(0, $count(), 'super-admin: no plugin feature lists timer rows');

        // positive control: ticket searches with the ActualTime columns (joining the timer table) still work
        $tickets = \Search::getDatas(\Ticket::class, [
            'criteria' => [['field' => 2, 'searchtype' => 'equals', 'value' => $this->root_ticket]],
        ], [7000]);
        $this->assertSame(1, (int) $tickets['data']['totalcount']);
    }

    public function testDashboardCardsAreScopedToEntities(): void
    {
        $this->startAs($this->root_tech, $this->root_task);
        $this->backdateOpenTimer('TicketTask', $this->root_task, 3600);
        $this->asUser($this->root_tech, function () {
            Task::stopTimer($this->root_task, 'TicketTask', Task::WEB);
        });
        $task = new TicketTask();
        $this->assertTrue($task->update(['id' => $this->root_task, 'state' => Planning::DONE]));

        $params = ['label' => 'test', 'apply_filters' => ['dates' => [date('Y-m-d', time() - DAY_TIMESTAMP), date('Y-m-d')]]];
        $series = static fn(array $card): int => count($card['data']['series'] ?? []);

        $this->assertGreaterThan(0, $series(Provider::lessActualtimeUsageByDay($params)), 'positive control');
        $this->assertSame(0, $series($this->asUser($this->other_entity_tech, static fn() => Provider::lessActualtimeUsageByDay($params))));
        $this->assertSame(0, $series($this->asUser($this->other_entity_tech, static fn() => Provider::moreActualtimeUsageByDay($params))));
    }
}
