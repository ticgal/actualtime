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

include("../../../inc/includes.php");

if (!isset($_POST["itemtype"])) {
	Html::back();
}
if (PluginActualtimeSourcetimer::checkItemtypeRight($_POST["itemtype"]) && PluginActualtimeSourcetimer::canModify($_POST["itemtype"], $_POST["items_is"])) {
	if (isset($_POST["update"])) {
		foreach ($_POST['actual_end'] as $key => $value) {
			if (!empty($value)) {
				$actualtime = new PluginActualtimeTask();
				if ($actualtime->getFromDB($key)) {
					if ($value != $actualtime->fields['actual_end'] && $value > $actualtime->fields['actual_begin']) {
						$seconds = (strtotime($value) - strtotime($actualtime->fields['actual_begin']));
						$input = [
							'id' => $key,
							'actual_end' => $value,
							'actual_actiontime' => $seconds,
							'is_modified' => 1
						];
						if ($actualtime->fields['is_modified'] == 0) {
							$source = new PluginActualtimeSourcetimer();
							$input_source = [
								'plugin_actualtime_tasks_id' => $actualtime->fields['id'],
								'users_id' => Session::getLoginUserID(),
								'source_end' => $actualtime->fields['actual_end'],
								'source_actiontime' => $actualtime->fields['actual_actiontime'],
							];
							$source->add($input_source);
						}
						$actualtime->update($input);
					}
				}
			}
		}
	}
}
Html::back();