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

use Glpi\Dashboard\Dashboard as GlpiDashboard;
use Glpi\Dashboard\Item;
use GlpiPlugin\Actualtime\Dashboard;
use GlpiPlugin\Actualtime\Task;
use GlpiPlugin\Actualtime\Tests\ActualtimeTestCase;
use Migration;
use User;

/**
 * 5.0.0 upgrade steps: data saved with the 4.x class names and card keys.
 */
class MigrationTest extends ActualtimeTestCase
{
    private const OLD_CARD = 'plugin_actualtime_moreapercentagectualtimetasksbyday';
    private const NEW_CARD = 'plugin_actualtime_morepercentageactualtimetasksbyday';

    public function testPluginLegacyClassNamesStillResolve(): void
    {
        // TAM and GappExtended still use these names
        $this->assertTrue(class_exists('PluginActualtimeTask'));
        $this->assertSame(Task::class, (new \ReflectionClass('PluginActualtimeTask'))->getName());
        $this->assertSame('glpi_plugin_actualtime_tasks', \PluginActualtimeTask::getTable());
    }

    public function testMisspelledDashboardCardKeyIsMigrated(): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $dashboard = new GlpiDashboard();
        $this->assertTrue($dashboard->getFromDB('central'));
        $DB->insert(Item::getTable(), [
            'dashboards_dashboards_id' => $dashboard->fields['id'],
            'gridstack_id'             => self::OLD_CARD . '_test',
            'card_id'                  => self::OLD_CARD,
            'x'                        => 0,
            'y'                        => 50,
            'width'                    => 4,
            'height'                   => 4,
            'card_options'             => json_encode(['card_id' => self::OLD_CARD, 'color' => '#aaaaaa']),
        ]);
        $id = $DB->insertId();

        Dashboard::install(new Migration(PLUGIN_ACTUALTIME_VERSION));
        // idempotent: a second run finds nothing to migrate
        Dashboard::install(new Migration(PLUGIN_ACTUALTIME_VERSION));

        $row = $DB->request(['FROM' => Item::getTable(), 'WHERE' => ['id' => $id]])->current();
        $this->assertSame(self::NEW_CARD, $row['card_id']);
        $this->assertSame(self::NEW_CARD . '_test', $row['gridstack_id']);
        $this->assertStringNotContainsString(self::OLD_CARD, $row['card_options']);
        $this->assertSame(0, countElementsInTable(Item::getTable(), ['card_id' => self::OLD_CARD]));

        // the migrated key is the one the plugin provides
        $this->assertArrayHasKey(self::NEW_CARD, Dashboard::dashboardCards());
        $this->assertArrayNotHasKey(self::OLD_CARD, Dashboard::dashboardCards());
    }

    public function testPlanningFiltersOfTheLegacyClassAreMigrated(): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $legacy = ['color' => '#123456', 'display' => false, 'type' => 'event_filter'];
        $current = ['color' => '#654321', 'display' => true, 'type' => 'event_filter'];
        $migrated_id = $this->createUser('at_planning_' . uniqid(), 'Technician', 0, true);
        $kept_id = $this->createUser('at_planning_kept_' . uniqid(), 'Technician', 0, true);
        $DB->update(User::getTable(), ['plannings' => json_encode(['filters' => ['PluginActualtimeTask' => $legacy], 'plannings' => []])], ['id' => $migrated_id]);
        // a user who already has the new key keeps it
        $DB->update(User::getTable(), ['plannings' => json_encode(['filters' => ['PluginActualtimeTask' => $legacy, Task::class => $current], 'plannings' => []])], ['id' => $kept_id]);

        // the data step of Task::install() (the schema steps are DDL, run by the CI install cycle)
        $this->runPlanningFiltersMigration();
        // running it twice changes nothing
        $this->runPlanningFiltersMigration();

        $filters = static function (int $id): array {
            $user = new User();
            $user->getFromDB($id);
            return json_decode($user->fields['plannings'], true)['filters'];
        };
        $this->assertSame([Task::class => $legacy], $filters($migrated_id));
        $this->assertSame([Task::class => $current], $filters($kept_id));
    }

    private function runPlanningFiltersMigration(): void
    {
        $method = new \ReflectionMethod(Task::class, 'migratePlanningFilters');
        $method->invoke(null, new Migration(PLUGIN_ACTUALTIME_VERSION));
    }
}
