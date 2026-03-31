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
 * along withOneTimeSecret. If not, see <http://www.gnu.org/licenses/>.
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

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
class PluginActualtimeSourcetimer extends CommonDBTM
{
    public static $rightname = 'plugin_actualtime_sourcetimer';
    public const TICKET     = 1024;
    public const CHANGE     = 2048;
    public const PROJECT    = 4096;
    public const PROBLEM    = 8192;

    /**
     * getRights
     *
     * @param  mixed $interface
     * @return array
     */
    public function getRights($interface = 'central'): array
    {
        if ($interface == 'central') {
            return [
                self::TICKET    => __('Modify tickets', 'actualtime'),
                self::CHANGE    => __('Modify changes', 'actualtime'),
                self::PROJECT   => __('Modify projects', 'actualtime'),
                self::PROBLEM   => __('Modify problems', 'actualtime'),
            ];
        }
        return [];
    }

    /**
     * checkItemtypeRight
     *
     * @param  mixed $itemtype
     * @return bool
     */
    public static function checkItemtypeRight($itemtype): bool
    {
        switch ($itemtype) {
            case 'TicketTask':
                return Session::haveRight(self::$rightname, self::TICKET);
            case 'ChangeTask':
                return Session::haveRight(self::$rightname, self::CHANGE);
            case 'ProblemTask':
                return Session::haveRight(self::$rightname, self::PROBLEM);
            case 'ProjectTask':
                return Session::haveRight(self::$rightname, self::PROJECT);
            default:
                return false;
        }
    }

    /**
     * canModify
     *
     * @param  mixed $itemtype
     * @param  mixed $items_id
     * @return bool
     */
    public static function canModify($itemtype, $items_id): bool
    {
        /** @var \DBmysql $DB */
        global $DB;

        switch ($itemtype) {
            case 'TicketTask':
            case 'ChangeTask':
            case 'ProblemTask':
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
                                'is_finished' => 1,
                            ],
                        ],
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

    /**
     * postShowItem
     *
     * @param  mixed $params
     * @return void
     */
    public static function postShowItem($params): void
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
        $count = countElementsInTable(
            PluginActualtimeTask::getTable(),
            [
                'items_id'  => $item->getID(),
                'itemtype'  => $itemtype,
                'NOT'       => ['actual_end' => null],
            ],
        );
        if ($count == 0) {
            return;
        }

        if (!self::canModify($itemtype, $item->getID())) {
            return;
        }

        $task_id = $item->getID();

        $html = "<div class='dropdown ms-2'>";
        $html .= "<a href='#' data-bs-toggle='modal' data-bs-target='#add_time_{$task_id}'>";
        $html .= "<span class='fas fa-calendar-plus control_item' title='" . __("Modify timers", "actualtime");
        $html .= "'></span>";
        $html .= "</a></div>";
        $script = <<<JAVASCRIPT
$(document).ready(function() {
    $("div[data-itemtype='{$itemtype}'][data-items-id='{$task_id}'] div.timeline-item-buttons").prepend("{$html}");
});
JAVASCRIPT;
        echo Html::scriptBlock($script);
        echo Ajax::createIframeModalWindow(
            'add_time_' . $task_id,
            Plugin::getWebDir('actualtime') . "/ajax/changetimer.php?itemtype=" . $itemtype . "&task_id=" . $task_id,
            [
                'reloadonclose' => true,
                'title'         => __('Modify timers', 'actualtime'),
                'height'        => '700',
            ],
        );
    }

    /**
     * modalForm
     *
     * @param  string $itemtype
     * @param  int $items_id
     * @return void
     */
    public function modalForm(string $itemtype, int $items_id): void
    {
        $config = PluginActualtimeConfig::getInstance();
        $actualtimes = [];
        $userdata = [];
        $duration = 0;

        foreach (self::getActualtimes($itemtype, $items_id) as $rows_id => $data) {
            if (empty($userdata)) {
                $href = User::getFormURLWithID($data['users_id']);
                $username = User::getFriendlyNameById($data['users_id']);
                $userdata['user'] = "<a href=\"{$href}\" target=\"_blank\">{$username}</a>";
            }
            $actualtimes[$rows_id] = $data;
        }

        $max_hour = $config->fields['task_limit'];
        if ($max_hour == 0) {
            $max_hour = 24;
        }

        $previous_row = 0;
        foreach ($actualtimes as $rows_id => $data) {
            $data['rand'] = mt_rand();
            $data['min_date'] = $data['actual_begin'];
            $max_seconds = $max_hour * 60 * 60 - $duration;
            $limit = strtotime($data['min_date'] . " + {$max_seconds} seconds");
            $a_limit = date('Y-m-d H:i:s', $limit);
            if (isset($actualtimes[$previous_row])) {
                $max_date = $data['actual_begin'];
                if ($max_date > $a_limit) {
                    $max_date = $a_limit;
                }
                $actualtimes[$previous_row]['max_date'] = $max_date;
                $previous_max_seconds = strtotime($max_date) - strtotime($actualtimes[$previous_row]['actual_end']);
                $actualtimes[$previous_row]['limit'] = Html::timestampToString($previous_max_seconds);
            }
            $data['max_date'] = $a_limit;
            $data['limit'] = Html::timestampToString($max_seconds);
            $data['stamp_actiontime'] = Html::timestampToString($data['actual_actiontime']);
            $actualtimes[$rows_id] = $data;
            $duration += (int) $data['actual_actiontime'];
            $previous_row = $rows_id;
        }
        $userdata['duration'] = Html::timestampToString($duration);
        $userdata['limit'] = Html::timestampToString($max_hour * 60 * 60);

        $template = "@actualtime/forms/modify_timers.html.twig";
        TemplateRenderer::getInstance()->display($template, [
            'itemtype'      => $itemtype,
            'items_id'      => $items_id,
            'actualtimes'   => $actualtimes,
            'userdata'      => $userdata,
            'target'        => $this->getFormURL(),
        ]);
    }

    /**
     * @param string $itemtype
     * @param int $items_id
     *
     * @return \DBmysqlIterator
     */
    private static function getActualtimes(string $itemtype, int $items_id): \DBmysqlIterator
    {
        /** @var \DBmysql $DB */
        global $DB;

        $query = [
            'FROM' => PluginActualtimeTask::getTable(),
            'WHERE' => [
                'items_id' => $items_id,
                'itemtype' => $itemtype,
                'NOT' => ['actual_end' => null],
            ],
        ];

        return $DB->request($query);
    }

    /**
     * @param string $itemtype
     * @param int $items_id
     *
     * @return array<int, array{min_date: string, max_date: string}>
     */
    public static function getTaskLimits(string $itemtype, int $items_id): array
    {
        $config = PluginActualtimeConfig::getInstance();
        $limits = [];
        $duration = 0;
        $max_hour = $config->fields['task_limit'];
        if ($max_hour == 0) {
            $max_hour = 24;
        }

        $actualtimes = [];
        foreach (self::getActualtimes($itemtype, $items_id) as $rows_id => $data) {
            $actualtimes[$rows_id] = $data;
        }

        $previous_row = 0;
        foreach ($actualtimes as $rows_id => $data) {
            $data['min_date'] = $data['actual_begin'];
            $max_seconds = $max_hour * 60 * 60 - $duration;
            $limit = strtotime($data['min_date'] . " + {$max_seconds} seconds");
            $a_limit = date('Y-m-d H:i:s', $limit);
            if (isset($actualtimes[$previous_row])) {
                $max_date = $data['actual_begin'];
                if ($max_date > $a_limit) {
                    $max_date = $a_limit;
                }
                $limits[$previous_row]['max_date'] = $max_date;
            }
            $duration += (int) $data['actual_actiontime'];
            $previous_row = $rows_id;
            // set min and max date, if the post date is between the limits it's ok
            $limits[$rows_id]['min_date'] = $data['actual_begin'];
            $limits[$rows_id]['max_date'] = $a_limit;
        }

        return $limits;
    }

    /**
     * install
     *
     * @param  Migration $migration
     * @return void
     */
    public static function install(Migration $migration): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $default_charset    = DBConnection::getDefaultCharset();
        $default_collation  = DBConnection::getDefaultCollation();
        $default_key_sign   = DBConnection::getDefaultPrimaryKeySignOption();

        $table = self::getTable();
        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");
            $query = "CREATE TABLE IF NOT EXISTS $table (
                `id` INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `plugin_actualtime_tasks_id` INT {$default_key_sign} NOT NULL DEFAULT '0',
                `users_id` INT {$default_key_sign} NOT NULL DEFAULT '0',
                `source_end` TIMESTAMP NULL DEFAULT NULL,
                `source_actiontime` INT {$default_key_sign} NOT NULL DEFAULT 0,
                `date_creation` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `plugin_actualtime_tasks_id` (`plugin_actualtime_tasks_id`),
                KEY `users_id` (`users_id`)
            ) ENGINE=InnoDB  DEFAULT CHARSET={$default_charset}
            COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);
        }
    }
}
