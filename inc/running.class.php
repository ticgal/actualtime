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

class PluginActualtimeRunning extends CommonGLPI
{
	static $rightname = 'plugin_actualtime_running';

	static function getMenuName()
	{
		return __("Actualtime", "actualtime");
	}

	static function getMenuContent()
	{
		$menu = [
			'title' => self::getMenuName(),
			'page' => self::getSearchURL(false),
			'icon' => 'fas fa-stopwatch'
		];

		return $menu;
	}

	static public function show()
	{
		global $DB;

		$rand = mt_rand();
		echo "<div class='center'>";
		echo "<h1>" . __("Running timers", "actualtime") . "</h1>";
		echo "</div>";

		echo "<div class='right' style='padding:10px;max-width: 950px;margin: 0px auto 5px auto;'>";

		echo "<label style='padding:2px'>" . __("Update every (s)", "actualtime") . " </label>";
		Dropdown::showNumber('interval', ['value' => 5, 'min' => 5, 'max' => MINUTE_TIMESTAMP, 'step' => 10, 'rand' => $rand]);
		echo "<label style='padding:2px'>" . __("Disable") . " </label>";
		Dropdown::showYesNo('disable', 0, -1, ['use_checkbox' => true, 'rand' => $rand]);
		echo "<i id='refresh' class='fa fa-sync pointer' style='margin-left: 10px;font-size: 15px'></i>";

		echo "</div>";

		echo "<div id='running'>";
		echo "<div>";
		$script = <<<JAVASCRIPT
		$(document).ready(function() {
			var loading=setInterval(loadRunning,5000);
			var interval=5000;

			function loadRunning(){
				$.ajax({
					type:'POST',
					url:CFG_GLPI.root_doc+"/"+GLPI_PLUGINS_PATH.actualtime+"/ajax/running.php",
					data:{
						action:'getlist'
					},
					success:function(data){
						$('#running').html(data);
					}
				});
			}
			loadRunning();
			$('#refresh').click(function(){
				loadRunning();
			});

			$('#dropdown_interval{$rand}').on('change',function(){
				clearInterval(loading);
				interval=(this.value*1000);
				loading=setInterval(loadRunning,(this.value*1000));
			});
			$('#dropdown_disable{$rand}').change(function(){
				if (this.checked) {
					clearInterval(loading);
				} else {
					loading=setInterval(loadRunning,interval);
				}
			});
		});
JAVASCRIPT;
		echo Html::scriptBlock($script);
	}

	static function listRunning()
	{
		global $DB;

		$tasktable = TicketTask::getTable();
		$tickettable = Ticket::getTable();

		$query = [
			'SELECT' => [
				PluginActualtimeTask::getTable() . '.*',
			],
			'FROM' => PluginActualtimeTask::getTable(),
			'INNER JOIN' => [
				$tasktable => [
					'ON' => [
						$tasktable => 'id',
						PluginActualtimeTask::getTable() => 'tickettasks_id'
					]
				],
				$tickettable => [
					'ON' => [
						$tickettable => 'id',
						$tasktable => 'tickets_id'
					]
				],
			],
			'WHERE' => [
				[
					'NOT' => ['actual_begin' => null],
				],
				'actual_end' => null,
			] + getEntitiesRestrictCriteria($tickettable),
		];
		$iterator = $DB->request($query);
		if ($iterator->count() > 0) {
			$html = "<table class='tab_cadre_fixehov'>";
			$html .= "<tr>";
			$html .= "<th class='center'>" . __("Technician") . "</th>";
			$html .= "<th class='center'>" . __("Entity") . "</th>";
			$html .= "<th class='center'>" . __("Ticket") . " - " . __("Task") . "</th>";
			$html .= "<th class='center'>" . __("Time") . "</th>";
			$html .= "</tr>";

			foreach ($iterator as $key => $row) {
				$html .= "<tr class='tab_bg_2'>";
				$user = new User();
				$user->getFromDB($row['users_id']);
				$html .= "<td class='center'><a href='" . $user->getLinkURL() . "'>" . $user->getFriendlyName() . "</a></td>";
				$task_id = $row['tickettasks_id'];
				$task = new TicketTask();
				$task->getFromDB($row['tickettasks_id']);
				$ticket = new Ticket();
				$ticket->getFromDB($task->fields['tickets_id']);
				$html .= "<td class='center'>" . Entity::getFriendlyNameById($ticket->fields['entities_id']) . "</td>";
				$html .= "<td class='center'><a href='" . $ticket->getLinkURL() . "'>" . $ticket->getID() . " - " . $task->getID() . "</a></td>";
				$html .= "<td class='center'>" . HTML::timestampToString(PluginActualtimeTask::totalEndTime($row['tickettasks_id'])) . "</td>";
				$html .= "</tr>";
			}
			$html .= "</table>";
		} else {
			$html = "<div><p class='center b'>" . __('No timer active') . "</p></div>";
		}
		return $html;
	}
}
