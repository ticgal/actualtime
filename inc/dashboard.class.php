<?php

/**
 * -------------------------------------------------------------------------
 * ActualTime plugin for GLPI
 * Copyright (C) 2018-2025 by the TICGAL Team.
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
 * along withOneTimeSecret. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @package   ActualTime
 * @author    the TICGAL team
 * @copyright Copyright (c) 2018-2025 TICGAL team
 * @license   AGPL License 3.0 or (at your option) any later version
 *            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @link      https://www.tic.gal/
 * @since     2018
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginActualtimeDashboard extends CommonDBTM
{
    /**
     * dashboardCards
     *
     * @param  mixed $cards
     * @return array
     */
    public static function dashboardCards($cards): array
    {
        $cards['plugin_actualtime_moreactualtimetasksbyday'] = [
            'widgettype' => ['stackedbars', 'lines'],
            'label' => Ticket::getTypeName() . ' - ' . __('Top 20 Actualtime tasks per day', 'actualtime'),
            'group' => 'Actualtime',
            'filters' => ['dates'],
            'provider' => 'PluginActualtimeProvider::moreActualtimeTasksByDay'
        ];

        $cards['plugin_actualtime_lessactualtimetasks'] = [
            'widgettype' => ['stackedbars', 'lines'],
            'label' => Ticket::getTypeName() . ' - ' . __('Bottom 20 Actualtime tasks per day', 'actualtime'),
            'group' => 'Actualtime',
            'filters' => ['dates'],
            'provider' => 'PluginActualtimeProvider::lessActualtimeTasksByDay'
        ];

        $cards['plugin_actualtime_moreactualtimeusagebyday'] = [
            'widgettype' => ['stackedbars', 'lines'],
            'label' => Ticket::getTypeName() . ' - ' . __('Top 20 Actualtime usage (hours)', 'actualtime'),
            'group' => 'Actualtime',
            'filters' => ['dates'],
            'provider' => 'PluginActualtimeProvider::moreActualtimeUsageByDay'
        ];

        $cards['plugin_actualtime_lessactualtimeusagebyday'] = [
            'widgettype' => ['stackedbars', 'lines'],
            'label' => Ticket::getTypeName() . ' - ' . __('Bottom 20 Actualtime usage (hours)', 'actualtime'),
            'group' => 'Actualtime',
            'filters' => ['dates'],
            'provider' => 'PluginActualtimeProvider::lessActualtimeUsageByDay'
        ];
        $cards['plugin_actualtime_moreapercentagectualtimetasksbyday'] = [
            'widgettype' => ['bars', 'lines'],
            'label' => Ticket::getTypeName() . ' - ' . __('Top 20 % Actualtime usage per day', 'actualtime'),
            'group' => 'Actualtime',
            'filters' => ['dates'],
            'provider' => 'PluginActualtimeProvider::morePercentageActualtimeTasksByDay'
        ];

        $cards['plugin_actualtime_lesspercentageactualtimetasks'] = [
            'widgettype' => ['bars', 'lines'],
            'label' => Ticket::getTypeName() . ' - ' . __('Bottom 20 % Actualtime usage per day', 'actualtime'),
            'group' => 'Actualtime',
            'filters' => ['dates'],
            'provider' => 'PluginActualtimeProvider::lessPercentageActualtimeTasksByDay'
        ];

        return $cards;
    }
}
