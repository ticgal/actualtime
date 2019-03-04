<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginActualtimeConfig
 */
class PluginActualtimeConfig extends CommonDBTM {

   static $rightname = 'config';
   static private $_config = null;

   /**
    * @param bool $update
    *
    * @return PluginActualtimeConfig
    */
   static function getConfig($update = false) {

      if (!isset(self::$_config)) {
         self::$_config = new self();
      }
      if ($update) {
         self::$_config->getFromDB(1);
      }
      return self::$_config;
   }

   /**
    * PluginActualtimeConfig constructor.
    */
   function __construct() {
      global $DB;

      if ($DB->tableExists($this->getTable())) {
         $this->getFromDB(1);
      }
   }

   static function canCreate() {
      return Session::haveRight('config', UPDATE);
   }


   static function canView() {
      return Session::haveRight('config', READ);
   }

   /**
    * @param int $nb
    *
    * @return translated
    */
   static function getTypeName($nb = 0) {
      return __("Task timer configuration", "actualtime");
   }

   function showForm() {

      $rand = mt_rand();

      $this->getFromDB(1);
      $this->showFormHeader();

      echo "<input type='hidden' name='id' value='1'>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __("Enable timer on tasks", "actualtime") . "</td><td>";
      Dropdown::showYesNo('enable', $this->isEnabled(), -1,
                          ['on_change' => 'show_hide_options(this.value);']);
      echo "</td>";
      echo "</tr>";

      echo Html::scriptBlock("
         function show_hide_options(val) {
            var display = (val == 0) ? 'none' : '';
            $('tr[name=\"optional$rand\"').css( 'display', display );
         }");

      $style = ($this->isEnabled()) ? "" : "style='display: none '";

      // Include lines with other settings

      echo "<tr class='tab_bg_1' name='optional$rand' $style>";
      echo "<td>" . __("Display pop-up window with current running timer", "actualtime") . "</td><td>";
      Dropdown::showYesNo('showtimerpopup', $this->showTimerPopup(), -1);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1' name='optional$rand' $style>";
      echo "<td>" . __("Automatically open new created tasks", "actualtime") . "</td><td>";
      Dropdown::showYesNo('autoopennew', $this->autoOpenNew(), -1);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1' name='optional$rand' $style>";
      echo "<td>" . __("Automatically open task with timer running", "actualtime") . "</td><td>";
      Dropdown::showYesNo('autoopenrunning', $this->autoOpenRunning(), -1);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1' align='center'>";
      $this->showFormButtons(['candel'=>false]);
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if ($item->getType()=='Config') {
            return __("Actual time", "actualtime");
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      if ($item->getType()=='Config') {
         $instance = self::getConfig();
         $instance->showForm();
      }
      return true;
   }

   /**
    * Plugin is enabled in plugin settings?
    *
    * @return boolean
    */
   function isEnabled() {
      return ($this->fields['enable'] ? true : false);
   }

   /**
    * Timer pop-up display on every page enabled in plugin settings?
    *
    * @return boolean
    */
   function showTimerPopup() {
      return ($this->fields['showtimerpopup'] ? true : false);
   }

   /**
    * Auto open the form for the task that was just created (new tasks)?
    *
    * @return boolean
    */
   function autoOpenNew() {
      return ($this->fields['autoopennew'] ? true : false);
   }

   /**
    * Auto open the form for the task with a currently running timer
    * when listing tickets' tasks?
    *
    * @return boolean
    */
   function autoOpenRunning() {
      return ($this->fields['autoopenrunning'] ? true : false);
   }

   static function install(Migration $migration) {
      global $DB;

      $table = self::getTable();
      if (! $DB->tableExists($table)) {
         $migration->displayMessage("Installing $table");

         $query = "CREATE TABLE IF NOT EXISTS $table (
                      `id` int(11) NOT NULL auto_increment,
                      `enable` boolean NOT NULL DEFAULT true,
                      `showtimerpopup` boolean NOT NULL DEFAULT true,
                      `autoopennew` boolean NOT NULL DEFAULT false,
                      `autoopenrunning` boolean NOT NULL DEFAULT false,
                      PRIMARY KEY (`id`)
                   )
                   ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
         $DB->query($query) or die($DB->error());
      }

      if ($DB->tableExists($table)) {
         if (! $DB->fieldExists($table, 'showtimerpopup')) {
            // Add new field showtimerpopup
            $migration->addField(
               $table,
               'showtimerpopup',
               'boolean',
               [
                  'update' => true,
                  'value'  => true,
                  'after' => 'enable'
               ]
            );
         }
         if (! $DB->fieldExists($table, 'autoopennew')) {
            // Add new field autoopennew
            $migration->addField(
               $table,
               'autoopennew',
               'boolean',
               [
                  'update' => false,
                  'value'  => false,
                  'after'  => 'showtimerpopup',
               ]
            );
         }
         if (! $DB->fieldExists($table, 'autoopenrunning')) {
            // Add new field autoopenrunning
            $migration->addField(
               $table,
               'autoopenrunning',
               'boolean',
               [
                  'update' => false,
                  'value'  => false,
                  'after'  => 'autoopennew',
               ]
            );
         }
         // Create default record (if it does not exist)
         $reg = $DB->request($table);
         if (! count($reg)) {
            $DB->insert(
               $table, [
                  'enable' => true
               ]
            );
         }

      }

   }

   static function uninstall(Migration $migration) {
      global $DB;

      $table = self::getTable();
      if ($DB->TableExists($table)) {
         $migration->displayMessage("Uninstalling $table");
         $migration->dropTable($table);
      }
   }
}
