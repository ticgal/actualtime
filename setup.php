<?php
/*
 -------------------------------------------------------------------------
 ActualTime plugin for GLPI
 Copyright (C) 2018-2022 by the TICgal Team.
 https://www.tic.gal/
 -------------------------------------------------------------------------
 LICENSE
 This file is part of the ActualTime plugin.
 ActualTime plugin is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 3 of the License, or
 (at your option) any later version.
 ActualTime plugin is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 You should have received a copy of the GNU General Public License
 along withOneTimeSecret. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 @package   ActualTime
 @author    the TICgal team
 @copyright Copyright (c) 2018-2022 TICgal team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://www.tic.gal/
 @since     2018-2022
 ----------------------------------------------------------------------
*/

define('PLUGIN_ACTUALTIME_VERSION', '2.0.0');

// Minimal GLPI version, inclusive
define("PLUGIN_ACTUALTIME_MIN_GLPI", "10.0.0");
// Maximum GLPI version, exclusive
define("PLUGIN_ACTUALTIME_MAX_GLPI", "10.1.0");

function plugin_version_actualtime()
{
   return [
      'name'       => 'ActualTime',
      'version'        => PLUGIN_ACTUALTIME_VERSION,
      'author'         => '<a href="https://tic.gal">TICgal</a>',
      'homepage'       => 'https://tic.gal/en/project/actualtime-plugin-glpi/',
      'license'        => 'AGPLv3+',
      'requirements'   => [
         'glpi'   => [
            'min' => PLUGIN_ACTUALTIME_MIN_GLPI,
            'max' => PLUGIN_ACTUALTIME_MAX_GLPI,
         ]
      ]
   ];
}

function plugin_init_actualtime()
{
   global $PLUGIN_HOOKS, $CFG_GLPI;

   $PLUGIN_HOOKS['csrf_compliant']['actualtime'] = true;

   $plugin = new Plugin();

   if ($plugin->isActivated('actualtime')) { //is plugin active?

      // Standard settings link, on Setup - Plugins page
      $PLUGIN_HOOKS['config_page']['actualtime'] = 'front/config.form.php';
      // Add settings form as a tab on Setup - General page
      Plugin::registerClass('PluginActualtimeConfig', ['addtabon' => 'Config']);

      $config = new PluginActualtimeConfig();

      $PLUGIN_HOOKS['post_item_form']['actualtime'] = ['PluginActualtimeTask', 'postForm'];
      $PLUGIN_HOOKS['show_item_stats']['actualtime'] = ['Ticket' => 'plugin_actualtime_item_stats'];
      $PLUGIN_HOOKS['pre_item_update']['actualtime'] = ['TicketTask' => 'plugin_actualtime_item_update'];
      $PLUGIN_HOOKS['post_show_item']['actualtime'] = ['PluginActualtimeTask', 'postShowItem'];
      $PLUGIN_HOOKS['add_javascript']['actualtime'] = 'js/actualtime.js';
      $PLUGIN_HOOKS['item_purge']['actualtime'] = ['TicketTask' => 'plugin_actualtime_item_purge'];
      $PLUGIN_HOOKS['pre_item_add']['actualtime']=[
         'ITILSolution' => 'plugin_actualtime_preSolutionAdd',
      ];

      if ($config->showTimerPopup()) {
         // This hook is not needed if not showing popup
         $PLUGIN_HOOKS['post_show_tab']['actualtime'] = ['PluginActualtimeTask', 'postShowTab'];
      }

      $PLUGIN_HOOKS['item_add']['actualtime'] = ['TicketTask' => 'plugin_actualtime_item_add'];

      $PLUGIN_HOOKS['menu_toadd']['actualtime'] = ['admin' => 'PluginActualtimeRunning'];

      Plugin::registerClass('PluginActualtimeTask', ['planning_types' => true]);

      $PLUGIN_HOOKS['dashboard_cards']['actualtime'] = ['PluginActualtimeDashboard', 'dashboardCards'];
   }
}
