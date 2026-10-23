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
use Planning;
use Project;
use ProjectTask;
use Session;
use TicketTask;

class TimerLifecycleTest extends ActualtimeTestCase
{
    public function testStartPauseRestartStop(): void
    {
        [, $task_id] = $this->createTicketWithTask(Session::getLoginUserID());

        $result = Task::startTimer($task_id, 'TicketTask', Task::WEB);
        $this->assertSame('info', $result['type'], json_encode($result));
        $this->assertSame(1, $this->countOpenTimers('TicketTask', $task_id));
        $this->assertTrue(Task::checkTimerActive($task_id, 'TicketTask'));
        $this->assertSame($task_id, Task::getTask(Session::getLoginUserID()));
        $this->assertSame('TicketTask', Task::getItemtype(Session::getLoginUserID()));

        // a second start on the running task is refused and does not open another segment
        $again = Task::startTimer($task_id, 'TicketTask', Task::WEB);
        $this->assertNotSame('info', $again['type']);
        $this->assertSame(1, $this->countOpenTimers('TicketTask', $task_id));

        $this->backdateOpenTimer('TicketTask', $task_id, 120);
        $paused = Task::pauseTimer($task_id, 'TicketTask', Task::WEB);
        $this->assertSame('info', $paused['type'], json_encode($paused));
        $this->assertSame(0, $this->countOpenTimers('TicketTask', $task_id));
        $this->assertGreaterThanOrEqual(119, Task::totalEndTime($task_id, 'TicketTask'));

        $this->assertSame('info', Task::startTimer($task_id, 'TicketTask', Task::WEB)['type']);
        $this->assertCount(2, $this->getTimers('TicketTask', $task_id));

        $this->backdateOpenTimer('TicketTask', $task_id, 60);
        $stopped = Task::stopTimer($task_id, 'TicketTask', Task::WEB);
        $this->assertSame('info', $stopped['type'], json_encode($stopped));
        $this->assertSame(0, $this->countOpenTimers('TicketTask', $task_id));
        $this->assertFalse(Task::checkTimerActive($task_id, 'TicketTask'));
        $this->assertTrue(Task::checkUserFree(Session::getLoginUserID()));
        $this->assertGreaterThanOrEqual(179, Task::totalEndTime($task_id, 'TicketTask'));
    }

    public function testStopUpdatesTaskDurationWhenConfigured(): void
    {
        /** @var \Glpi\Config\ConfigContainer $CFG_GLPI */
        global $CFG_GLPI;

        $this->setConfig(['autoupdate_duration' => 1]);
        [, $task_id] = $this->createTicketWithTask(Session::getLoginUserID());

        Task::startTimer($task_id, 'TicketTask', Task::WEB);
        $this->backdateOpenTimer('TicketTask', $task_id, 90);
        $this->assertSame('info', Task::stopTimer($task_id, 'TicketTask', Task::WEB)['type']);

        $task = new TicketTask();
        $task->getFromDB($task_id);
        $step = max(1, (int) $CFG_GLPI['time_step']) * MINUTE_TIMESTAMP;
        $this->assertSame(0, $task->fields['actiontime'] % $step, 'duration rounded to the time step');
        $this->assertGreaterThanOrEqual(90, (int) $task->fields['actiontime']);
    }

    public function testOneRunningTimerPerUser(): void
    {
        [, $first] = $this->createTicketWithTask(Session::getLoginUserID());
        [, $second] = $this->createTicketWithTask(Session::getLoginUserID());

        $this->assertSame('info', Task::startTimer($first, 'TicketTask', Task::WEB)['type']);
        $result = Task::startTimer($second, 'TicketTask', Task::WEB);

        $this->assertNotSame('info', $result['type']);
        $this->assertSame(0, $this->countOpenTimers('TicketTask', $second));
        $this->assertFalse(Task::checkUserFree(Session::getLoginUserID()));
    }

    public function testDoneTaskCannotBeStarted(): void
    {
        [, $task_id] = $this->createTicketWithTask(Session::getLoginUserID());
        $task = new TicketTask();
        $this->assertTrue($task->update(['id' => $task_id, 'state' => Planning::DONE]));

        $result = Task::startTimer($task_id, 'TicketTask', Task::WEB);

        $this->assertNotSame('info', $result['type']);
        $this->assertSame(0, $this->countOpenTimers('TicketTask', $task_id));
    }

    public function testOnlyTheAssignedTechnicianStartsTheTimer(): void
    {
        $other_tech = $this->createUser('at_other_tech_' . uniqid(), 'Technician', 0, true);
        [, $task_id] = $this->createTicketWithTask($other_tech);

        // the super-admin can update the task, but it is not assigned to them
        $result = Task::startTimer($task_id, 'TicketTask', Task::WEB);

        $this->assertNotSame('info', $result['type']);
        $this->assertSame(0, $this->countOpenTimers('TicketTask', $task_id));
    }

    public function testProjectTaskUpdatesEffectiveDuration(): void
    {
        $this->setConfig(['autoupdate_duration' => 1]);
        $project = new Project();
        $projects_id = (int) $project->add(['name' => 'AT project ' . uniqid(), 'entities_id' => 0]);
        $this->assertGreaterThan(0, $projects_id);
        $ptask = new ProjectTask();
        $ptask_id = (int) $ptask->add(['name' => 'AT project task', 'projects_id' => $projects_id, 'entities_id' => 0]);
        $this->assertGreaterThan(0, $ptask_id);

        $this->assertSame('info', Task::startTimer($ptask_id, 'ProjectTask', Task::WEB)['type']);
        $this->backdateOpenTimer('ProjectTask', $ptask_id, 120);
        $this->assertSame('info', Task::stopTimer($ptask_id, 'ProjectTask', Task::WEB)['type']);

        $ptask->getFromDB($ptask_id);
        $this->assertGreaterThanOrEqual(120, (int) $ptask->fields['effective_duration']);
    }

    public function testSegmentListsEachClosedTimer(): void
    {
        [, $task_id] = $this->createTicketWithTask(Session::getLoginUserID());
        Task::startTimer($task_id, 'TicketTask', Task::WEB);
        $this->backdateOpenTimer('TicketTask', $task_id, 65);
        Task::stopTimer($task_id, 'TicketTask', Task::WEB);

        $segment = Task::getSegment($task_id, 'TicketTask');

        $this->assertStringContainsString('1 minute', $segment);
    }
}
