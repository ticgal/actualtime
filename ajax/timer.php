<?php

/**
 * -------------------------------------------------------------------------
 * ActualTime plugin for GLPI
 * Copyright (C) 2018-2026 by the TICGAL Team.
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
 * along with ActualTime. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @package   ActualTime
 * @author    the TICGAL team
 * @copyright Copyright (c) 2018-2026 TICGAL team
 * @license   AGPL License 3.0 or (at your option) any later version
 *            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @link      https://www.tic.gal/
 * @since     2018
 * -------------------------------------------------------------------------
 */

use Glpi\Application\View\TemplateRenderer;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\RichText\UserMention;
use GlpiPlugin\Actualtime\Config;
use GlpiPlugin\Actualtime\Task;

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

if (Session::getLoginUserID() === false || !isset($_SESSION['glpiactiveprofile'])) {
    http_response_code(204);
    exit;
}

/** @var \Glpi\Config\ConfigContainer $CFG_GLPI */
global $CFG_GLPI;
if (isset($_POST["action"])) {
    $task_id = (int) ($_POST["task_id"] ?? 0);
    $itemtype = $_POST["itemtype"] ?? '';
    if ($task_id <= 0 || !Task::isAllowedItemtype($itemtype)) {
        http_response_code(400);
        exit;
    }
    switch ($_POST["action"]) {
        case 'start':
            $result = Task::startTimer($task_id, $itemtype, Task::WEB);
            echo json_encode($result);
            break;
        case 'end':
            $result = Task::stopTimer($task_id, $itemtype, Task::WEB);
            echo json_encode($result);
            break;
        case 'pause':
            $result = Task::pauseTimer($task_id, $itemtype, Task::WEB);
            echo json_encode($result);
            break;
        case 'count':
            if (Task::getAuthorizedTask($itemtype, $task_id, READ) === null) {
                http_response_code(403);
                exit;
            }
            echo abs(Task::totalEndTime($task_id, $itemtype));
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
    $result['text_pause']   = "<i class='ti ti-player-pause'></i>";
    $result['text_restart'] = "<i class='ti ti-player-track-next'></i>";
    $result['text_done']    = __('Done');
    // Current user active task. Data to timer popup
    $config = Config::getInstance();
    if ($config->showTimerPopup()) {
        // popup_div exists only if settings allow display pop-up timer
        $popup_div = "<div id='actualtime_popup'>" . __("Timer started on", 'actualtime');
        $popup_div .= " <a onclick='window.actualTime.showTaskForm(event)' href='%l'>%n #%t</a> -> <span></span></div>";
        $result['popup_div'] = $popup_div;
        $task_id = Task::getTask(Session::getLoginUserID());
        if ($task_id) {
            // Only if timer is active
            $result['task_id'] = $task_id;
            $result['itemtype'] = Task::getItemtype(Session::getLoginUserID());
            $task = getItemForItemtype($result['itemtype']);
            $parent = Task::getParentItem($task);
            $result['parent_id'] = Task::getParent(Session::getLoginUserID());
            $parent->getFromDB($result['parent_id']);
            $result['link'] = $parent->getLinkURL();
            $result['name'] = $parent->getTypeName(1);
            $result['time'] = abs(Task::totalEndTime($task_id, $result['itemtype']));
        }
    }
    echo json_encode($result);
} elseif (isset($_GET['showform'])) {
    // For modal windows
    $task_id = Task::getTask(Session::getLoginUserID());
    $itemtype = Task::getItemtype(Session::getLoginUserID());
    if ($task_id == 0 || $itemtype == '') {
        exit;
    }
    $item = getItemForItemtype($itemtype);
    if (!$item->can($task_id, READ)) {
        throw new AccessDeniedHttpException();
    }
    $parent = Task::getParentItem($item);
    $parent_id = Task::getParent(Session::getLoginUserID());
    if ($parent === null || !$parent->getFromDB($parent_id)) {
        exit;
    }
    echo  "<div class='modal-header'>";
    echo "<h4 class='modal-title'>" . __s('Update of a task') . "</h4>";
    echo "<button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='" . __s("Close") . "'>";
    echo "</button>";
    echo "</div>";
    echo "<div class='modal-body'>";
    echo "<div class='center'>";
    $redirect = strtolower($parent->getType()) . "_" . $parent_id;
    $url = $CFG_GLPI['url_base'] . "/index.php" . "?redirect=" . $redirect . "&noAUTO=1";
    echo "<a class='btn btn-outline-secondary' href='" . htmlescape($url) . "'>";
    echo "<i class='ti ti-eye'></i><span>" . __s("View this item in its context") . "</span></a>";
    echo "</div><hr>";
    if ($item instanceof CommonITILTask && $parent instanceof CommonITILObject) {
        // Same context as the core timeline (ajax/timeline.php), required by form_task.html.twig
        TemplateRenderer::getInstance()->display('components/itilobject/timeline/form_task.html.twig', [
            'item'               => $parent,
            'subitem'            => $item,
            'mention_options'    => UserMention::getMentionOptions($parent),
            'has_pending_reason' => PendingReason_Item::getForItem($parent) !== false,
            'params'             => ['parent' => $parent],
        ]);
    } else {
        // Project task
        $item->showForm($task_id, ['parent' => $parent]);
    }
    echo "</div>";
}
