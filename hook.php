<?php
/*
 -------------------------------------------------------------------------
 ActualTime plugin for GLPI
 Copyright (C) 2018-2022 by the TICgal Team.
 https://www.tic.gal/
 -------------------------------------------------------------------------
 LICENSE
 This file is part of theOneTimeSecret plugin.
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

/**
 * Install all necessary elements for the plugin
 *
 * @return boolean True if success
 */
function plugin_actualtime_install()
{

   $migration = new Migration(PLUGIN_ACTUALTIME_VERSION);

   // Parse inc directory
   foreach (glob(__DIR__ . '/inc/*') as $filepath) {
      // Load *.class.php files and get the class name
      if (preg_match("/inc.(.+)\.class.php/", $filepath, $matches)) {
         $classname = 'PluginActualtime' . ucfirst($matches[1]);
         include_once($filepath);
         // If the install method exists, load it
         if (method_exists($classname, 'install')) {
            $classname::install($migration);
         }
      }
   }

   // Execute the whole migration
   $migration->executeMigration();

   return true;
}

function plugin_actualtime_item_stats($item)
{
   PluginActualtimeTask::showStats($item);
}

function plugin_actualtime_item_update($item)
{
   PluginActualtimeTask::preUpdate($item);
}

function plugin_actualtime_item_add($item)
{
   PluginActualtimeTask::afterAdd($item);
}

function plugin_actualtime_item_purge(TicketTask $item)
{
   global $DB;

   $DB->delete(
      PluginActualtimeTask::getTable(),
      [
         'tasks_id' => $item->fields['id']
      ]
   );
}

function plugin_actualtime_getAddSearchOptions($itemtype)
{
   $tab = [];

   switch ($itemtype) {
      case Ticket::getType():
         $config = new PluginActualtimeConfig;
         if ((Session::getCurrentInterface() == "central") || $config->showInHelpdesk()) {
            $tab = array_merge($tab, PluginActualtimeTask::rawSearchOptionsToAdd());
         }
         break;
   }

   return $tab;
}

/**
 * Uninstall previously installed elements of the plugin
 *
 * @return boolean True if success
 */
function plugin_actualtime_uninstall()
{

   $migration = new Migration(PLUGIN_ACTUALTIME_VERSION);

   // Parse inc directory
   foreach (glob(__DIR__ . '/inc/*') as $filepath) {
      // Load *.class.php files and get the class name
      if (preg_match("/inc.(.+)\.class.php/", $filepath, $matches)) {
         $classname = 'PluginActualtime' . ucfirst($matches[1]);
         include_once($filepath);
         // If the install method exists, load it
         if (method_exists($classname, 'uninstall')) {
            $classname::uninstall($migration);
         }
      }
   }

   // Execute the whole migration
   $migration->executeMigration();

   return true;
}
