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

use GlpiPlugin\Actualtime\Running;
use GlpiPlugin\Actualtime\Sourcetimer;
use GlpiPlugin\Actualtime\Task;
use GlpiPlugin\Actualtime\Tests\ActualtimeTestCase;
use Ticket;

/**
 * Names stored raw by GLPI (a user's own realname, item names) must be escaped wherever the
 * plugin renders them (security review 4.1.3: three stored XSS through a user's name).
 */
class OutputEscapingTest extends ActualtimeTestCase
{
    private const USER_PAYLOAD   = '<img src=x onerror=alert(101)>';
    private const TICKET_PAYLOAD = '<svg onload=alert(102)>';

    private int $ticket_id;
    private int $task_id;
    private string $tech;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grantPluginRights('Technician');
        $this->tech = 'at_xss_' . uniqid();
        // any user can set their own name: the attacker is just a technician
        $tech_id = $this->createUser($this->tech, 'Technician', 0, true, ['realname' => self::USER_PAYLOAD]);
        [$this->ticket_id, $this->task_id] = $this->createTicketWithTask($tech_id, 0, self::TICKET_PAYLOAD);

        // a closed segment modified by the attacker, then a running one (pause, not stop: stop marks
        // the task done and a done task cannot start again)
        $this->asUser($this->tech, function () {
            $result = Task::startTimer($this->task_id, 'TicketTask', Task::WEB);
            $this->assertSame('info', $result['type'], json_encode($result));
        });
        $this->backdateOpenTimer('TicketTask', $this->task_id, 120);
        $this->asUser($this->tech, function () use ($tech_id) {
            $this->assertSame('info', Task::pauseTimer($this->task_id, 'TicketTask', Task::WEB)['type']);
            $segment = $this->getTimers('TicketTask', $this->task_id)[0];
            $source = new Sourcetimer();
            $this->assertGreaterThan(0, (int) $source->add([
                'plugin_actualtime_tasks_id' => $segment['id'],
                'users_id'          => $tech_id,
                'source_end'        => $segment['actual_end'],
                'source_actiontime' => $segment['actual_actiontime'],
            ]));
            $timer = new Task();
            $this->assertTrue($timer->update(['id' => $segment['id'], 'is_modified' => 1]));
        });
        $this->asUser($this->tech, function () {
            $result = Task::startTimer($this->task_id, 'TicketTask', Task::WEB);
            $this->assertSame('info', $result['type'], json_encode($result));
        });
    }

    private function assertEscaped(string $html, string $payload, string $where): void
    {
        $this->assertStringNotContainsString($payload, $html, "raw payload in $where");
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

    public function testRunningTimersList(): void
    {
        $html = Running::listRunning();

        $this->assertStringContainsString('alert(101)', $html, 'technician name rendered');
        $this->assertEscaped($html, self::USER_PAYLOAD, 'running timers');
        $this->assertEscaped($html, self::TICKET_PAYLOAD, 'running timers');
    }

    public function testModifyTimersModal(): void
    {
        $html = $this->capture(fn() => (new Sourcetimer())->modalForm('TicketTask', $this->task_id));

        $this->assertStringContainsString('alert(101)', $html, 'technician name rendered');
        $this->assertEscaped($html, self::USER_PAYLOAD, 'modify timers modal');
    }

    public function testSegmentTooltip(): void
    {
        $html = Task::getSegment($this->task_id, 'TicketTask');

        $this->assertStringContainsString('alert(101)', $html, 'first modification author rendered');
        $this->assertEscaped($html, self::USER_PAYLOAD, 'segment tooltip');
    }

    public function testTicketStatistics(): void
    {
        $ticket = new Ticket();
        $ticket->getFromDB($this->ticket_id);
        $html = $this->capture(fn() => Task::showStats($ticket));

        $this->assertEscaped($html, self::USER_PAYLOAD, 'statistics');
        $this->assertEscaped($html, self::TICKET_PAYLOAD, 'statistics');
    }

    public function testPlanningEvent(): void
    {
        $user = new \User();
        $user->getFromDBbyName($this->tech);
        $events = Task::populatePlanning([
            'who'      => $user->getID(),
            'whogroup' => 0,
            'begin'    => date('Y-m-d H:i:s', time() - DAY_TIMESTAMP),
            'end'      => date('Y-m-d H:i:s', time() + DAY_TIMESTAMP),
        ]);
        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            $html = Task::displayPlanningItem($event, $user->getID());
            $this->assertStringContainsString('alert(101)', $html, 'technician name rendered');
            $this->assertEscaped($html, self::USER_PAYLOAD, 'planning event');
        }
    }
}
