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

use Glpi\Plugin\Hooks;

define('PLUGIN_ACTUALTIME_VERSION', '3.2.0');

// Minimal GLPI version, inclusive
define("PLUGIN_ACTUALTIME_MIN_GLPI", "10.0.10");
// Maximum GLPI version, exclusive
define("PLUGIN_ACTUALTIME_MAX_GLPI", "10.1.0");
define("PLUGIN_ACTUALTIME_NAME", "ActualTime");

/**
 * plugin_version_actualtime
 *
 * @return array
 */
function plugin_version_actualtime(): array
{
    return [
        'name'          => PLUGIN_ACTUALTIME_NAME,
        'version'       => PLUGIN_ACTUALTIME_VERSION,
        'author'        => '<a href="https://tic.gal">TICgal</a>',
        'homepage'      => 'https://tic.gal/en/project/actualtime-plugin-glpi/',
        'license'       => 'AGPLv3+',
        'requirements'  => [
            'glpi'   => [
                'min' => PLUGIN_ACTUALTIME_MIN_GLPI,
                'max' => PLUGIN_ACTUALTIME_MAX_GLPI,
            ]
        ]
    ];
}

/**
 * plugin_init_actualtime
 *
 * @return void
 */
function plugin_init_actualtime(): void
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['actualtime'] = true;

    $plugin = new Plugin();

    if ($plugin->isActivated('actualtime')) { //is plugin active?
        // Classes
        Plugin::registerClass(PluginActualtimeProfile::class, ['addtabon' => 'Profile']);
        // Add settings form as a tab on Setup - General page
        Plugin::registerClass(PluginActualtimeConfig::class, ['addtabon' => 'Config']);

        Plugin::registerClass(PluginActualtimeTask::class, ['planning_types' => true]);

        // Hooks
        $PLUGIN_HOOKS[Hooks::POST_ITEM_FORM]['actualtime'] = [PluginActualtimeTask::class, 'postForm'];

        $PLUGIN_HOOKS[Hooks::SHOW_ITEM_STATS]['actualtime'] = [
            Ticket::class       => 'plugin_actualtime_item_stats',
            Change::class       => 'plugin_actualtime_item_stats',
            Problem::class      => 'plugin_actualtime_item_stats',
        ];

        $PLUGIN_HOOKS[Hooks::PRE_ITEM_UPDATE]['actualtime'] = [
            TicketTask::class   => 'plugin_actualtime_item_update',
            ChangeTask::class   => 'plugin_actualtime_item_update',
            ProblemTask::class  => 'plugin_actualtime_item_update',
            ProjectTask::class  => 'plugin_actualtime_item_update',
        ];

        $PLUGIN_HOOKS[Hooks::ITEM_DELETE]['actualtime'] = [
            Ticket::class       => 'plugin_actualtime_parent_delete',
            Change::class       => 'plugin_actualtime_parent_delete',
            Problem::class      => 'plugin_actualtime_parent_delete',
            Project::class      => 'plugin_actualtime_project_delete',
        ];

        $PLUGIN_HOOKS[Hooks::ITEM_PURGE]['actualtime'] = [
            TicketTask::class   => 'plugin_actualtime_item_purge',
            ChangeTask::class   => 'plugin_actualtime_item_purge',
            ProblemTask::class  => 'plugin_actualtime_item_purge',
            ProjectTask::class  => 'plugin_actualtime_item_purge',
        ];

        $PLUGIN_HOOKS[Hooks::PRE_ITEM_ADD]['actualtime'] = [
            ITILSolution::class => 'plugin_actualtime_preSolutionAdd',
        ];

        $PLUGIN_HOOKS[Hooks::ITEM_ADD]['actualtime'] = [
            TicketTask::class   => 'plugin_actualtime_item_add',
            ChangeTask::class   => 'plugin_actualtime_item_add',
            ProblemTask::class  => 'plugin_actualtime_item_add',
        ];

        $PLUGIN_HOOKS[Hooks::POST_SHOW_ITEM]['actualtime'] = 'plugin_actualtime_postshowitem';

        $PLUGIN_HOOKS[Hooks::DASHBOARD_CARDS]['actualtime'] = [PluginActualtimeDashboard::class, 'dashboardCards'];

        $config = new PluginActualtimeConfig();
        if ($config->showTimerPopup()) {
           // This hook is not needed if not showing popup
            $PLUGIN_HOOKS[Hooks::POST_SHOW_TAB]['actualtime'] = [PluginActualtimeTask::class, 'postShowTab'];
        }

        if (Session::getLoginUserID()) {
            $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['actualtime'] = 'js/actualtime.js';
        }

        if (Session::haveRight('plugin_actualtime_running', READ)) {
            $PLUGIN_HOOKS['menu_toadd']['actualtime'] = ['admin' => 'PluginActualtimeRunning'];
        }

        // Standard settings link, on Setup - Plugins page
        $PLUGIN_HOOKS['config_page']['actualtime'] = 'front/config.form.php';
    }
}
