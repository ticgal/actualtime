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

namespace GlpiPlugin\Actualtime\Tests\Unit;

use GlpiPlugin\Actualtime\Task;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The itemtype allowlist is what keeps request data from choosing an arbitrary class
 * (security review 4.1.3, "unvalidated itemtype").
 */
class ItemtypeAllowlistTest extends TestCase
{
    public static function allowedProvider(): iterable
    {
        yield ['TicketTask'];
        yield ['ChangeTask'];
        yield ['ProblemTask'];
        yield ['ProjectTask'];
    }

    public static function rejectedProvider(): iterable
    {
        yield 'other core class' => ['User'];
        yield 'task parent' => ['Ticket'];
        yield 'plugin class' => [Task::class];
        yield 'wrong case' => ['tickettask'];
        yield 'namespaced alias' => ['\\TicketTask'];
        yield 'empty' => [''];
        yield 'null' => [null];
        yield 'int' => [1];
        // `itemtype[0]=LIKE&itemtype[1]=%` arrives as an array
        yield 'operator array' => [['LIKE', '%']];
    }

    #[DataProvider('allowedProvider')]
    public function testAllowedItemtypes(string $itemtype): void
    {
        $this->assertTrue(Task::isAllowedItemtype($itemtype));
    }

    #[DataProvider('rejectedProvider')]
    public function testRejectedItemtypes(mixed $itemtype): void
    {
        $this->assertFalse(Task::isAllowedItemtype($itemtype));
    }

    #[DataProvider('rejectedProvider')]
    public function testNoTaskIsLoadedForARejectedItemtype(mixed $itemtype): void
    {
        $this->assertNull(Task::getAuthorizedTask($itemtype, 1, READ));
    }

    public function testNoTaskIsLoadedForAnInvalidId(): void
    {
        $this->assertNull(Task::getAuthorizedTask('TicketTask', 0, READ));
        $this->assertNull(Task::getAuthorizedTask('TicketTask', -3, READ));
        $this->assertNull(Task::getAuthorizedTask('TicketTask', '1 OR 1=1', READ));
        $this->assertNull(Task::getAuthorizedTask('TicketTask', ['>', 0], READ));
    }
}
