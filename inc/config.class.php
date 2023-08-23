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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginActualtimeConfig
 */
class PluginActualtimeConfig extends CommonDBTM
{
   static private $_instance = null;

   /**
    * PluginActualtimeConfig constructor.
    */
   function __construct()
   {
      global $DB;

      if ($DB->tableExists($this->getTable())) {
         $this->getFromDB(1);
      }
   }

   static function canCreate()
   {
      return Session::haveRight('config', UPDATE);
   }

   static function canView()
   {
      return Session::haveRight('config', READ);
   }

   static function canUpdate()
   {
      return Session::haveRight('config', UPDATE);
   }

   /**
    * @param int $nb
    *
    * @return translated
    */
   static function getTypeName($nb = 0)
   {
      return __("Task timer configuration", "actualtime");
   }

   static function getInstance()
   {
      if (!isset(self::$_instance)) {
         self::$_instance = new self();
         if (!self::$_instance->getFromDB(1)) {
            self::$_instance->getEmpty();
         }
      }
      return self::$_instance;
   }

   /**
    * @param bool $update
    *
    * @return PluginActualtimeConfig
    */
   static function getConfig($update = false)
   {
      static $config = null;
      if (is_null(self::$config)) {
         $config = new self();
      }
      if ($update) {
         $config->getFromDB(1);
      }
      return $config;
   }

   static function showConfigForm()
   {
      $rand = mt_rand();

      $config = new self();
      $config->getFromDB(1);

      $config->showFormHeader(['colspan' => 4]);

      $values = [
         0 => __('In Standard interface only (default)', 'actualtime'),
         1 => __('Both in Standard and Helpdesk interfaces', 'actualtime'),
      ];
      echo "<table class='tab_cadre_fixe'><thead>";
      echo "<th colspan='4'>" . self::getTypeName() . '</th></thead>';

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __("Enable timer on tasks", "actualtime") . "</td><td>";
      Dropdown::showFromArray(
         'displayinfofor',
         $values,
         [
            'value' => $config->fields['displayinfofor']
         ]
      );
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __("Display pop-up window with current running timer", "actualtime") . "</td><td>";
      Dropdown::showYesNo('showtimerpopup', $config->showTimerPopup(), -1);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __("Display actual time in closed task box ('Processing ticket' list)", "actualtime") . "</td><td>";
      Dropdown::showYesNo('showtimerinbox', $config->showTimerInBox(), -1);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1' name='optional$rand'>";
      echo "<td>" . __("Automatically open task with timer running", "actualtime") . "</td><td>";
      Dropdown::showYesNo('autoopenrunning', $config->autoOpenRunning(), -1);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1' name='optional$rand'>";
      echo "<td>" . __("Automatically update the duration", "actualtime") . "</td><td>";
      Dropdown::showYesNo('autoupdate_duration', $config->autoUpdateDuration(), -1);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1' name='optional$rand'>";
      echo "<td>" . __("Block timer on planned task", "actualtime") . "</td><td>";
      Dropdown::showYesNo('planned_task', $config->fields['planned_task'], -1);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1' name='optional$rand'>";
      echo "<td>" . __("Block multiple days on task", "actualtime") . "</td><td>";
      Dropdown::showYesNo('multiple_day', $config->fields['multiple_day'], -1);
      echo "</td>";
      echo "</tr>";

      $config->showFormButtons(['candel' => false]);

      return false;
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
   {

      if ($item->getType() == 'Config') {
         return __("Actual time", "actualtime");
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
   {

      if ($item->getType() == 'Config') {
         self::showConfigForm();
      }
      return true;
   }

   /**
    * Is displaying timer pop-up on every page enabled in plugin settings?
    *
    * @return boolean
    */
   function showTimerPopup()
   {
      return ($this->fields['showtimerpopup'] ? true : false);
   }

   /**
    * Is actual time information (timers) shown also in Helpdesk interface?
    *
    * @return boolean
    */
   function showInHelpdesk()
   {
      return ($this->fields['displayinfofor'] == 1);
   }

   /**
    * Is timer shown in closed task box at 'Actions historical' page?
    *
    * @return boolean
    */
   function showTimerInBox()
   {
      return ($this->fields['showtimerinbox'] ? true : false);
   }

   /**
    * Auto open the form for the task with a currently running timer
    * when listing tickets' tasks?
    *
    * @return boolean
    */
   function autoOpenRunning()
   {
      return ($this->fields['autoopenrunning'] ? true : false);
   }

   function autoUpdateDuration()
   {
      return $this->fields['autoupdate_duration'];
   }

   static function install(Migration $migration)
   {
      global $DB;

      $default_charset = DBConnection::getDefaultCharset();
      $default_collation = DBConnection::getDefaultCollation();
      $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

      $table = self::getTable();
      $config = new self();
      if (!$DB->tableExists($table)) {

         $migration->displayMessage("Installing $table");

         $query = "CREATE TABLE IF NOT EXISTS $table (
                      `id` int {$default_key_sign} NOT NULL auto_increment,
                      `displayinfofor` smallint NOT NULL DEFAULT '0',
                      `showtimerpopup` TINYINT NOT NULL DEFAULT '1',
                      `showtimerinbox` TINYINT NOT NULL DEFAULT '1',
                      `autoopenrunning` TINYINT NOT NULL DEFAULT '0',
                      `autoupdate_duration` TINYINT NOT NULL DEFAULT '0',
                      `planned_task` TINYINT NOT NULL DEFAULT '0',
                      `multiple_day` TINYINT NOT NULL DEFAULT '0',
                      PRIMARY KEY (`id`)
         ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
         $DB->query($query) or die($DB->error());
         $config->add([
            'id' => 1,
            'displayinfofor' => 0,
         ]);
      } else {
         $migration->changeField($table, 'showtimerpopup', 'showtimerpopup', 'bool', ['value' => 1]);
         $migration->changeField($table, 'showtimerinbox', 'showtimerinbox', 'bool', ['value' => 1]);
         $migration->changeField($table, 'autoopenrunning', 'autoopenrunning', 'bool', ['value' => 0]);
         $migration->dropField($table, 'autoopennew');
         
         $migration->addField($table, 'planned_task', 'bool');
         $migration->addField($table, 'multiple_day', 'bool');

         $migration->migrationOneTable($table);
      }
   }

   static function uninstall(Migration $migration)
   {
      global $DB;

      $table = self::getTable();
      if ($DB->TableExists($table)) {
         $migration->displayMessage("Uninstalling $table");
         $migration->dropTable($table);
      }
   }
}
