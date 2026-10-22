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

use CommonITILTask;
use GlpiPlugin\Actualtime\Profile as ActualtimeProfile;
use GlpiPlugin\Actualtime\Provider;
use GlpiPlugin\Actualtime\Running;
use GlpiPlugin\Actualtime\Sourcetimer;
use GlpiPlugin\Actualtime\Task;
use GlpiPlugin\Actualtime\Tests\ActualtimeTestCase;
use Planning;
use Profile;
use ProfileRight;
use ReflectionMethod;
use Ticket;
use TicketTask;

/**
 * Security audit 2026-10-22, SEC-02 to SEC-08. Every denial has a positive control: the user who
 * should still see or do the thing still does, so a fix that just hides everything fails here.
 *
 * Two Technicians of the same entity. The stock Technician has task SEEPRIVATE in GLPI 12, so setUp
 * removes it (keeping UPDATEALL): a private task of one is then invisible to the other while still
 * being updatable by them.
 */
class PrivacyHardeningTest extends ActualtimeTestCase
{
    private const OWNER_NAME = 'Zatprivowner';

    private string $owner;
    private int $owner_id;
    private string $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grantPluginRights('Technician');
        $technician = $this->getProfileId('Technician');
        $task_rights = ProfileRight::getProfileRights($technician, [TicketTask::$rightname])[TicketTask::$rightname];
        $this->setProfileRights($technician, [TicketTask::$rightname => $task_rights & ~CommonITILTask::SEEPRIVATE]);
        $this->owner = 'at_priv_owner_' . uniqid();
        $this->owner_id = $this->createUser($this->owner, 'Technician', 0, true, ['realname' => self::OWNER_NAME]);
        $this->viewer = 'at_priv_viewer_' . uniqid();
        $this->createUser($this->viewer, 'Technician', 0, true);
    }

    private function makePrivate(int $task_id): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $DB->update(TicketTask::getTable(), ['is_private' => 1], ['id' => $task_id]);
    }

    private function capture(callable $fn): string
    {
        ob_start();
        try {
            $fn();
        } finally {
            $output = (string) ob_get_clean();
        }
        return $output;
    }

    private function startAsOwner(int $task_id): void
    {
        $this->asUser($this->owner, function () use ($task_id) {
            $result = Task::startTimer($task_id, 'TicketTask', Task::WEB);
            $this->assertSame('info', $result['type'], json_encode($result));
        });
    }

    /**
     * A finished timer of the owner on $task_id, one hour long, for the statistics and the cards
     */
    private function finishedSegment(int $task_id): void
    {
        $this->startAsOwner($task_id);
        $this->backdateOpenTimer('TicketTask', $task_id, 3600);
        $this->asUser($this->owner, function () use ($task_id) {
            $this->assertSame('info', Task::stopTimer($task_id, 'TicketTask', Task::WEB)['type']);
        });
        $task = new TicketTask();
        $this->assertTrue($task->update(['id' => $task_id, 'state' => Planning::DONE]));
    }

    public function testRunningListHidesPrivateTasksOfOthers(): void
    {
        [$ticket_id, $task_id] = $this->createTicketWithTask($this->owner_id);
        $this->makePrivate($task_id);
        $this->startAsOwner($task_id);
        $marker = "ticket.form.php?id={$ticket_id}";

        // positive control: the owner still sees their own running timer
        $this->assertStringContainsString($marker, $this->asUser($this->owner, static fn() => Running::listRunning()));
        $this->assertStringNotContainsString($marker, $this->asUser($this->viewer, static fn() => Running::listRunning()));
    }

    public function testRunningListStillShowsPublicTasks(): void
    {
        [$ticket_id, $task_id] = $this->createTicketWithTask($this->owner_id);
        $this->startAsOwner($task_id);

        $html = $this->asUser($this->viewer, static fn() => Running::listRunning());
        $this->assertStringContainsString("ticket.form.php?id={$ticket_id}", $html);
    }

    public function testEndWithoutTimerReturnsSegmentsOnlyToReaders(): void
    {
        // the viewer may close the private task (UPDATEALL) but not read its timers
        [, $hidden_task] = $this->createTicketWithTask($this->owner_id);
        $this->makePrivate($hidden_task);
        $result = $this->asUser($this->viewer, static fn() => Task::stopTimer($hidden_task, 'TicketTask', Task::WEB));
        $this->assertSame('info', $result['type'], json_encode($result));
        $this->assertArrayNotHasKey('segment', $result);
        $this->assertArrayNotHasKey('task_time', $result);
        $task = new TicketTask();
        $task->getFromDB($hidden_task);
        $this->assertSame(Planning::DONE, (int) $task->fields['state'], 'the task is still closed');

        // positive control: the assigned technician gets the timer data back as before
        [, $own_task] = $this->createTicketWithTask($this->owner_id);
        $this->makePrivate($own_task);
        $result = $this->asUser($this->owner, static fn() => Task::stopTimer($own_task, 'TicketTask', Task::WEB));
        $this->assertSame('info', $result['type']);
        $this->assertArrayHasKey('segment', $result);
        $this->assertArrayHasKey('time', $result);
        $this->assertArrayHasKey('task_time', $result);
    }

    public function testStatisticsHidePrivateTasksOfOthers(): void
    {
        [$ticket_id, $task_id] = $this->createTicketWithTask($this->owner_id);
        $this->makePrivate($task_id);
        $this->finishedSegment($task_id);
        $ticket = new Ticket();
        $ticket->getFromDB($ticket_id);

        $owner_html = $this->asUser($this->owner, fn() => $this->capture(static fn() => Task::showStats($ticket)));
        $this->assertStringContainsString(self::OWNER_NAME, $owner_html, 'positive control');
        $viewer_html = $this->asUser($this->viewer, fn() => $this->capture(static fn() => Task::showStats($ticket)));
        $this->assertStringContainsString('ActualTime', $viewer_html, 'the statistics are still shown');
        $this->assertStringNotContainsString(self::OWNER_NAME, $viewer_html);
    }

    public function testStatisticsStillListPublicTasks(): void
    {
        [$ticket_id, $task_id] = $this->createTicketWithTask($this->owner_id);
        $this->finishedSegment($task_id);
        $ticket = new Ticket();
        $ticket->getFromDB($ticket_id);

        $html = $this->asUser($this->viewer, fn() => $this->capture(static fn() => Task::showStats($ticket)));
        $this->assertStringContainsString(self::OWNER_NAME, $html);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function cardProvider(): array
    {
        return [
            'more usage' => ['moreActualtimeUsageByDay'],
            'less usage' => ['lessActualtimeUsageByDay'],
            'more tasks' => ['moreActualtimeTasksByDay'],
            'less tasks' => ['lessActualtimeTasksByDay'],
            'more %'     => ['morePercentageActualtimeTasksByDay'],
            'less %'     => ['lessPercentageActualtimeTasksByDay'],
        ];
    }

    private function cardSeries(string $card, string $login): int
    {
        $params = ['label' => 'test', 'apply_filters' => ['dates' => [date('Y-m-d', time() - DAY_TIMESTAMP), date('Y-m-d', time() + DAY_TIMESTAMP)]]];
        $result = $this->asUser($login, static fn() => Provider::$card($params));

        return count($result['data']['series'] ?? []);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cardProvider')]
    public function testCardsHidePrivateTasksOfOthers(string $card): void
    {
        [, $task_id] = $this->createTicketWithTask($this->owner_id);
        $this->makePrivate($task_id);
        $this->finishedSegment($task_id);

        $this->assertGreaterThan(0, $this->cardSeries($card, $this->owner), 'positive control: own private task');
        $this->assertSame(0, $this->cardSeries($card, $this->viewer));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cardProvider')]
    public function testCardsSkipTicketsInTheTrash(string $card): void
    {
        [$ticket_id, $task_id] = $this->createTicketWithTask($this->owner_id);
        $this->finishedSegment($task_id);
        $this->assertGreaterThan(0, $this->cardSeries($card, $this->viewer), 'positive control: public task');

        $ticket = new Ticket();
        $this->assertTrue($ticket->delete(['id' => $ticket_id]));
        $this->assertSame(0, $this->cardSeries($card, $this->viewer));
    }

    /**
     * Ticket READMY: the profile restriction (a subquery) keeps only the reader's tickets, and a
     * reader who is several actors of the same ticket does not multiply the summed times
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('cardProvider')]
    public function testCardsFollowTheTicketProfileRights(string $card): void
    {
        [$ticket_id, $task_id] = $this->createTicketWithTask($this->owner_id);
        $this->finishedSegment($task_id);
        $params = ['label' => 'test', 'apply_filters' => ['dates' => [date('Y-m-d', time() - DAY_TIMESTAMP), date('Y-m-d', time() + DAY_TIMESTAMP)]]];
        // what a Ticket READALL reader (super-admin) gets for this single ticket
        $expected = Provider::$card($params)['data'];
        $this->assertNotEmpty($expected['series'], 'positive control');

        $this->setProfileRights($this->getProfileId('Technician'), [Ticket::$rightname => Ticket::READMY]);
        $this->assertSame(0, $this->cardSeries($card, $this->viewer), 'not an actor of the ticket');

        $viewer = new \User();
        $viewer->getFromDBbyName($this->viewer);
        foreach ([\CommonITILActor::REQUESTER, \CommonITILActor::OBSERVER] as $type) {
            $this->assertGreaterThan(0, (int) (new \Ticket_User())->add([
                'tickets_id' => $ticket_id,
                'users_id'   => $viewer->getID(),
                'type'       => $type,
            ]));
        }
        $this->assertSame($expected, $this->asUser($this->viewer, static fn() => Provider::$card($params))['data']);
    }

    public function testCardsAreEmptyWithoutTicketRight(): void
    {
        [, $task_id] = $this->createTicketWithTask($this->owner_id);
        $this->finishedSegment($task_id);
        $profiles_id = $this->getProfileId('Technician');
        $this->assertGreaterThan(0, $this->cardSeries('moreActualtimeUsageByDay', $this->viewer), 'positive control');

        $this->setProfileRights($profiles_id, [Ticket::$rightname => 0]);
        $this->assertSame(0, $this->cardSeries('moreActualtimeUsageByDay', $this->viewer));
    }

    public function testProfileTabOnlyOnCentralProfiles(): void
    {
        $tab = new ActualtimeProfile();
        $central = new Profile();
        $central->getFromDB($this->getProfileId('Technician'));
        $helpdesk = new Profile();
        $helpdesk->getFromDB($this->getProfileId('Self-Service'));

        $this->assertNotSame('', $tab->getTabNameForItem($central), 'positive control');
        $this->assertSame('', $tab->getTabNameForItem($helpdesk));
        $this->assertFalse(ActualtimeProfile::displayTabContentForItem($helpdesk));

        // setUp wrote the plugin rights inside the transaction without refreshing core's cached list
        // of rights, which would make the matrix create them again: refresh it, as GLPI does
        /** @var \Psr\SimpleCache\CacheInterface $GLPI_CACHE */
        global $GLPI_CACHE;
        $GLPI_CACHE->delete('all_possible_rights');
        $html = $this->capture(static fn() => ActualtimeProfile::displayTabContentForItem($central));
        $this->assertStringContainsString('_plugin_actualtime_running', $html, 'positive control: central tab rendered');
    }

    public function testTimerMethodsDefaultToTheCheckedOrigin(): void
    {
        foreach (['startTimer', 'pauseTimer', 'stopTimer'] as $method) {
            $origin = (new ReflectionMethod(Task::class, $method))->getParameters()[2];
            $this->assertSame(Task::WEB, $origin->getDefaultValue(), $method);
        }
    }

    public function testModifyTimersModalAuthorizesByItself(): void
    {
        [, $task_id] = $this->createTicketWithTask($this->owner_id);
        $this->finishedSegment($task_id);
        $other_entity_tech = 'at_priv_other_' . uniqid();
        $this->createUser($other_entity_tech, 'Technician', $this->createEntity(), false);

        $render = fn() => $this->capture(static fn() => (new Sourcetimer())->modalForm('TicketTask', $task_id));
        $this->assertStringContainsString(self::OWNER_NAME, $this->asUser($this->owner, $render), 'positive control');
        $this->assertSame('', $this->asUser($other_entity_tech, $render));
    }
}
