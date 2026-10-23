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

namespace GlpiPlugin\Actualtime;

use Glpi\Dashboard\Item;
use Migration;
use Ticket;

class Dashboard
{
    /**
     * dashboardCards
     *
     * @param  ?array $cards
     * @return array
     */
    public static function dashboardCards(?array $cards = []): array
    {
        $cards = $cards ?? [];
        $group = 'Actualtime';

        $cards['plugin_actualtime_moreactualtimetasksbyday'] = [
            'widgettype'    => ['stackedbars', 'lines'],
            'label'         => Ticket::getTypeName() . ' - ' . __('Top 20 Actualtime tasks per day', 'actualtime'),
            'group'         => $group,
            'filters'       => ['dates'],
            'provider'      => Provider::class . '::moreActualtimeTasksByDay',
        ];

        $cards['plugin_actualtime_lessactualtimetasks'] = [
            'widgettype'    => ['stackedbars', 'lines'],
            'label'         => Ticket::getTypeName() . ' - ' . __('Bottom 20 Actualtime tasks per day', 'actualtime'),
            'group'         => $group,
            'filters'       => ['dates'],
            'provider'      => Provider::class . '::lessActualtimeTasksByDay',
        ];

        $cards['plugin_actualtime_moreactualtimeusagebyday'] = [
            'widgettype'    => ['stackedbars', 'lines'],
            'label'         => Ticket::getTypeName() . ' - ' . __('Top 20 Actualtime usage (hours)', 'actualtime'),
            'group'         => $group,
            'filters'       => ['dates'],
            'provider'      => Provider::class . '::moreActualtimeUsageByDay',
        ];

        $cards['plugin_actualtime_lessactualtimeusagebyday'] = [
            'widgettype'    => ['stackedbars', 'lines'],
            'label'         => Ticket::getTypeName() . ' - ' . __('Bottom 20 Actualtime usage (hours)', 'actualtime'),
            'group'         => $group,
            'filters'       => ['dates'],
            'provider'      => Provider::class . '::lessActualtimeUsageByDay',
        ];

        $cards['plugin_actualtime_morepercentageactualtimetasksbyday'] = [
            'widgettype'    => ['bars', 'lines'],
            'label'         => Ticket::getTypeName() . ' - ' . __('Top 20 % Actualtime usage per day', 'actualtime'),
            'group'         => $group,
            'filters'       => ['dates'],
            'provider'      => Provider::class . '::morePercentageActualtimeTasksByDay',
        ];

        $cards['plugin_actualtime_lesspercentageactualtimetasks'] = [
            'widgettype'    => ['bars', 'lines'],
            'label'         => Ticket::getTypeName() . ' - ' . __('Bottom 20 % Actualtime usage per day', 'actualtime'),
            'group'         => $group,
            'filters'       => ['dates'],
            'provider'      => Provider::class . '::lessPercentageActualtimeTasksByDay',
        ];

        return $cards;
    }

    /**
     * install
     * 5.0.0: fix the misspelled key of the "Top 20 % Actualtime usage per day" card in saved dashboards
     *
     * @param  Migration $migration
     * @return void
     */
    public static function install(Migration $migration): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $old = 'plugin_actualtime_moreapercentagectualtimetasksbyday';
        $new = 'plugin_actualtime_morepercentageactualtimetasksbyday';
        $table = Item::getTable();
        foreach ($DB->request(['FROM' => $table, 'WHERE' => ['card_id' => $old]]) as $row) {
            $DB->update(
                $table,
                [
                    'card_id'      => $new,
                    'gridstack_id' => str_replace($old, $new, $row['gridstack_id']),
                    'card_options' => str_replace($old, $new, (string) $row['card_options']),
                ],
                ['id' => $row['id']],
            );
        }
    }
}
