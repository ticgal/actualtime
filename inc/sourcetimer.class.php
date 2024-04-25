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

class PluginActualtimeSourcetimer extends CommonDBTM
{
   public static $rightname = 'plugin_actualtime_sourcetimer';
   const TICKET  = 1024;
   const CHANGE = 2048;
   const PROJECT  = 4096;

   public function getRights($interface = 'central')
   {
      if ($interface == 'central') {
         return [
            self::TICKET  => __('Modify tickets', 'actualtime'),
            self::CHANGE => __('Modify changes', 'actualtime'),
            self::PROJECT => __('Modify projects', 'actualtime'),
         ];
      }
      return [];
   }

   public static function checkItemtypeRight($itemtype)
   {
      switch ($itemtype) {
         case 'TicketTask':
            return Session::haveRight(self::$rightname, self::TICKET);
            break;
         case 'ChangeTask':
            return Session::haveRight(self::$rightname, self::CHANGE);
            break;
         case 'ProjectTask':
            return Session::haveRight(self::$rightname, self::PROJECT);
            break;
         default:
            return false;
            break;
      }
   }

   public static function canModify($itemtype, $items_id)
   {
      global $DB;

      switch ($itemtype) {
         case 'TicketTask':
         case 'ChangeTask':
            $task = new $itemtype();
            if ($task->getFromDB($items_id)) {
               $parent = getItemForItemtype($task->getItilObjectItemType());
               if ($parent->getFromDB($task->fields[$parent->getForeignKeyField()])) {
                  if ($parent->fields['status'] < CommonITILObject::SOLVED) {
                     return true;
                  }
               }
            }
            break;
         case 'ProjectTask':
            $task = new $itemtype();
            if ($task->getFromDB($items_id)) {
               $finished_states_it = $DB->request(
                  [
                     'SELECT' => ['id'],
                     'FROM'   => ProjectState::getTable(),
                     'WHERE'  => [
                        'is_finished' => 1
                     ],
                  ]
               );
               $finished_states_ids = [];
               foreach ($finished_states_it as $finished_state) {
                  $finished_states_ids[] = $finished_state['id'];
               }
               if (!in_array($task->getField('projectstates_id'), $finished_states_ids)) {
                  return true;
               }
            }
            break;
      }

      return false;
   }

   static function postShowItem($params)
   {
      $item = $params['item'];
      if (!is_object($item) || !method_exists($item, 'getType')) {
         // Sometimes, params['item'] is just an array, like 'Solution'
         return;
      }
      $itemtype = $item->getType();
      if (!self::checkItemtypeRight($itemtype)) {
         return;
      }
      if (countElementsInTable(PluginActualtimeTask::getTable(), ['items_id' => $item->getID(), 'itemtype' => $itemtype, 'NOT' => ['actual_end' => NULL]]) == 0) {
         return;
      }
      if (!self::canModify($itemtype, $item->getID())) {
         return;
      }
      $task_id = $item->getID();

      $html = "<div class='dropdown ms-2'>";
      $html .= "<a href='#' data-bs-toggle='modal' data-bs-target='#add_time_{$task_id}'>";
      $html .= "<span class='fas fa-calendar-plus control_item' title='" . __("Modify timers", "actualtime") . "'></span>";
      $html .= "</a></div>";
      $script = <<<JAVASCRIPT
			$(document).ready(function() {
				$("div[data-itemtype='{$itemtype}'][data-items-id='{$task_id}'] div.timeline-item-buttons").prepend("{$html}");
         });
JAVASCRIPT;
      echo Html::scriptBlock($script);
      echo Ajax::createIframeModalWindow('add_time_' . $task_id, Plugin::getWebDir('actualtime') . "/ajax/changetimer.php?itemtype=" . $itemtype . "&task_id=" . $task_id, ['reloadonclose' => true, 'dialog_class' => 'modal-xl', 'title' => __('Modify timers', 'actualtime')]);
   }

   function modalForm($itemtype, $items_id)
   {
      global $DB;

      echo "<form name='form' id='form' method='post' action='" . $this->getFormURL() . "' enctype='multipart/form-data'>";
      echo Html::hidden('itemtype', ['value' => $itemtype]);
      echo Html::hidden('items_is', ['value' => $items_id]);

      $query = [
         'FROM' => PluginActualtimeTask::getTable(),
         'WHERE' => [
            'items_id' => $items_id,
            'itemtype' => $itemtype,
            'NOT' => ['actual_end' => NULL],
         ],
      ];

      foreach ($DB->request($query) as $data) {
         echo "<div id='mainformtable'>";
         echo "<div class='card-body row'>";

         echo "<div class='form-field row col-12 mb-2'>";
         echo "<label class='col-form-label col-2 text-xxl-end'>" . __('Start date') . "</label>";
         echo "<label class='col-form-label col-2'>" . $data['actual_begin'] . "</label>";
         echo "<label class='col-form-label col-2 text-xxl-end'>" . __('End date') . "</label>";
         echo "<div class='col-6  field-container'>";
         Html::showDateTimeField('actual_end[' . $data['id'] . ']', ['value' => $data['actual_end']]);
         echo "</div>";
         echo "</div>";

         echo "</div>";
         echo "</div>";
      }

      echo "<div class='card-body mx-n2 mb-4 border-top d-flex flex-row-reverse align-items-start flex-wrap'>";
      echo "<button class='btn btn-primary me-2' type='submit' name='update' value='1'>
      <i class='far fa-save'></i>
      <span>" . _x('button', 'Save') . "</span>
      </button>";
      echo "</div>";
      echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
      echo "</div>";
      echo "</form>";

      Html::closeForm();
   }

   static function install(Migration $migration)
   {
      global $DB;

      $default_charset = DBConnection::getDefaultCharset();
      $default_collation = DBConnection::getDefaultCollation();
      $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

      $table = self::getTable();

      if (!$DB->tableExists($table)) {
         $migration->displayMessage("Installing $table");

         $query = "CREATE TABLE IF NOT EXISTS $table (
            `id` int {$default_key_sign} NOT NULL auto_increment,
            `plugin_actualtime_tasks_id` int {$default_key_sign} NOT NULL DEFAULT '0',
            `users_id` int {$default_key_sign} NOT NULL DEFAULT '0',
            `source_end` TIMESTAMP NULL DEFAULT NULL,
            `source_actiontime` int {$default_key_sign} NOT NULL DEFAULT 0,
            `date_creation` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `plugin_actualtime_tasks_id` (`plugin_actualtime_tasks_id`),
            KEY `users_id` (`users_id`)
         ) ENGINE=InnoDB  DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
         $DB->query($query) or die($DB->error());
      }
   }
}
