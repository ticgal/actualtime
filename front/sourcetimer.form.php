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

include("../../../inc/includes.php");

if (!isset($_POST["itemtype"])) {
    Html::back();
}
if (
    PluginActualtimeSourcetimer::checkItemtypeRight($_POST["itemtype"])
    && PluginActualtimeSourcetimer::canModify($_POST["itemtype"], $_POST["items_id"])
) {
    if (isset($_POST["update"])) {
        $config = new PluginActualtimeConfig();
        foreach ($_POST['actual_end'] as $key => $value) {
            if (!empty($value)) {
                $actualtime = new PluginActualtimeTask();
                if ($actualtime->getFromDB($key)) {
                    if ($value != $actualtime->fields['actual_end'] && $value > $actualtime->fields['actual_begin']) {
                        $seconds = (strtotime($value) - strtotime($actualtime->fields['actual_begin']));
                        $input = [
                            'id'                => $key,
                            'actual_end'        => $value,
                            'actual_actiontime' => $seconds,
                            'is_modified'       => 1
                        ];
                        if ($actualtime->fields['is_modified'] == 0) {
                            $source = new PluginActualtimeSourcetimer();
                            $input_source = [
                                'plugin_actualtime_tasks_id' => $actualtime->fields['id'],
                                'users_id'          => Session::getLoginUserID(),
                                'source_end'        => $actualtime->fields['actual_end'],
                                'source_actiontime' => $actualtime->fields['actual_actiontime'],
                            ];
                            $source->add($input_source);
                        }
                        $actualtime->update($input);
                    }
                }
            }
        }

        if ($config->autoUpdateDuration()) {
            $task_id = $_POST["items_id"];
            $itemtype = $_POST["itemtype"];
            $task = new $itemtype();
            $task->getFromDB($task_id);
            $input = [
                'id' => $task_id,
            ];

            /** @var array $CFG_GLPI */
            global $CFG_GLPI;
            $totaltime = PluginActualtimeTask::totalEndTime($task_id, $itemtype);
            $step = $CFG_GLPI["time_step"];
            $ceil = ceil($totaltime / ($step * MINUTE_TIMESTAMP)) * ($step * MINUTE_TIMESTAMP);
            if (isset($task->fields['actiontime'])) {
                $input['actiontime'] = $ceil;
            } else {
                $input['effective_duration'] = $ceil;
            }
            $task->update($input);
        }
    }
}
Html::back();
