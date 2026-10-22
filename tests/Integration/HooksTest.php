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

use GlpiPlugin\Actualtime\Task;
use GlpiPlugin\Actualtime\Tests\ActualtimeTestCase;
use ITILSolution;
use Planning;
use Session;
use Ticket;
use TicketTask;

/**
 * Timers react to GLPI events through the hooks registered in setup.php.
 */
class HooksTest extends ActualtimeTestCase
{
    /**
     * @return array{0: int, 1: int}
     */
    private function runningTask(): array
    {
        [$ticket_id, $task_id] = $this->createTicketWithTask(Session::getLoginUserID());
        $this->assertSame('info', Task::startTimer($task_id, 'TicketTask', Task::WEB)['type']);
        $this->backdateOpenTimer('TicketTask', $task_id, 60);

        return [$ticket_id, $task_id];
    }

    public function testMarkingTheTaskDoneStopsTheTimer(): void
    {
        [, $task_id] = $this->runningTask();

        $task = new TicketTask();
        $this->assertTrue($task->update(['id' => $task_id, 'state' => Planning::DONE]));

        $this->assertSame(0, $this->countOpenTimers('TicketTask', $task_id));
    }

    public function testChangingTheTechnicianPausesTheTimer(): void
    {
        [, $task_id] = $this->runningTask();
        $other = $this->createUser('at_hook_tech_' . uniqid(), 'Technician', 0, true);

        $task = new TicketTask();
        $this->assertTrue($task->update(['id' => $task_id, 'users_id_tech' => $other]));

        $this->assertSame(0, $this->countOpenTimers('TicketTask', $task_id));
    }

    public function testAddingASolutionStopsTheTicketTimers(): void
    {
        [$ticket_id, $task_id] = $this->runningTask();

        $solution = new ITILSolution();
        $this->assertGreaterThan(0, (int) $solution->add([
            'itemtype' => Ticket::class,
            'items_id' => $ticket_id,
            'content'  => 'solved',
        ]));

        $this->assertSame(0, $this->countOpenTimers('TicketTask', $task_id));
    }

    public function testPurgingTheTaskDeletesItsTimers(): void
    {
        [, $task_id] = $this->runningTask();
        $this->assertSame('info', Task::stopTimer($task_id, 'TicketTask', Task::WEB)['type']);
        $this->assertNotEmpty($this->getTimers('TicketTask', $task_id));

        $task = new TicketTask();
        $this->assertTrue($task->delete(['id' => $task_id], true));

        $this->assertSame([], $this->getTimers('TicketTask', $task_id));
    }

    public function testDeletingTheTicketStopsItsTimers(): void
    {
        [$ticket_id, $task_id] = $this->runningTask();

        $ticket = new Ticket();
        $this->assertTrue($ticket->delete(['id' => $ticket_id]));

        $this->assertSame(0, $this->countOpenTimers('TicketTask', $task_id));
    }

    public function testAutostartOnTaskCreation(): void
    {
        [$ticket_id] = $this->createTicketWithTask(Session::getLoginUserID());

        $task = new TicketTask();
        $task_id = (int) $task->add([
            'tickets_id'    => $ticket_id,
            'content'       => 'autostart',
            'state'         => Planning::TODO,
            'users_id_tech' => Session::getLoginUserID(),
            'autostart'     => 1,
        ]);

        $this->assertGreaterThan(0, $task_id);
        $this->assertSame(1, $this->countOpenTimers('TicketTask', $task_id));
    }
}
