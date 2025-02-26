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

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

/** @var array $CFG_GLPI */
global $CFG_GLPI;

if (isset($_POST["action"])) {
    $plugin = new Plugin();
    $task_id = $_POST["task_id"];
    $itemtype = $_POST["itemtype"];
    $config = new PluginActualtimeConfig();
    switch ($_POST["action"]) {
        case 'start':
            $result = PluginActualtimeTask::startTimer($_POST["task_id"], $itemtype, PluginActualtimeTask::WEB);
            echo json_encode($result);
            break;
        case 'end':
            $result = PluginActualtimeTask::stopTimer($_POST["task_id"], $itemtype, PluginActualtimeTask::WEB);
            echo json_encode($result);
            break;
        case 'pause':
            $result = PluginActualtimeTask::pauseTimer($_POST["task_id"], $itemtype, PluginActualtimeTask::WEB);
            echo json_encode($result);
            break;
        case 'count':
            echo abs(PluginActualtimeTask::totalEndTime($task_id, $itemtype));
            break;
    }
} elseif (isset($_GET["footer"])) {
   // Base function for all general stuff in javascript
   // Translations
    $result = [];
    $result['rand']         = mt_rand();
   //TRANS: d is a symbol for days in a time (displays: 3d)
    $result['symb_d']       = __("%dd", "actualtime");
    $result['symb_day']     = _n("%d day", "%d days", 1);
    $result['symb_days']    = _n("%d day", "%d days", 2);
   //TRANS: h is a symbol for hours in a time (displays: 3h)
    $result['symb_h']       = __("%dh", "actualtime");
    $result['symb_hour']    = _n("%d hour", "%d hours", 1);
    $result['symb_hours']   = _n("%d hour", "%d hours", 2);
   //TRANS: min is a symbol for minutes in a time (displays: 3min)
    $result['symb_min']     = __("%dmin", "actualtime");
    $result['symb_minute']  = _n("%d minute", "%d minutes", 1);
    $result['symb_minutes'] = _n("%d minute", "%d minutes", 2);
   //TRANS: s is a symbol for seconds in a time (displays: 3s)
    $result['symb_s']       = __("%ds", "actualtime");
    $result['symb_second']  = _n("%d second", "%d seconds", 1);
    $result['symb_seconds'] = _n("%d second", "%d seconds", 2);
    $result['text_warning'] = __('Warning');
    $result['text_pause']   = "<i class='fa-solid fa-pause'></i>";
    $result['text_restart'] = "<i class='fa-solid fa-forward'></i>";
    $result['text_done']    = __('Done');
   // Current user active task. Data to timer popup
    $config = new PluginActualtimeConfig();
    if ($config->showTimerPopup()) {
       // popup_div exists only if settings allow display pop-up timer
        $popup_div = "<div id='actualtime_popup'>" . __("Timer started on", 'actualtime');
        $popup_div .= " <a onclick='window.actualTime.showTaskForm(event)' href='%l'>%n #%t</a> -> <span></span></div>";
        $result['popup_div'] = $popup_div;
        $task_id = PluginActualtimeTask::getTask(Session::getLoginUserID());
        if ($task_id) {
           // Only if timer is active
            $result['task_id'] = $task_id;
            $result['itemtype'] = PluginActualtimeTask::getItemtype(Session::getLoginUserID());
            $task = getItemForItemtype($result['itemtype']);
            if (is_a($task, CommonDBChild::class, true)) {
                $parent = getItemForItemtype($task::$itemtype);
            } else {
                $parent = getItemForItemtype($task->getItilObjectItemType());
            }
            $result['parent_id'] = PluginActualtimeTask::getParent(Session::getLoginUserID());
            $parent->getFromDB($result['parent_id']);
            $result['link'] = $parent->getLinkURL();
            $result['name'] = $parent->getTypeName(1);
            $result['time'] = abs(PluginActualtimeTask::totalEndTime($task_id, $result['itemtype']));
        }
    }
    echo json_encode($result);
} else {
   // For modal windows
    $parts = parse_url($_SERVER['REQUEST_URI']);
    $query = [];
    if (isset($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    if (isset($query['showform'])) {
        $task_id = PluginActualtimeTask::getTask(Session::getLoginUserID());
        $itemtype = PluginActualtimeTask::getItemtype(Session::getLoginUserID());
        if ($task_id == 0 || $itemtype == '') {
            exit;
        }
        $item = getItemForItemtype($itemtype);
        $item->getFromDB($task_id);
        $rand = mt_rand();
        if (is_a($item, CommonDBChild::class, true)) {
            $parent = getItemForItemtype($item::$itemtype);
        } else {
            $parent = getItemForItemtype($item->getItilObjectItemType());
        }
        $parent->getFromDB(PluginActualtimeTask::getParent(Session::getLoginUserID()));
        $options['parent'] = $parent;
        echo  "<div class='modal-header'>";
        echo "<h4 class='modal-title'>" . __('Update of a task') . "</h4>";
        echo "<button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='" . __("Close") . "'>";
        echo "</button>";
        echo "</div>";
        echo "<div class='modal-body'>";
        echo "<div class='center'>";
        $redirect = strtolower($parent->getType());
        $redirect .= "_" . PluginActualtimeTask::getParent(Session::getLoginUserID());
        $url = $CFG_GLPI['url_base'] . "/index.php" . "?redirect=" . $redirect . "&noAUTO=1";
        echo "<a class='btn btn-outline-secondary' href='" . urldecode($url) . "'>";
        echo "<i class='ti ti-eye'></i><span>" . __s("View this item in its context") . "</span></a>";
        echo "</div><hr>";
        $item->showForm($task_id, $options);
        echo "</div>";
    }
}
