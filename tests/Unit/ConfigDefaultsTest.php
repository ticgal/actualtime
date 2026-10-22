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

use GlpiPlugin\Actualtime\Config;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Settings getters run in every request (plugin_init): they must not fail when the settings
 * row is missing (fresh table, deleted row, update in progress).
 */
class ConfigDefaultsTest extends TestCase
{
    private function configWithFields(array $fields): Config
    {
        /** @var Config $config */
        $config = (new ReflectionClass(Config::class))->newInstanceWithoutConstructor();
        $config->fields = $fields;

        return $config;
    }

    public function testGettersAreFalseWithoutSettingsRow(): void
    {
        $config = $this->configWithFields([]);

        $this->assertFalse($config->showTimerPopup());
        $this->assertFalse($config->showInHelpdesk());
        $this->assertFalse($config->showTimerInBox());
        $this->assertFalse($config->autoOpenRunning());
        $this->assertFalse($config->autoUpdateDuration());
    }

    public function testGettersReadTheSettingsRow(): void
    {
        $config = $this->configWithFields([
            'showtimerpopup'      => 1,
            'displayinfofor'      => 1,
            'showtimerinbox'      => 1,
            'autoopenrunning'     => 1,
            'autoupdate_duration' => 1,
        ]);

        $this->assertTrue($config->showTimerPopup());
        $this->assertTrue($config->showInHelpdesk());
        $this->assertTrue($config->showTimerInBox());
        $this->assertTrue($config->autoOpenRunning());
        $this->assertTrue($config->autoUpdateDuration());
    }

    public function testHelpdeskDisplayIsOptIn(): void
    {
        // 0 = standard interface only (default), 1 = both interfaces
        $this->assertFalse($this->configWithFields(['displayinfofor' => 0])->showInHelpdesk());
        $this->assertFalse($this->configWithFields(['displayinfofor' => '0'])->showInHelpdesk());
        $this->assertTrue($this->configWithFields(['displayinfofor' => '1'])->showInHelpdesk());
    }

    public function testSettingsRequireReauthentication(): void
    {
        $method = (new ReflectionClass(Config::class))->getMethod('itemTypeRequiresReauthentication');
        $this->assertTrue($method->invoke(null));
    }
}
