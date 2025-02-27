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

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

include_once('config.class.php');

class PluginActualtimeTask extends CommonDBTM
{
    public static $rightname = 'task';
    public const AUTO       = 1;
    public const WEB        = 2;
    public const ANDROID    = 3;

    /**
     * {@inheritdoc}
     */
    public static function getTypeName($nb = 0): string
    {
        return PLUGIN_ACTUALTIME_NAME;
    }

    /**
     * {@inheritdoc}
     */
    public static function rawSearchOptionsToAdd(): array
    {
        $tab['actualtime'] = ['name' => PLUGIN_ACTUALTIME_NAME];

        $tab['7000'] = [
            'table'             => self::getTable(),
            'field'             => 'actual_actiontime',
            'name'              => __('Total duration'),
            'datatype'          => 'specific',
            'additionalfields'  => ['itemtype'],
            'type'              => 'total',
            'joinparams'        => [
                'beforejoin' => [
                    'table' => 'glpi_tickettasks',
                    'additionalfields' => ['itemtype'],
                    'joinparams' => [
                        'jointype' => 'child'
                    ]
                ],
                'jointype' => 'child',
            ]
        ];

        $tab['7001'] = [
            'table'         => self::getTable(),
            'field'         => 'actual_actiontime',
            'name'          => __("Duration Diff", "actiontime"),
            'datatype'      => 'specific',
            'type'          => 'diff',
            'joinparams'    => [
                'beforejoin' => [
                    'table' => 'glpi_tickettasks',
                    'additionalfields' => ['itemtype'],
                    'joinparams' => [
                        'jointype' => 'child'
                    ]
                ],
                'jointype' => 'child',
            ]
        ];

        $tab['7002'] = [
            'table'         => self::getTable(),
            'field'         => 'actual_actiontime',
            'name'          => __("Duration Diff", "actiontime") . " (%)",
            'datatype'      => 'specific',
            'type'          => 'diff%',
            'joinparams'    => [
                'beforejoin' => [
                    'table' => 'glpi_tickettasks',
                    'additionalfields' => ['itemtype'],
                    'joinparams' => [
                        'jointype' => 'child'
                    ]
                ],
                'jointype' => 'child',
            ]
        ];

        return $tab;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSpecificValueToDisplay($field, $values, array $options = []): string
    {
        /** @var \DBmysql $DB */
        global $DB;
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        switch ($field) {
            case 'actual_actiontime':
                $actual_totaltime = 0;
                $parent = getItemForItemtype($options['searchopt']['parent']);
                $parent->getFromDB($options['raw_data']['id']);
                $itemtype = $parent->getTaskClass();
                $ttask = $itemtype::getTable();
                $total_time = $parent->getField('actiontime');
                $query = [
                    'SELECT' => [
                        $ttask . '.id',
                    ],
                    'FROM' => $ttask,
                    'WHERE' => [
                        $parent->getForeignKeyField() => $options['raw_data']['id'],
                    ]
                ];
                foreach ($DB->request($query) as $id => $row) {
                    $actual_totaltime += self::totalEndTime($row['id'], $itemtype);
                }
                switch ($options['searchopt']['type']) {
                    case 'diff':
                        $diff = $total_time - $actual_totaltime;
                        return Html::timestampToString($diff);

                    case 'diff%':
                        if ($total_time == 0) {
                            $diffpercent = 0;
                        } else {
                            $diffpercent = 100 * ($total_time - $actual_totaltime) / $total_time;
                        }
                        return round($diffpercent, 2) . "%";

                    case 'task':
                        $query = [
                        'SELECT' => [
                            'actual_actiontime'
                        ],
                        'FROM' => self::getTable(),
                        'WHERE' => [
                            'items_id' => $options['raw_data']['id'],
                            'itemtype' => $itemtype,
                        ]
                        ];
                        $task_time = 0;
                        foreach ($DB->request($query) as $actiontime) {
                            $task_time += $actiontime["actual_actiontime"];
                        }
                        return Html::timestampToString($task_time);
                }
                return Html::timestampToString($actual_totaltime);
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /**
     * postForm
     *
     * @param  mixed $params
     * @return void
     */
    public static function postForm($params): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $item = $params['item'];
        $itemtype = $item->getType();

        $config = new PluginActualtimeConfig();

        switch ($item->getType()) {
            case ChangeTask::class:
            case TicketTask::class:
            case ProblemTask::class:
                if ($item->getID()) {
                    $task_id = $item->getID();
                    if (is_a($item, CommonDBChild::class, true)) {
                        $parent = getItemForItemtype($item::$itemtype);
                    } else {
                        $parent = getItemForItemtype($item->getItilObjectItemType());
                    }
                    $parent_key = getForeignKeyFieldForItemType($parent::getType());
                    $rand = mt_rand();
                    $buttons = ($item->fields['users_id_tech'] == Session::getLoginUserID() && $item->can($task_id, UPDATE));
                    $disable = false;
                    if ($config->fields['planned_task'] && !is_null($item->fields['begin'])) {
                        if ($item->fields['begin'] > date("Y-m-d H:i:s")) {
                            $disable = true;
                        }
                    }
                    $time = self::totalEndTime($task_id, $itemtype);
                    $text_restart = "<i class='fa-solid fa-forward'></i>";
                    $text_pause = "<i class='fa-solid fa-pause'></i>";
                    $html = '';
                    $html_buttons = '';
                    $script = '';

                    // Only task user
                    $timercolor = 'black';
                    if ($buttons) {
                        $value1 = "<i class='fa-solid fa-play'></i>";
                        $action1 = '';
                        $color1 = 'gray';
                        $disabled1 = 'disabled';
                        $action2 = '';
                        $color2 = 'gray';
                        $disabled2 = 'disabled';

                        if ($item->getField('state') == 1 && !$disable) {
                            if (self::checkTimerActive($task_id, $itemtype)) {
                                $value1 = $text_pause;
                                $action1 = 'pause';
                                $color1 = 'orange';
                                $disabled1 = '';
                                $action2 = 'end';
                                $color2 = 'red';
                                $disabled2 = '';
                                $timercolor = 'red';
                            } else {
                                if ($time > 0) {
                                    $value1 = $text_restart;
                                    $action2 = 'end';
                                    $color2 = 'red';
                                    $disabled2 = '';
                                }

                                $action1 = 'start';
                                $color1 = 'green';
                                $disabled1 = '';
                            }
                        }

                        $button_action1 = "<button type='button' class='btn btn-primary m-2'";
                        $button_action1 .= " id='actualtime_button_{$task_id}_1_{$rand}'";
                        $button_action1 .= " action='$action1'";
                        $button_action1 .= " style='background-color:$color1;color:white'";
                        $button_action1 .= " $disabled1";
                        $button_action1 .= "><span class='d-none d-md-block'>$value1</span>";
                        $button_action1 .= "</button>";

                        $html_buttons .= $button_action1;

                        $button_action2 = "<button type='button' class='btn btn-primary m-2'";
                        $button_action2 .= " id='actualtime_button_{$task_id}_2_{$rand}'";
                        $button_action2 .= " action='$action2'";
                        $button_action2 .= " style='background-color:$color2;color:white'";
                        $button_action2 .= " $disabled2";
                        $button_action2 .= "><span class='d-none d-md-block'><i class='fa-solid fa-stop'></i></span>";
                        $button_action2 .= "</button>";

                        $html_buttons .= $button_action2;

                        // Only task user have buttons
                        $script .= <<<JAVASCRIPT
$(document).ready(function() {
    $("#actualtime_button_{$task_id}_1_{$rand}").click(function(event) {
        window.actualTime.pressedButton($task_id, "{$itemtype}", $(this).attr('action'));
    });

    $("#actualtime_button_{$task_id}_2_{$rand}").click(function(event) {
        window.actualTime.pressedButton($task_id, "{$itemtype}", $(this).attr('action'));
    });
});
JAVASCRIPT;
                    }

                    // Task user (always) or Standard interface (always)
                    // or Helpdesk inteface (only if config allows)
                    if (
                        $buttons
                        || (Session::getCurrentInterface() == "central")
                        || $config->showInHelpdesk()
                    ) {
                        $html .= "<div class='row center'>";
                        $html .= "<div class='col-12 col-md-7'>";
                        $html .= "<div class='b'>" . __("Actual Duration", 'actualtime') . "</div>";
                        $html .= "<div id='actualtime_timer_{$task_id}_{$rand}' style='color:{$timercolor}'></div>";
                        $html .= "</div>";
                        $html .= "<div class='col-12 col-md-5'>";
                        $html .= "<div class='btn-group'>";
                        $html .= $html_buttons;
                        $html .= "</div>";
                        $html .= "</div>";
                        $html .= "</div>";
                        $html .= "<div class='row center b'>";
                        $html .= "<div class='col-12 col-md-7'>" . __("Start date") . "</div>";
                        $html .= "<div class='col-12 col-md-5'>" . __("Partial actual duration", 'actualtime') . "</div>";
                        $html .= "</div>";

                        $html .= "<div id='actualtime_segment_{$task_id}_{$rand}'>";
                        $html .= self::getSegment($item->getID(), $itemtype);
                        $html .= "</div>";

                        echo $html;

                        // Finally, fill the actual total time in all timers
                        $script .= <<<JAVASCRIPT
$(document).ready(function() {
    window.actualTime.fillCurrentTime($task_id, $time);
});
JAVASCRIPT;
                        echo Html::scriptBlock($script);
                    }

                    $submit_buton = "<button id='actualtime_addme_{$rand}' form='actualtime_form_addme_{$rand}' type='submit' name='update' class='btn btn-icon btn-sm btn-ghost-secondary float-end mt-1 ms-1'><i class='fas fa-male'></i></button>";
                    $form = "<form method='POST' action='/front/" . strtolower($itemtype) . ".form.php' class='d-none' id='actualtime_form_addme_{$rand}' data-submit-once>";
                    $form .= "<input type='hidden' name='id' value='{$task_id}'";
                    $form .= "<input type='hidden' name='itemtype' value='" . $parent::getType() . "'>";
                    $form .= "<input type='hidden' name='users_id_tech' value='" . Session::getLoginUserID() . "'>";
                    $form .= "<input type='hidden' name='{$parent_key}' value='" . $item->fields[$parent_key] . "'>";
                    $form .= "<input type='hidden' name='_glpi_csrf_token' value='" . Session::getNewCSRFToken() . "'>";
                    $form .= "</form>";
                    $script = <<<JAVASCRIPT
$(document).ready(function() {
    if($("#actualtime_addme_{$rand}").length==0){
        $("div[data-itemtype='{$itemtype}'][data-items-id='{$task_id}'] div.itiltask form select[name='users_id_tech']").parent().append("{$submit_buton}");
    }
    if($("#actualtime_form_addme_{$rand}").length==0){
        $("#itil-object-container").parent().append("{$form}");
    }
});
JAVASCRIPT;
                    echo Html::scriptBlock($script);
                } else {
                   //echo Html::scriptBlock('');
                    $div = "<div id='actualtime_autostart' class='form-field row col-12 mb-2'><label class='col-form-label col-2 text-xxl-end' for='autostart'><i class='fas fa-stopwatch fa-fw me-1' title='" . __('Autostart') . "'></i></label><div class='col-10 field-container'><label class='form-check form-switch mt-2'><input type='hidden' name='autostart' value='0'><input type='checkbox' id='autostart' name='autostart' value='1' class='form-check-input'></label></div></div>";
                    $script = <<<JAVASCRIPT
$(document).ready(function() {
    if($("#actualtime_autostart").length==0){
        $("#new-{$itemtype}-block div.itiltask form > div.row div.row:first").append("{$div}");
    }
});
JAVASCRIPT;
                    echo Html::scriptBlock($script);
                }
                break;
            case 'ProjectTask':
                if ($item->getID()) {
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

                    $task_id = $item->getID();
                    $rand = mt_rand();
                    $buttons = $item->canUpdateItem();
                    $disable = false;
                    if ($config->fields['planned_task'] && !is_null($item->fields['real_start_date'])) {
                        if ($item->fields['real_start_date'] > date("Y-m-d H:i:s")) {
                            $disable = true;
                        }
                    }
                    $time = self::totalEndTime($task_id, $itemtype);
                    $text_restart = "<i class='fa-solid fa-forward'></i>";
                    $text_pause = "<i class='fa-solid fa-pause'></i>";
                    $html = '';
                    $html_buttons = '';
                    $script = '';

                    // Only task user
                    $timercolor = 'black';
                    if ($buttons) {
                        $value1 = "<i class='fa-solid fa-play'></i>";
                        $action1 = '';
                        $color1 = 'gray';
                        $disabled1 = 'disabled';
                        $action2 = '';
                        $color2 = 'gray';
                        $disabled2 = 'disabled';

                        if (!in_array($item->getField('projectstates_id'), $finished_states_ids) && !$disable) {
                            if (self::checkTimerActive($task_id, $itemtype)) {
                                $value1 = $text_pause;
                                $action1 = 'pause';
                                $color1 = 'orange';
                                $disabled1 = '';
                                $action2 = 'end';
                                $color2 = 'red';
                                $disabled2 = '';
                                $timercolor = 'red';
                            } else {
                                if ($time > 0) {
                                    $value1 = $text_restart;
                                    $action2 = 'end';
                                    $color2 = 'red';
                                    $disabled2 = '';
                                }

                                $action1 = 'start';
                                $color1 = 'green';
                                $disabled1 = '';
                            }
                        }

                        $html_buttons .= "<button type='button' class='btn btn-primary m-2' id='actualtime_button_{$task_id}_1_{$rand}' action='$action1' style='background-color:$color1;color:white' $disabled1><span class='d-none d-md-block'>$value1</span></button>";
                        $html_buttons .= "<button type='button' class='btn btn-primary m-2' id='actualtime_button_{$task_id}_2_{$rand}' action='$action2' style='background-color:$color2;color:white' $disabled2><span class='d-none d-md-block'><i class='fa-solid fa-stop'></i></span></button>";

                       // Only task user have buttons
                        $script .= <<<JAVASCRIPT
$(document).ready(function() {
    $("#actualtime_button_{$task_id}_1_{$rand}").click(function(event) {
    window.actualTime.pressedButton($task_id, "{$itemtype}", $(this).attr('action'));
    });

    $("#actualtime_button_{$task_id}_2_{$rand}").click(function(event) {
    window.actualTime.pressedButton($task_id, "{$itemtype}", $(this).attr('action'));
    });
});
JAVASCRIPT;
                    }

                    // Task user (always) or Standard interface (always)
                    // or Helpdesk inteface (only if config allows)
                    if (
                        $buttons
                        || (Session::getCurrentInterface() == "central")
                        || $config->showInHelpdesk()
                    ) {
                        if (PluginActualtimeSourcetimer::checkItemtypeRight($itemtype) && (countElementsInTable(PluginActualtimeTask::getTable(), ['items_id' => $task_id, 'itemtype' => $itemtype, 'NOT' => ['actual_end' => null]]) > 0) && PluginActualtimeSourcetimer::canModify($itemtype, $task_id)) {
                            $html .= "<div class='dropdown ms-2'>";
                            $html .= "<a href='#' data-bs-toggle='modal' data-bs-target='#add_time_{$task_id}'>";
                            $html .= "<span class='fas fa-calendar-plus control_item' title='" . __("Modify timers", "actualtime") . "'></span>";
                            $html .= "</a></div>";
                        }

                        $html .= "<div class='row center'>";
                        $html .= "<div class='col-12 col-md-7'>";
                        $html .= "<div class='b'>" . __("Actual Duration", 'actualtime') . "</div>";
                        $html .= "<div id='actualtime_timer_{$task_id}_{$rand}' style='color:{$timercolor}'></div>";
                        $html .= "</div>";
                        $html .= "<div class='col-12 col-md-5'>";
                        $html .= "<div class='btn-group'>";
                        $html .= $html_buttons;
                        $html .= "</div>";
                        $html .= "</div>";
                        $html .= "</div>";
                        $html .= "<div class='row center b'>";
                        $html .= "<div class='col-12 col-md-7'>" . __("Start date") . "</div>";
                        $html .= "<div class='col-12 col-md-5'>" . __("Partial actual duration", 'actualtime') . "</div>";
                        $html .= "</div>";

                        $html .= "<div id='actualtime_segment_{$task_id}_{$rand}'>";
                        $html .= self::getSegment($item->getID(), $itemtype);
                        $html .= "</div>";

                        echo $html;

                       // Finally, fill the actual total time in all timers
                        $script .= <<<JAVASCRIPT
$(document).ready(function() {
    window.actualTime.fillCurrentTime($task_id, $time);
});
JAVASCRIPT;
                        echo Html::scriptBlock($script);
                    }
                }
                break;
        }
    }

    /**
     * checkTech
     *
     * @param  mixed $task_id
     * @param  mixed $itemtype
     * @return bool
     */
    public static function checkTech($task_id, $itemtype): bool
    {
        /** @var \DBmysql $DB */
        global $DB;

        $query = [
            'FROM' => $itemtype::getTable(),
            'WHERE' => [
                'id' => $task_id,
                'users_id_tech' => Session::getLoginUserID(),
            ]
        ];
        $req = $DB->request($query);
        if ($row = $req->current()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * checkTimerActive
     *
     * @param  mixed $task_id
     * @param  mixed $itemtype
     * @return bool
     */
    public static function checkTimerActive($task_id, $itemtype): bool
    {
        /** @var \DBmysql $DB */
        global $DB;

        $query = [
            'FROM' => self::getTable(),
            'WHERE' => [
                'items_id' => $task_id,
                'itemtype' => $itemtype,
                [
                    'NOT' => ['actual_begin' => null],
                ],
                'actual_end' => null,
            ]
        ];
        $req = $DB->request($query);
        if ($row = $req->current()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * totalEndTime
     *
     * @param  mixed $task_id
     * @param  mixed $itemtype
     * @return int
     */
    public static function totalEndTime($task_id, $itemtype): int
    {
        /** @var \DBmysql $DB */
        global $DB;

        $query = [
            'FROM' => self::getTable(),
            'WHERE' => [
                'items_id' => $task_id,
                'itemtype' => $itemtype,
                [
                    'NOT' => ['actual_begin' => null],
                ],
                [
                    'NOT' => ['actual_end' => null],
                ],
            ]
        ];

        $seconds = 0;
        foreach ($DB->request($query) as $id => $row) {
            $seconds += $row['actual_actiontime'];
        }

        $querytime = [
            'FROM' => self::getTable(),
            'WHERE' => [
                'items_id' => $task_id,
                'itemtype' => $itemtype,
                [
                    'NOT' => ['actual_begin' => null],
                ],
                'actual_end' => null,
            ]
        ];

        $req = $DB->request($querytime);
        if ($row = $req->current()) {
            $seconds += (strtotime("now") - strtotime($row['actual_begin']));
        }

        return $seconds;
    }

    /**
     * checkUser
     *
     * @param  mixed $task_id
     * @param  mixed $itemtype
     * @param  mixed $user_id
     * @return bool
     */
    public static function checkUser($task_id, $itemtype, $user_id): bool
    {
        /** @var \DBmysql $DB */
        global $DB;

        $query = [
            'FROM' => self::getTable(),
            'WHERE' => [
                'items_id' => $task_id,
                'itemtype' => $itemtype,
                [
                    'NOT' => ['actual_begin' => null],
                ],
                'actual_end' => null,
                'users_id' => $user_id,
            ]
        ];
        $req = $DB->request($query);
        if ($row = $req->current()) {
            return true;
        } else {
            return false;
        }
    }

    /**
    * Check if the technician is free (= not active in any task)
    *
    * @param $user_id  Long  ID of technitian logged in
    *
    * @return Boolean (true if technitian IS NOT ACTIVE in any task)
    * (opposite behaviour from original version until 1.1.0)
    * */
    public static function checkUserFree($user_id): bool
    {
        /** @var \DBmysql $DB */
        global $DB;

        $query = [
            'FROM' => self::getTable(),
            'WHERE' => [
                [
                    'NOT' => ['actual_begin' => null],
                ],
                'actual_end' => null,
                'users_id' => $user_id,
            ]
        ];
        $req = $DB->request($query);
        if ($row = $req->current()) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * getParent
     *
     * @param  mixed $user_id
     * @return mixed
     */
    public static function getParent($user_id)
    {
        if ($task_id = self::getTask($user_id)) {
            if ($itemtype = self::getItemtype($user_id)) {
                $task = new $itemtype();
                if ($task->getFromDB($task_id)) {
                    if (is_a($task, CommonDBChild::class, true)) {
                        $parent = $task::$itemtype;
                    } else {
                        $parent = $task->getItilObjectItemType();
                    }
                    return $task->fields[getForeignKeyFieldForItemType($parent)];
                } else {
                    return false;
                }
            }
        }
        return false;
    }

    /**
     * getTask
     *
     * @param  mixed $user_id
     * @return int
     */
    public static function getTask($user_id): int
    {
        /** @var \DBmysql $DB */
        global $DB;

        $query = [
            'FROM' => self::getTable(),
            'WHERE' => [
                [
                    'NOT' => ['actual_begin' => null],
                ],
                'actual_end' => null,
                'users_id' => $user_id,
            ]
        ];
        $req = $DB->request($query);
        if ($row = $req->current()) {
            return $row['items_id'];
        } else {
            return 0;
        }
    }

    /**
     * getItemtype
     *
     * @param  mixed $user_id
     * @return string
     */
    public static function getItemtype($user_id): string
    {
        /** @var \DBmysql $DB */
        global $DB;

        $query = [
            'SELECT' => [
                'itemtype'
            ],
            'FROM' => self::getTable(),
            'WHERE' => [
                [
                    'NOT' => ['actual_begin' => null],
                ],
                'actual_end' => null,
                'users_id' => $user_id,
            ]
        ];
        $req = $DB->request($query);
        if ($row = $req->current()) {
            return $row['itemtype'];
        } else {
            return '';
        }
    }

    /**
     * getActualBegin
     *
     * @param  mixed $task_id
     * @param  mixed $itemtype
     * @return string
     */
    public static function getActualBegin($task_id, $itemtype): string
    {
        /** @var \DBmysql $DB */
        global $DB;

        $query = [
            'FROM' => self::getTable(),
            'WHERE' => [
                'items_id' => $task_id,
                'itemtype' => $itemtype,
                'actual_end' => null,
            ]
        ];
        $req = $DB->request($query);
        $row = $req->current();
        return $row['actual_begin'];
    }

    /**
     * showStats
     *
     * @param  CommonITILObject $parent
     * @return void
     */
    public static function showStats(CommonITILObject $parent): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $config = new PluginActualtimeConfig();
        if (
            (Session::getCurrentInterface() == "central")
            || $config->showInHelpdesk()
        ) {
            $total_time = $parent->getField('actiontime');
            $itemtype = $parent->getTaskClass();
            $tasktable = $itemtype::getTable();
            $parent_id = $parent->getID();
            $actual_totaltime = 0;
            $query = [
            'SELECT' => [
                $tasktable . '.id',
            ],
            'FROM' => $tasktable,
            'WHERE' => [
                $parent->getForeignKeyField() => $parent_id,
            ]
            ];
            foreach ($DB->request($query) as $id => $row) {
                $actual_totaltime += self::totalEndTime($row['id'], $itemtype);
            }
            $html = "<table class='tab_cadre_fixe'>";
            $html .= "<tr><th colspan='2'>ActualTime</th></tr>";

            $html .= "<tr class='tab_bg_2'><td>" . __("Total duration") . "</td><td>" . Html::timestampToString($total_time) . "</td></tr>";
            $html .= "<tr class='tab_bg_2'><td>ActualTime - " . __("Total duration") . "</td><td>" . Html::timestampToString($actual_totaltime) . "</td></tr>";

            $diff = $total_time - $actual_totaltime;
            if ($diff < 0) {
                $color = 'red';
            } else {
                $color = 'black';
            }
            $html .= "<tr class='tab_bg_2'><td>" . __("Duration Diff", "actiontime") . "</td><td style='color:" . $color . "'>" . Html::timestampToString($diff) . "</td></tr>";
            if ($total_time == 0) {
                $diffpercent = 0;
            } else {
                $diffpercent = 100 * ($total_time - $actual_totaltime) / $total_time;
            }
            $html .= "<tr class='tab_bg_2'><td>" . __("Duration Diff", "actiontime") . " (%)</td><td style='color:" . $color . "'>" . round($diffpercent, 2) . "%</td></tr>";

            $html .= "</table>";

            $html .= "<table class='tab_cadre_fixe'>";
            $html .= "<tr><th colspan='5'>ActualTime - " . __("Technician") . "</th></tr>";
            $html .= "<tr><th>" . __("Technician") . "</th><th>" . __("Total duration") . "</th><th>ActualTime - " . __("Total duration") . "</th><th>" . __("Duration Diff", "actiontime") . "</th><th>" . __("Duration Diff", "actiontime") . " (%)</th></tr>";

            $query = [
            'SELECT' => [
                'actiontime',
                'id',
                'users_id_tech',
            ],
            'FROM' => $tasktable,
            'WHERE' => [
                $parent->getForeignKeyField() => $parent_id,
            ],
            'ORDER' => 'users_id_tech',
            ];
            $list = [];
            foreach ($DB->request($query) as $id => $row) {
                $list[$row['users_id_tech']]['name'] = getUserName($row['users_id_tech']);
                if (isset($list[$row['users_id_tech']]['total'])) {
                    $list[$row['users_id_tech']]['total'] += $row['actiontime'];
                } else {
                    $list[$row['users_id_tech']]['total'] = $row['actiontime'];
                }
                $qtime = [
                    'SELECT' => [
                        'SUM' => 'actual_actiontime AS actual_total'
                    ],
                    'FROM' => self::getTable(),
                    'WHERE' => [
                        'items_id' => $row['id'],
                        'itemtype' => $itemtype,
                    ],
                ];
                $req = $DB->request($qtime);
                if ($time = $req->current()) {
                    $actualtotal = $time['actual_total'];
                } else {
                    $actualtotal = 0;
                }

                if (isset($list[$row['users_id_tech']]['actual_total'])) {
                    $list[$row['users_id_tech']]['actual_total'] += $actualtotal;
                } else {
                    $list[$row['users_id_tech']]['actual_total'] = $actualtotal;
                }
            }

            foreach ($list as $key => $value) {
                $html .= "<tr class='tab_bg_2'><td>" . $value['name'] . "</td>";

                $html .= "<td>" . Html::timestampToString($value['total']) . "</td>";

                $html .= "<td>" . Html::timestampToString($value['actual_total']) . "</td>";
                if (($value['total'] - $value['actual_total']) < 0) {
                    $color = 'red';
                } else {
                    $color = 'black';
                }
                $html .= "<td style='color:" . $color . "'>" . Html::timestampToString($value['total'] - $value['actual_total']) . "</td>";
                if ($value['total'] == 0) {
                    $html .= "<td style='color:" . $color . "'>0%</td></tr>";
                } else {
                    $html .= "<td style='color:" . $color . "'>" . round(100 * ($value['total'] - $value['actual_total']) / $value['total']) . "%</td></tr>";
                }
            }
            $html .= "</table>";

            $script = <<<JAVASCRIPT
$(document).ready(function(){
    $("div.dates_timelines:last").append("{$html}");
});
JAVASCRIPT;
            echo Html::scriptBlock($script);
        }
    }

    /**
     * getSegment
     *
     * @param  mixed $task_id
     * @param  mixed $itemtype
     * @return string
     */
    public static function getSegment($task_id, $itemtype): string
    {
        /** @var \DBmysql $DB */
        global $DB;

        $query = [
            'FROM' => self::getTable(),
            'WHERE' => [
                'items_id' => $task_id,
                'itemtype' => $itemtype,
                [
                    'NOT' => ['actual_begin' => null],
                ],
                [
                    'NOT' => ['actual_end' => null],
                ],
            ]
        ];
        $html = "";
        foreach ($DB->request($query) as $id => $row) {
            $html .= "<div class='row center'><div class='col-12 col-md-7'>" . $row['actual_begin'] . "</div>";
            $style = "";
            if ($row['is_modified']) {
                $style = "color: red;font-weight: bold;font-style: italic;";
            }
            $html .= "<div class='col-12 col-md-5' style='$style'>" . Html::timestampToString($row['actual_actiontime']);
            if ($row['is_modified']) {
                $source = new PluginActualtimeSourcetimer();
                $source->getFromDBByCrit([
                'plugin_actualtime_tasks_id' => $row['id']
                ]);
                $comment = __("Original end date", "actualtime") . ": " . $source->fields['source_end'] . "<br>";
                $comment .= __("Original duration", "actualtime") . ": " . Html::timestampToString($source->fields['source_actiontime']) . "<br>";
                $comment .= sprintf(__("First modification by %s", "actualtime"), getUserName($source->fields['users_id']));
                $html .= Html::showToolTip($comment, ['display' => false]);
            }
            $html .= "</div>";
            $html .= "</div>";
        }
        return $html;
    }

    /**
     * afterAdd
     *
     * @param  CommonITILTask $item
     * @return void
     */
    public static function afterAdd(CommonITILTask $item): void
    {
        if (isset($item->input['autostart']) && $item->input['autostart']) {
            if ($item->getField('state') == 1 && $item->getField('users_id_tech') == Session::getLoginUserID() && $item->fields['id']) {
                $task_id = $item->fields['id'];
                $result = self::startTimer($task_id, $item->getType(), self::WEB);
                if ($result['type'] != 'info') {
                    Session::addMessageAfterRedirect(
                        $result['message'],
                        true,
                        WARNING
                    );
                    return;
                } else {
                    Session::addMessageAfterRedirect(
                        $result['message'],
                        true,
                        INFO
                    );
                }
            }
        }
    }

    /**
     * preUpdate
     *
     * @param  CommonDBTM $item
     * @return CommonDBTM
     */
    public static function preUpdate(CommonDBTM $item): CommonDBTM
    {
        /** @var \DBmysql $DB */
        global $DB;

        $config = new PluginActualtimeConfig();
        $itemtype = $item->getType();
        if (!array_key_exists('plugin_actualtime', $item->input)) {
            if (array_key_exists('state', $item->input) && array_key_exists('state', $item->fields)) {
                if ($item->fields['state'] != $item->input['state']) {
                    if ($item->input['state'] != 1) {
                        self::stopTimer($item->input['id'], $itemtype, self::AUTO);
                        if ($config->autoUpdateDuration()) {
                            unset($item->input['actiontime']);
                        }
                    }
                }
            }
            if (array_key_exists('users_id_tech', $item->input)) {
                if ($item->input['users_id_tech'] != $item->fields['users_id_tech']) {
                    self::pauseTimer($item->input['id'], $itemtype, self::AUTO);
                    if ($config->autoUpdateDuration()) {
                        unset($item->input['actiontime']);
                    }
                }
            }
            if (array_key_exists('projectstates_id', $item->input)) {
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
                if (in_array($item->input['projectstates_id'], $finished_states_ids)) {
                    self::stopTimer($item->input['id'], $itemtype, self::AUTO);
                    if ($config->autoUpdateDuration()) {
                        unset($item->input['effective_duration']);
                    }
                }
            }
        }

        return $item;
    }

    /**
     * postShowTab
     *
     * @param  mixed $params
     * @return void
     */
    public static function postShowTab($params): void
    {
        if ($itemtype = self::getItemtype(Session::getLoginUserID())) {
            $task = getItemForItemtype($itemtype);
            if (is_a($task, CommonDBChild::class, true)) {
                $parent = getItemForItemtype($task::$itemtype);
            } else {
                $parent = getItemForItemtype($task->getItilObjectItemType());
            }
            if ($parent_id = PluginActualtimeTask::getParent(Session::getLoginUserID())) {
                $link = $parent->getFormURLWithID($parent_id);
                $name = $parent->getTypeName(1);
                $script = <<<JAVASCRIPT
$(document).ready(function(){
    window.actualTime.showTimerPopup($parent_id, '{$link}', '{$name}');
});
JAVASCRIPT;
                echo Html::scriptBlock($script);
            }
        }
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
        $config = new PluginActualtimeConfig();
        switch ($item->getType()) {
            case TicketTask::class:
            case ChangeTask::class:
            case ProblemTask::class:
                $task_id = $item->getID();
               // Auto open needs to use correct item randomic number
                $rand = $params['options']['rand'];

               // Show timer in closed task box in:
               // Standard interface (always)
               // or Helpdesk inteface (only if config allows)
                if (
                    $config->showTimerInBox() &&
                    ((Session::getCurrentInterface() == "central") ||
                    $config->showInHelpdesk())
                ) {
                    $time = self::totalEndTime($task_id, $item->getType());
                    $fa_icon = ($time > 0 ? ' fa-clock' : '');
                    $timercolor = (self::checkTimerActive($task_id, $itemtype) ? 'red' : 'black');
                    // Anchor to find correct span, even when user has no update
                    // right on status checkbox
                    $italic = '';
                    if (countElementsInTable(self::getTable(), ['items_id' => $task_id, 'itemtype' => $itemtype, 'is_modified' => 1]) > 0) {
                        $italic = 'font-style: italic;';
                    }
                    $icon = "<span class='badge text-wrap ms-1 d-none d-md-block' style='color:{$timercolor};{$italic}'><i id='actualtime_faclock_{$task_id}_{$rand}' class='fa{$fa_icon}'></i> <span id='actualtime_timer_{$task_id}_box_{$rand}'></span></span>";
                    echo "<div id='actualtime_anchor_{$task_id}_{$rand}'></div>";
                    $script = <<<JAVASCRIPT
$(document).ready(function() {
    if ($("[id^='actualtime_faclock_{$task_id}_']").length == 0) {
        $("div[data-itemtype='{$itemtype}'][data-items-id='{$task_id}'] div.card-body div.timeline-header div.creator")
            .append("{$icon}");
            if ($time > 0) {
            window.actualTime.fillCurrentTime($task_id, $time);
        }
    }
});
JAVASCRIPT;
                    echo Html::scriptBlock($script);
                }

                if ($config->autoOpenRunning() && self::checkUser($task_id, $itemtype, Session::getLoginUserID())) {
                   // New created task or user has running timer on this task
                   // Open edit window automatically
                    $parent_item = getItemForItemtype($item->getItilObjectItemType());
                    $ticket_id = $item->fields[$parent_item::getForeignKeyField()];
                    $div = "<div id='actualtime_autoEdit_{$task_id}_{$rand}' onclick='javascript:viewEditSubitem$ticket_id$rand(event, \"{$itemtype}\", $task_id, this, \"viewitem{$itemtype}$task_id$rand\")'></div>";
                    echo $div;
                    $script = <<<JAVASCRIPT
$(document).ready(function() {
    $("div[data-itemtype='{$itemtype}'][data-items-id='{$task_id}'] div.card-body a.edit-timeline-item").trigger('click');
});
JAVASCRIPT;

                    print_r(Html::scriptBlock($script));
                }

                if ($item->fields['users_id_tech'] == Session::getLoginUserID() && $item->can($task_id, UPDATE) && $item->fields['state'] > 0) {
                    $time = self::totalEndTime($task_id, $item->getType());
                    $text_restart = "<i class='fa-solid fa-forward'></i>";
                    $text_pause = "<i class='fa-solid fa-pause'></i>";
                    $value1 = "<i class='fa-solid fa-play'></i>";
                    $action1 = '';
                    $color1 = 'gray';
                    $disabled1 = 'disabled';
                    $disable = self::disableButton($item);
                    if ($item->getField('state') == 1 && !$disable['disable']) {
                        if (self::checkTimerActive($task_id, $item->getType())) {
                            $value1 = $text_pause;
                            $action1 = 'pause';
                            $color1 = 'orange';
                            $disabled1 = '';
                            $timercolor = 'red';
                        } else {
                            if ($time > 0) {
                                $value1 = $text_restart;
                            }

                            $action1 = 'start';
                            $color1 = 'green';
                            $disabled1 = '';
                        }
                    }
                    $button = "<div class='ms-auto col-auto'><button type='button' class='btn btn-icon btn-sm mt-1' id='actualtime_button_{$task_id}_1_{$rand}' action='$action1' style='background-color:$color1;color:white;width: 20px;height: 20px;' $disabled1><span class='d-none d-md-block'>$value1</span></button></div>";
                    $script = <<<JAVASCRIPT
$(document).ready(function() {
    if ($("[id^='actualtime_button_{$task_id}_1_{$rand}']").length == 0) {
        $("div[data-itemtype='{$itemtype}'][data-items-id='{$task_id}'] div.todo-list-state").append("{$button}");
    }
    $("#actualtime_button_{$task_id}_1_{$rand}").click(function(event) {
        window.actualTime.pressedButton($task_id, "{$itemtype}", $(this).attr('action'));
    });
});
JAVASCRIPT;
                    echo Html::scriptBlock($script);
                }
                break;
        }
    }

    /**
     * populatePlanning
     *
     * @param  mixed $options
     * @return array
     */
    public static function populatePlanning($options = []): array
    {
        /**
         * @var \DBmysql $DB
         * @var array $CFG_GLPI
         */
        global $DB, $CFG_GLPI;

        $default_options = [
            'genical'               => false,
            'color'                 => '',
            'event_type_color'      => '',
            'display_done_events'   => true,
        ];

        $options = array_merge($default_options, $options);
        $interv = [];

        if (
            !isset($options['begin'])
            || ($options['begin'] == 'NULL')
            || !isset($options['end'])
            || ($options['end'] == 'NULL')
        ) {
            return $interv;
        }
        if (!$options['display_done_events']) {
            return $interv;
        }

        $who      = $options['who'];
        $whogroup = $options['whogroup'];
        $begin    = $options['begin'];
        $end      = $options['end'];

        $ASSIGN = "";

        $query = [
            'FROM' => self::getTable(),
            'WHERE' => [
                'actual_begin' => ['<=', $end],
                'actual_end' => ['>=', $begin]
            ],
            'ORDER' => [
                'actual_begin ASC'
            ]
        ];

        if ($whogroup === "mine") {
            if (isset($_SESSION['glpigroups'])) {
                $whogroup = $_SESSION['glpigroups'];
            } elseif ($who > 0) {
                $whogroup = array_column(Group_User::getUserGroups($who), 'id');
            }
        }
        if ($who > 0) {
            $query['WHERE'][] = ["users_id" => $who];
        }
        if ($whogroup > 0) {
            $query['WHERE'][] = ["groups_id" => $whogroup];
        }

        foreach ($DB->request($query) as $id => $row) {
            $key = $row["actual_begin"] . "$$" . "PluginActualtimeTask" . $row["id"];
            $interv[$key]['color']            = $options['color'];
            $interv[$key]['event_type_color'] = $options['event_type_color'];
            $interv[$key]['itemtype']         = self::getType();
            $interv[$key]['id']               = $row['id'];
            $interv[$key]["users_id"]         = $row["users_id"];
            $interv[$key]["name"]             = self::getTypeName();
            $interv[$key]["content"]          = Html::timestampToString($row['actual_actiontime']);

            $task = new $row['itemtype']();
            $task->getFromDB($row['items_id']);
            if (is_a($task, CommonDBChild::class, true)) {
                $canupdate = $task->canUpdateItem();
                $parent = getItemForItemtype($task::$itemtype);
            } else {
                $canupdate = $task->canUpdateITILItem();
                $parent = getItemForItemtype($task->getItilObjectItemType());
            }
            $url_id = $task->fields[$parent->getForeignKeyField()];
            if (!$options['genical']) {
                $interv[$key]["url"] = $parent::getFormURLWithID($url_id);
            } else {
                $interv[$key]["url"] = $CFG_GLPI["url_base"] . $parent::getFormURLWithID($url_id, false);
            }
            $interv[$key]["name"] .= " - " . $parent::getTypeName(1) . " #" . $url_id . " - " . $row['items_id'];
            $interv[$key]["ajaxurl"] = $CFG_GLPI["root_doc"] . "/ajax/planning.php" .
            "?action=edit_event_form" .
            "&itemtype=" . $task->getType() .
            "&parentitemtype=" . $parent::getType() .
            "&parentid=" . $task->fields[$parent->getForeignKeyField()] .
            "&id=" . $row['items_id'] .
            "&url=" . $interv[$key]["url"];

            $interv[$key]["begin"] = $row['actual_begin'];
            $interv[$key]["end"] = $row['actual_end'];

            $interv[$key]["editable"] = $canupdate;
        }

        return $interv;
    }

    /**
     * displayPlanningItem
     *
     * @param  array $val
     * @param  mixed $who
     * @param  mixed $type
     * @param  mixed $complete
     * @return string
     */
    public static function displayPlanningItem(array $val, $who, $type = "", $complete = 0): string
    {
        $html = "<strong>" . $val["name"] . "</strong>";
        $html .= "<br><strong>" . sprintf(__('By %s'), getUserName($val["users_id"])) . "</strong>";
        $html .= "<br><strong>" . __('Start date') . "</strong> : " . Html::convdatetime($val["begin"]);
        $html .= "<br><strong>" . __('End date') . "</strong> : " . Html::convdatetime($val["end"]);
        $html .= "<br><strong>" . __('Total duration') . "</strong> : " . $val["content"];

        return $html;
    }

    /**
     * disableButton
     *
     * @param  mixed $task
     * @return array
     */
    public static function disableButton($task): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $config = new PluginActualtimeConfig();

        $result = [
            'disable' => false,
            'message' => '',
        ];
        if ($config->fields['planned_task']) {
            if (isset($task->fields['begin'])) {
                if ($task->fields['begin'] > date("Y-m-d H:i:s")) {
                    $result['disable'] = true;
                    $result['message'] = sprintf(__("You cannot start a timer because the task was scheduled for %d.", 'actualtime'), $task->fields['begin']);
                    return $result;
                }
            } elseif (isset($task->fields['real_start_date'])) {
                if ($task->fields['real_start_date'] > date("Y-m-d H:i:s")) {
                    $result['disable'] = true;
                    $result['message'] = sprintf(__("You cannot start a timer because the task was scheduled for %d.", 'actualtime'), $task->fields['begin']);
                    return $result;
                }
            }
        }

        if ($config->fields['multiple_day']) {
            $query = [
                'SELECT' => [
                    new QueryExpression(
                        "FROM_UNIXTIME(UNIX_TIMESTAMP(" . $DB->quoteName("actual_end") . "),'%Y-%m-%d') AS date"
                    ),
                ],
                'FROM' => self::getTable(),
                'WHERE' => [
                    'items_id'  => $task->getID(),
                    'itemtype'  => $task->getType(),
                    'NOT'       => ['actual_end' => null],
                ]
            ];
            $req = $DB->request($query);
            if ($row = $req->current()) {
                if ($row['date'] < date("Y-m-d")) {
                    $result['disable'] = true;
                    $result['message'] = __("You cannot add a timer on a different day.", 'actualtime');
                    return $result;
                }
            }
        }

        return $result;
    }

    /**
     * startTimer
     *
     * @param  mixed $task_id
     * @param  mixed $itemtype
     * @param  mixed $origin
     * @return array
     */
    public static function startTimer($task_id, $itemtype, $origin = self::AUTO): array
    {
        /**
         * @var \DBmysql $DB
         * @var array $CFG_GLPI
         */
        global $DB, $CFG_GLPI;

        $result = [
            'type'   => 'warning',
        ];

        $DB->delete(
            'glpi_plugin_actualtime_tasks',
            [
                'items_id'     => $task_id,
                'itemtype'     => $itemtype,
                'actual_begin' => null,
                'actual_end'   => null,
                'users_id'     => Session::getLoginUserID(),
            ]
        );

        $plugin = new Plugin();
        if ($plugin->isActivated('tam')) {
            if (PluginTamLeave::checkLeave(Session::getLoginUserID())) {
                $result['message'] = __("Today is marked as absence you can not initialize the timer", 'tam');
                return $result;
            } else {
                $timer_id = PluginTamTam::checkWorking(Session::getLoginUserID());
                if ($timer_id == 0 || PluginTamTam::checkCurrentTamType() == 'coffee_break') {
                    $link = "<a href='" . $CFG_GLPI['root_doc'] . "/front/preference.php";
                    $link .= "?forcetab=PluginTamTam$1'>" . __("Timer has not been initialized", 'tam') . "</a>";
                    $result['message'] = $link;
                    return $result;
                }
            }
        }

        if ($plugin->isActivated('waypoint')) {
            $waypoint = new PluginWaypointWaypoint();
            $count = countElementsInTable(
                $waypoint->getTable(),
                [
                    'users_id' => Session::getLoginUserID(),
                    'date_end' => null
                ]
            );
            if ($count > 0) {
                $result['message'] = __("You are already doing a waypoint", 'waypoint');
                return $result;
            }
        }

        $task = new $itemtype();
        if (!$task->getFromDB($task_id)) {
            $result['message'] = __("Item not found");
            return $result;
        }
        if (isset($task->fields['state'])) {
            if ($task->getField('state') != 1) {
                $result['message'] = __("Task completed.");
                return $result;
            }
        } else {
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
            if (in_array($task->getField('projectstates_id'), $finished_states_ids)) {
                $result['message'] = __("Task completed.");
                return $result;
            }
        }

        if (isset($task->fields['users_id_tech'])) {
            if (Session::getLoginUserID() != $task->fields['users_id_tech']) {
                $result['message'] = __("Technician not in charge of the task", 'gappextended');
                return $result;
            }
        } else {
            if (!$task->canUpdateItem()) {
                $result['message'] = __("Technician not in charge of the task", 'gappextended');
                return $result;
            }
        }

        if (self::checkTimerActive($task_id, $itemtype)) {
            $result['message'] = __("A user is already performing the task", 'actualtime');
            return $result;
        }

        $disable = self::disableButton($task);
        if ($disable['disable']) {
            $result['message'] = $disable['message'];
            return $result;
        }

        if (!self::checkUserFree(Session::getLoginUserID())) {
            if (is_a($task, CommonDBChild::class, true)) {
                $parent = getItemForItemtype($task::$itemtype);
            } else {
                $parent = getItemForItemtype($task->getItilObjectItemType());
            }
            $parent_key = $parent->getForeignKeyField();
            $parent_id = $task->fields[$parent_key];

            $active_task_id = 0;
            $active_task_itemtype = '';
            $active_task_parent_id = 0;
            $active_task_parent_itemtype = '';
            $DB = DBConnection::getReadConnection();
            $iterator = $DB->request([
                'FROM'  => self::getTable(),
                'WHERE' => [
                    'users_id'      => Session::getLoginUserID(),
                    'actual_end'    => null,
                ],
                'LIMIT' => 1
            ]);
            if ($row = $iterator->current()) {
                // Active task found, get its id and itemtype
                $active_task_id = $row['items_id'];
                $active_task_itemtype = $row['itemtype'];
                $tmp_task = new $active_task_itemtype();
                if ($tmp_task->getFromDB($active_task_id)) {
                    // get parent id and itemtype, allowing TicketTask, ProjectTask, ChangeTask..
                    $dbu = new DbUtils();
                    $active_task_parent_itemtype = $tmp_task->getItilObjectItemType();
                    $tmp_parent_table = $dbu->getTableForItemType($active_task_parent_itemtype);
                    $tmp_key = $dbu->getForeignKeyFieldForTable($tmp_parent_table);
                    $active_task_parent_id = $tmp_task->fields[$tmp_key] ?? 0;
                }
            }

            $message = __('Error');
            if ($active_task_parent_itemtype != "" && $active_task_parent_id > 0) {
                $url = (new $active_task_parent_itemtype())->getFormURLWithID($active_task_parent_id);
                $message = sprintf(__('You are already working on %s', 'actualtime'), $active_task_parent_itemtype);
                $link = '<a href="' . $url . '">#' . $active_task_parent_id . '</a>';
                $message .= ' ' . $link;
                if ($active_task_id > 0) {
                    $message .= ' (' . __('Task') . ' #' . $active_task_id . ')';
                }
            }

            $result['message'] = $message;
            return $result;
        } else {
           // action=start, timer=off, current user is free
            $DB->insert(
                'glpi_plugin_actualtime_tasks',
                [
                    'items_id'       => $task_id,
                    'itemtype'       => $itemtype,
                    'actual_begin'   => date("Y-m-d H:i:s"),
                    'users_id'       => Session::getLoginUserID(),
                    'origin_start'   => $origin,
                ]
            );

            $timer_id = $DB->insertId();
            if (is_a($task, CommonDBChild::class, true)) {
                $parent = getItemForItemtype($task::$itemtype);
            } else {
                $parent = getItemForItemtype($task->getItilObjectItemType());
            }
            $parent_id = self::getParent(Session::getLoginUserID());
            $result = [
                'message'   => __("Timer started", 'actualtime'),
                'type'      => 'info',
                'timer_id'  => $timer_id,
                'parent_id' => $parent_id,
                'time'      => abs(self::totalEndTime($task_id, $itemtype)),
                'link'      => $parent::getFormURLWithID($parent_id),
                'name'      => $parent::getTypeName(1),
            ];

            if ($plugin->isActivated('gappextended')) {
                PluginGappextendedPush::sendActualtime(
                    self::getParent(Session::getLoginUserID()),
                    $task_id,
                    $result,
                    Session::getLoginUserID(),
                    true,
                    $parent::getType()
                );
            }
        }

        return $result;
    }

    /**
     * pauseTimer
     *
     * @param  mixed $task_id
     * @param  mixed $itemtype
     * @param  mixed $origin
     * @return array
     */
    public static function pauseTimer($task_id, $itemtype, $origin = self::AUTO): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $result = [
            'type'   => 'warning',
        ];

        $plugin = new Plugin();
        if (self::checkTimerActive($task_id, $itemtype)) {
            if (self::checkUser($task_id, $itemtype, Session::getLoginUserID())) {
                $actual_begin = self::getActualBegin($task_id, $itemtype);
                $seconds = (strtotime(date("Y-m-d H:i:s")) - strtotime($actual_begin));
                $actualtime = new self();
                $actualtime->getFromDBByCrit([
                    'items_id' => $task_id,
                    'itemtype' => $itemtype,
                    [
                        'NOT' => ['actual_begin' => null],
                    ],
                    'actual_end' => null,
                ]);
                $timer_id = $actualtime->getID();
                $DB->update(
                    'glpi_plugin_actualtime_tasks',
                    [
                        'actual_end'        => date("Y-m-d H:i:s"),
                        'actual_actiontime' => $seconds,
                        'origin_end' => $origin,
                    ],
                    [
                        'items_id' => $task_id,
                        'itemtype' => $itemtype,
                        [
                            'NOT' => ['actual_begin' => null],
                        ],
                        'actual_end' => null,
                    ]
                );

                $result = [
                    'message'  => __("Timer completed", 'actualtime'),
                    'type'     => 'info',
                    'segment'  => self::getSegment($task_id, $itemtype),
                    'time'     => abs(self::totalEndTime($task_id, $itemtype)),
                    'timer_id' => $timer_id,
                ];

                if ($plugin->isActivated('gappextended')) {
                    $task = new $itemtype();
                    $task->getFromDB($task_id);
                    if (is_a($task, CommonDBChild::class, true)) {
                        $parent = $task::$itemtype;
                    } else {
                        $parent = $task->getItilObjectItemType();
                    }
                    PluginGappextendedPush::sendActualtime(
                        $task->fields[getForeignKeyFieldForItemType($parent)],
                        $task_id,
                        $result,
                        Session::getLoginUserID(),
                        false,
                        $parent
                    );

                    $timerquery = [
                        'FROM' => PluginGappextendedTimer::getTable(),
                        'WHERE' => [
                            'items_id' => $timer_id,
                            'itemtype' => PluginActualtimeTask::getType()
                        ]
                    ];

                    foreach ($DB->request($timerquery) as $timerrow) {
                        $timer = new PluginGappextendedTimer();
                        $timer->delete(['id' => $timerrow['id']]);
                    }
                }
            } else {
                $result['message'] = __("Only the user who initiated the task can close it", 'actualtime');
            }
        } else {
            $result['message'] = __("The task had not been initialized", 'actualtime');
        }
        return $result;
    }

    /**
     * stopTimer
     *
     * @param  mixed $task_id
     * @param  mixed $itemtype
     * @param  mixed $origin
     * @return array
     */
    public static function stopTimer($task_id, $itemtype, $origin = self::AUTO): array
    {
        /**
         * @var \DBmysql $DB
         * @var array $CFG_GLPI
         */
        global $DB, $CFG_GLPI;

        $config = new PluginActualtimeConfig();
        $plugin = new Plugin();

        if (self::checkTimerActive($task_id, $itemtype)) {
            if (self::checkUser($task_id, $itemtype, Session::getLoginUserID()) || $origin == self::AUTO) {
                $actual_begin = self::getActualBegin($task_id, $itemtype);
                $seconds = (strtotime(date("Y-m-d H:i:s")) - strtotime($actual_begin));
                $actualtime = new self();
                $actualtime->getFromDBByCrit([
                    'items_id' => $task_id,
                    'itemtype' => $itemtype,
                    [
                        'NOT' => ['actual_begin' => null],
                    ],
                    'actual_end' => null,
                ]);
                $timer_id = $actualtime->getID();
                $DB->update(
                    'glpi_plugin_actualtime_tasks',
                    [
                        'actual_end'        => date("Y-m-d H:i:s"),
                        'actual_actiontime' => $seconds,
                        'origin_end'        => $origin,
                    ],
                    [
                        'items_id' => $task_id,
                        'itemtype' => $itemtype,
                        [
                            'NOT' => ['actual_begin' => null],
                        ],
                        'actual_end' => null,
                    ]
                );

                $input = [];
                $task = new $itemtype();
                $task->getFromDB($task_id);
                $input['id'] = $task_id;
                $input['state'] = 2;
                $input['plugin_actualtime'] = true;
                if ($config->autoUpdateDuration()) {
                    $totalendtime = PluginActualtimeTask::totalEndTime($task_id, $itemtype);
                    $time_step = $CFG_GLPI["time_step"] * MINUTE_TIMESTAMP;
                    $ceil = ceil($totalendtime / ($time_step)) * ($time_step);
                    if (isset($task->fields['actiontime'])) {
                        $input['actiontime'] = $ceil;
                    } else {
                        $input['effective_duration'] = $ceil;
                    }
                }
                $task->update($input);

                $result = [
                    'message'   => __("Timer completed", 'actualtime'),
                    'type'      => 'info',
                    'segment'   => PluginActualtimeTask::getSegment($task_id, $itemtype),
                    'time'      => abs(PluginActualtimeTask::totalEndTime($task_id, $itemtype)),
                    'task_time' => $task->getField('actiontime'),
                    'timer_id'  => $timer_id,
                ];

                if ($plugin->isActivated('gappextended')) {
                    if (is_a($task, CommonDBChild::class, true)) {
                        $parent = $task::$itemtype;
                    } else {
                        $parent = $task->getItilObjectItemType();
                    }
                    PluginGappextendedPush::sendActualtime(
                        $task->fields[getForeignKeyFieldForItemType($parent)],
                        $task_id,
                        $result,
                        $actualtime->fields['users_id'],
                        false,
                        $parent
                    );

                    $timerquery = [
                        'FROM' => PluginGappextendedTimer::getTable(),
                        'WHERE' => [
                            'items_id' => $timer_id,
                            'itemtype' => PluginActualtimeTask::getType()
                        ]
                    ];

                    foreach ($DB->request($timerquery) as $timerrow) {
                        $timer = new PluginGappextendedTimer();
                        $timer->delete(['id' => $timerrow['id']]);
                    }
                }
            } else {
                $result['message'] = __("Only the user who initiated the task can close it", 'actualtime');
            }
        } else {
            $task = new $itemtype();
            $task->getFromDB($task_id);
            $input['id'] = $task_id;
            $input['state'] = 2;
            $input['pending'] = 0;
            $input['plugin_actualtime'] = true;
            if ($config->autoUpdateDuration()) {
                $totalendtime = PluginActualtimeTask::totalEndTime($task_id, $itemtype);
                $time_step = $CFG_GLPI["time_step"] * MINUTE_TIMESTAMP;
                $ceil = ceil($totalendtime / ($time_step)) * ($time_step);
                if (isset($task->fields['actiontime'])) {
                    $input['actiontime'] = $ceil;
                } else {
                    $input['effective_duration'] = $ceil;
                }
            }
            $task->update($input);

            $result = [
                'message'   => __("Timer completed", 'actualtime'),
                'type'      => 'info',
                'segment'   => PluginActualtimeTask::getSegment($task_id, $itemtype),
                'time'      => abs(PluginActualtimeTask::totalEndTime($task_id, $itemtype)),
                'task_time' => $task->getField('actiontime'),
                'timer_id'  => 0,
            ];
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareInputForUpdate($input)
    {
        $input = parent::prepareInputForUpdate($input);

        if (isset($input['is_modified'])) {
            $this->getFromDB($input['id']);
            $itemtype = $this->fields['itemtype'];
            $item_id = $this->fields['items_id'];

            $task = new $itemtype();
            if ($task->getFromDB($item_id)) {
                if (is_a($task, CommonDBChild::class, true)) {
                    $parent = getItemForItemtype($task::$itemtype);
                } else {
                    $parent = getItemForItemtype($task->getItilObjectItemType());
                }
                $item_id = $task->fields[$parent->getForeignKeyField()];
                $itemtype = $parent::getType();
            }

            Log::history($item_id, $itemtype, ['0', $this->fields['actual_end'], $input['actual_end']]);
        }

        return $input;
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

        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

        $table = self::getTable();

        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");

            $query = "CREATE TABLE IF NOT EXISTS $table (
                `id` int {$default_key_sign} NOT NULL auto_increment,
                `itemtype` varchar(255) NOT NULL,
                `items_id` int {$default_key_sign} NOT NULL DEFAULT '0',
                `actual_begin` TIMESTAMP NULL DEFAULT NULL,
                `actual_end` TIMESTAMP NULL DEFAULT NULL,
                `users_id` int {$default_key_sign} NOT NULL,
                `actual_actiontime` int {$default_key_sign} NOT NULL DEFAULT 0,
                `origin_start` INT {$default_key_sign} NOT NULL,
                `origin_end` INT {$default_key_sign} NOT NULL DEFAULT 0,
                `override_begin` TIMESTAMP NULL DEFAULT NULL,
                `override_end` TIMESTAMP NULL DEFAULT NULL,
                `is_modified` TINYINT NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `item` (`itemtype`, `items_id`),
                KEY `users_id` (`users_id`)
            ) ENGINE=InnoDB  DEFAULT CHARSET={$default_charset}
            COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->doQueryOrDie($query, $DB->error());
        } else {
            $migration->changeField($table, 'tasks_id', 'tickettasks_id', 'int');
            $migration->dropField($table, 'latitude_start');
            $migration->dropField($table, 'longitude_start');
            $migration->dropField($table, 'latitude_end');
            $migration->dropField($table, 'longitude_end');
            $migration->changeField($table, 'origin_end', 'origin_end', 'int', ['value' => 0]);

            $migration->addField($table, 'override_begin', 'timestamp', ['nodefault' => true, 'null' => true]);
            $migration->addField($table, 'override_end', 'timestamp', ['nodefault' => true, 'null' => true]);

            $migration->addField($table, 'itemtype', 'varchar(255) NOT NULL', ['after' => 'id', 'update' => "'TicketTask'"]);
            $migration->addField($table, 'items_id', "int {$default_key_sign} NOT NULL DEFAULT '0'", ['after' => 'itemtype', 'update' => $DB->quoteName($table . '.tickettasks_id')]);
            $migration->addKey($table, ['itemtype', 'items_id'], 'item');
            $migration->dropField($table, 'tickettasks_id');

            $migration->addField($table, 'is_modified', 'bool');

            $migration->migrationOneTable($table);
        }
    }

    /**
     * uninstall
     *
     * @param  Migration $migration
     * @return void
     */
    public static function uninstall(Migration $migration): void
    {
        $table = self::getTable();
        $migration->displayMessage("Uninstalling $table");
        $migration->dropTable($table);
    }
}
