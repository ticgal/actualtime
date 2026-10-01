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

namespace GlpiPlugin\Actualtime;

use ChangeTask;
use CommonDBChild;
use CommonDBTM;
use CommonITILObject;
use CommonITILTask;
use DBConnection;
use DbUtils;
use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\QueryExpression;
use Html;
use Log;
use Migration;
use Planning;
use Plugin;
use ProblemTask;
use Project;
use ProjectState;
use ProjectTask;
use Session;
use TicketTask;
use User;

class Task extends CommonDBTM
{
    public static string $rightname = 'task';
    public const AUTO       = 1;
    public const WEB        = 2;
    public const ANDROID    = 3;

    public const ALLOWED_ITEMTYPES = [
        'TicketTask',
        'ChangeTask',
        'ProblemTask',
        'ProjectTask',
    ];

    /**
     * isAllowedItemtype
     *
     * @param  mixed $itemtype
     * @return bool
     */
    public static function isAllowedItemtype(mixed $itemtype): bool
    {
        return is_string($itemtype) && in_array($itemtype, self::ALLOWED_ITEMTYPES, true);
    }

    /**
     * Load a task only if the current user holds $right on it (entity included)
     *
     * @param  mixed $itemtype
     * @param  mixed $task_id
     * @param  int   $right READ or UPDATE
     * @return CommonDBTM|null null if itemtype not allowed, task not found or access denied
     */
    public static function getAuthorizedTask(mixed $itemtype, mixed $task_id, int $right): ?CommonDBTM
    {
        if (!self::isAllowedItemtype($itemtype) || !is_numeric($task_id) || (int) $task_id <= 0) {
            return null;
        }
        $task = getItemForItemtype($itemtype);
        if (!$task instanceof CommonDBTM || !$task->can((int) $task_id, $right)) {
            return null;
        }

        return $task;
    }

    /**
     * Deny timer actions not triggered by GLPI itself (AUTO) when the current user cannot update the task
     *
     * @param  mixed $task_id
     * @param  mixed $itemtype
     * @param  mixed $origin
     * @return array|null warning result to return, null if the action is allowed
     */
    private static function checkTimerAccess(mixed $task_id, mixed $itemtype, mixed $origin): ?array
    {
        if ($origin == self::AUTO && self::isAllowedItemtype($itemtype)) {
            return null;
        }
        if (self::getAuthorizedTask($itemtype, $task_id, UPDATE) === null) {
            return [
                'type'    => 'warning',
                'message' => __("You don't have permission to perform this action."),
            ];
        }

        return null;
    }

    /**
     * Get an empty instance of the parent of a task: the ITIL object of a ticket/change/problem task
     * or the project of a project task
     *
     * @param  mixed $task
     * @return CommonITILObject|Project|null
     */
    public static function getParentItem(mixed $task): CommonITILObject|Project|null
    {
        if ($task instanceof CommonITILTask) {
            return getItemForItemtype($task->getItilObjectItemType());
        }
        if ($task instanceof ProjectTask) {
            return new Project();
        }
        return null;
    }

    /**
     * Resolve the class of another plugin used by an integration (tam, waypoint, gappextended).
     * Prefers the PSR-4 class name and falls back to the legacy one.
     *
     * @param  string $plugin plugin key
     * @param  string $class  short class name, e.g. 'Tam' for GlpiPlugin\Tam\Tam / PluginTamTam
     * @return class-string|null null when the plugin is not active or the class does not exist
     */
    private static function getIntegrationClass(string $plugin, string $class): ?string
    {
        if (!Plugin::isPluginActive($plugin)) {
            return null;
        }
        foreach (['GlpiPlugin\\' . ucfirst($plugin) . '\\' . $class, 'Plugin' . ucfirst($plugin) . $class] as $classname) {
            if (class_exists($classname)) {
                return $classname;
            }
        }
        return null;
    }

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
                        'jointype' => 'child',
                    ],
                ],
                'jointype' => 'child',
            ],
        ];

        $tab['7001'] = [
            'table'         => self::getTable(),
            'field'         => 'actual_actiontime',
            'name'          => __("Duration Diff", "actualtime"),
            'datatype'      => 'specific',
            'type'          => 'diff',
            'joinparams'    => [
                'beforejoin' => [
                    'table' => 'glpi_tickettasks',
                    'additionalfields' => ['itemtype'],
                    'joinparams' => [
                        'jointype' => 'child',
                    ],
                ],
                'jointype' => 'child',
            ],
        ];

        $tab['7002'] = [
            'table'         => self::getTable(),
            'field'         => 'actual_actiontime',
            'name'          => __("Duration Diff", "actualtime") . " (%)",
            'datatype'      => 'specific',
            'type'          => 'diff%',
            'joinparams'    => [
                'beforejoin' => [
                    'table' => 'glpi_tickettasks',
                    'additionalfields' => ['itemtype'],
                    'joinparams' => [
                        'jointype' => 'child',
                    ],
                ],
                'jointype' => 'child',
            ],
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
                $parent = getItemForItemtype($options['searchopt']['parent'] ?? '');
                if (!$parent instanceof CommonITILObject || !$parent->getFromDB($options['raw_data']['id'])) {
                    return '';
                }
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
                    ],
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
                                'actual_actiontime',
                            ],
                            'FROM' => self::getTable(),
                            'WHERE' => [
                                'items_id' => $options['raw_data']['id'],
                                'itemtype' => $itemtype,
                            ],
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
        if (!$item instanceof CommonITILTask && !$item instanceof ProjectTask) {
            return;
        }
        $config = Config::getInstance();
        $twig = TemplateRenderer::getInstance();

        if ($item instanceof CommonITILTask) {
            if (!$item->getID()) {
                // New task form
                $twig->display('@actualtime/timer/autostart.html.twig', [
                    'itemtype' => $item->getType(),
                ]);
                return;
            }
            $can_run = ($item->fields['users_id_tech'] == Session::getLoginUserID() && $item->can($item->getID(), UPDATE));
            $runnable = $item->getField('state') == Planning::TODO
                && !(($config->fields['planned_task'] ?? 0) && !is_null($item->fields['begin']) && $item->fields['begin'] > date("Y-m-d H:i:s"));
            if ($can_run || Session::getCurrentInterface() == "central" || $config->showInHelpdesk()) {
                self::renderTimerBlock($item, $can_run, $runnable, false, false);
            }

            $parent = self::getParentItem($item);
            if ($parent !== null) {
                $parent_key = $parent::getForeignKeyField();
                $twig->display('@actualtime/timer/assign_me.html.twig', [
                    'rand'            => mt_rand(),
                    'task_id'         => $item->getID(),
                    'itemtype'        => $item->getType(),
                    'form_url'        => $item::getFormURL(),
                    'parent_itemtype' => $parent::getType(),
                    'parent_key'      => $parent_key,
                    'parent_id'       => $item->fields[$parent_key],
                    'users_id'        => Session::getLoginUserID(),
                ]);
            }
            return;
        }

        // Project task
        if (!$item->getID()) {
            return;
        }
        $finished_states_ids = array_column(
            iterator_to_array($DB->request([
                'SELECT' => ['id'],
                'FROM'   => ProjectState::getTable(),
                'WHERE'  => ['is_finished' => 1],
            ])),
            'id',
        );
        $task_id = $item->getID();
        $itemtype = $item->getType();
        $can_run = $item->canUpdateItem();
        $runnable = !in_array($item->getField('projectstates_id'), $finished_states_ids)
            && !(($config->fields['planned_task'] ?? 0) && !is_null($item->fields['real_start_date']) && $item->fields['real_start_date'] > date("Y-m-d H:i:s"));
        if ($can_run || Session::getCurrentInterface() == "central" || $config->showInHelpdesk()) {
            $show_modify_link = Sourcetimer::checkItemtypeRight($itemtype)
                && countElementsInTable(self::getTable(), ['items_id' => $task_id, 'itemtype' => $itemtype, 'NOT' => ['actual_end' => null]]) > 0
                && Sourcetimer::canModify($itemtype, $task_id);
            self::renderTimerBlock($item, $can_run, $runnable, true, $show_modify_link);
        }
    }

    /**
     * Timer block of a task form
     *
     * @param  CommonDBTM $item             the task
     * @param  bool       $can_run          the current user can start and stop the timer
     * @param  bool       $runnable         the task state and planning allow to run the timer
     * @param  bool       $with_segments    show the timer segments
     * @param  bool       $show_modify_link show the link to modify the segments
     * @return void
     */
    private static function renderTimerBlock(CommonDBTM $item, bool $can_run, bool $runnable, bool $with_segments, bool $show_modify_link): void
    {
        $task_id = $item->getID();
        $itemtype = $item->getType();
        TemplateRenderer::getInstance()->display('@actualtime/timer/task_timer.html.twig', [
            'task_id'          => $task_id,
            'itemtype'         => $itemtype,
            'rand'             => mt_rand(),
            'can_run'          => $can_run,
            'runnable'         => $runnable,
            'active'           => $can_run && $runnable && self::checkTimerActive($task_id, $itemtype),
            'time'             => self::totalEndTime($task_id, $itemtype),
            'with_segments'    => $with_segments,
            'segments'         => $with_segments ? self::getSegment($task_id, $itemtype) : '',
            'show_modify_link' => $show_modify_link,
        ]);
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
            ],
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
            ],
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
            ],
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
            ],
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
            ],
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
            ],
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
            ],
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
                'itemtype',
            ],
            'FROM' => self::getTable(),
            'WHERE' => [
                [
                    'NOT' => ['actual_begin' => null],
                ],
                'actual_end' => null,
                'users_id' => $user_id,
            ],
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
    public static function getActualBegin($task_id, $itemtype): ?string
    {
        /** @var \DBmysql $DB */
        global $DB;

        // Same criteria as checkTimerActive(): a row with no begin is not a running timer.
        $query = [
            'FROM' => self::getTable(),
            'WHERE' => [
                'items_id' => $task_id,
                'itemtype' => $itemtype,
                [
                    'NOT' => ['actual_begin' => null],
                ],
                'actual_end' => null,
            ],
        ];
        $row = $DB->request($query)->current();

        // No running timer: null instead of reading a key on a null row, which was a
        // TypeError against the declared `string`.
        return $row['actual_begin'] ?? null;
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

        $config = Config::getInstance();
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
                ],
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
            $html .= "<tr class='tab_bg_2'><td>" . __("Duration Diff", "actualtime") . "</td><td style='color:" . $color . "'>" . Html::timestampToString($diff) . "</td></tr>";
            if ($total_time == 0) {
                $diffpercent = 0;
            } else {
                $diffpercent = 100 * ($total_time - $actual_totaltime) / $total_time;
            }
            $html .= "<tr class='tab_bg_2'><td>" . __("Duration Diff", "actualtime") . " (%)</td><td style='color:" . $color . "'>" . round($diffpercent, 2) . "%</td></tr>";

            $html .= "</table>";

            $html .= "<table class='tab_cadre_fixe'>";
            $html .= "<tr><th colspan='5'>ActualTime - " . __("Technician") . "</th></tr>";
            $html .= "<tr><th>" . __("Technician") . "</th><th>" . __("Total duration") . "</th><th>ActualTime - " . __("Total duration") . "</th><th>" . __("Duration Diff", "actualtime") . "</th><th>" . __("Duration Diff", "actualtime") . " (%)</th></tr>";

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
                $list[$row['users_id_tech']]['name'] = htmlescape(getUserName($row['users_id_tech']));
                if (isset($list[$row['users_id_tech']]['total'])) {
                    $list[$row['users_id_tech']]['total'] += $row['actiontime'];
                } else {
                    $list[$row['users_id_tech']]['total'] = $row['actiontime'];
                }
                $qtime = [
                    'SELECT' => [
                        'SUM' => 'actual_actiontime AS actual_total',
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
            $html_js = json_encode($html, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

            $script = <<<JAVASCRIPT
$(document).ready(function(){
    $("div.dates_timelines:last").append({$html_js});
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
            ],
        ];
        $html = "";
        foreach ($DB->request($query) as $id => $row) {
            $html .= "<div class='row center'><div class='col-12 col-md-7'>" . $row['actual_begin'] . "</div>";
            $style = "";
            if ($row['is_modified']) {
                $style = "color: red;font-weight: bold;font-style: italic;";
            }
            $html .= "<div class='col-12 col-md-5' style='$style'>" . Html::timestampToString($row['actual_actiontime']);
            $source = new Sourcetimer();
            if (
                $row['is_modified']
                && $source->getFromDBByCrit([
                    'plugin_actualtime_tasks_id' => $row['id'],
                ])
            ) {
                $comment = __("Original end date", "actualtime") . ": " . $source->fields['source_end'] . "<br>";
                $comment .= __("Original duration", "actualtime") . ": " . Html::timestampToString($source->fields['source_actiontime']) . "<br>";
                $comment .= sprintf(__("First modification by %s", "actualtime"), htmlescape(getUserName($source->fields['users_id'])));
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
        $config = Config::getInstance();

        $autostart_checked = isset($item->input['autostart']) && $item->input['autostart'];
        $autostart_config  = (int) ($config->fields['autoopenrunning'] ?? 0) === 1;

        if ($autostart_checked || $autostart_config) {
            if (
                $item->getField('state') == Planning::TODO
                && $item->getField('users_id_tech') == Session::getLoginUserID()
                && $item->fields['id']
            ) {
                $task_id = $item->fields['id'];
                $result = self::startTimer($task_id, $item->getType(), self::WEB);
                if ($result['type'] != 'info') {
                    Session::addMessageAfterRedirect($result['message'], true, WARNING);
                    return;
                } else {
                    Session::addMessageAfterRedirect($result['message'], true, INFO);
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

        $config = Config::getInstance();
        $itemtype = $item->getType();
        if (!array_key_exists('plugin_actualtime', $item->input)) {
            if (array_key_exists('state', $item->input) && array_key_exists('state', $item->fields)) {
                if ($item->fields['state'] != $item->input['state']) {
                    if ($item->input['state'] != Planning::TODO) {
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
                            'is_finished' => 1,
                        ],
                    ],
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
            $parent = self::getParentItem($task);
            if ($parent_id = Task::getParent(Session::getLoginUserID())) {
                $parent_id = (int) $parent_id;
                $link = jsescape($parent->getFormURLWithID($parent_id));
                $name = jsescape($parent->getTypeName(1));
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
        $item = $params['item'] ?? null;
        if (!$item instanceof CommonITILTask || !$item->getID()) {
            // Only tasks of the ITIL timeline (params['item'] is sometimes an array, like for solutions)
            return;
        }
        $config = Config::getInstance();
        $task_id = $item->getID();
        $itemtype = $item->getType();
        $user_id = Session::getLoginUserID();

        // Timer in the task box: standard interface, or helpdesk if settings allow it
        $show_box = $config->showTimerInBox()
            && (Session::getCurrentInterface() == "central" || $config->showInHelpdesk());
        $show_button = $item->fields['users_id_tech'] == $user_id
            && $item->can($task_id, UPDATE)
            && $item->fields['state'] != Planning::INFO;
        $auto_open = $config->autoOpenRunning() && self::checkUser($task_id, $itemtype, $user_id);
        if (!$show_box && !$show_button && !$auto_open) {
            return;
        }

        TemplateRenderer::getInstance()->display('@actualtime/timer/timeline.html.twig', [
            'task_id'     => $task_id,
            'itemtype'    => $itemtype,
            'rand'        => $params['options']['rand'] ?? mt_rand(),
            'time'        => self::totalEndTime($task_id, $itemtype),
            'active'      => self::checkTimerActive($task_id, $itemtype),
            'is_modified' => countElementsInTable(self::getTable(), ['items_id' => $task_id, 'itemtype' => $itemtype, 'is_modified' => 1]) > 0,
            'show_box'    => $show_box,
            'show_button' => $show_button,
            'runnable'    => $item->getField('state') == Planning::TODO && !self::disableButton($item)['disable'],
            'auto_open'   => $auto_open,
        ]);
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
         * @var \Glpi\Config\ConfigContainer $CFG_GLPI
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
        $begin    = $options['begin'];
        $end      = $options['end'];

        $query = [
            'FROM' => self::getTable(),
            'WHERE' => [
                'actual_begin' => ['<=', $end],
                'actual_end' => ['>=', $begin],
            ],
            'ORDER' => [
                'actual_begin ASC',
            ],
        ];

        // Timers belong to a user only: there is no group to filter on
        if ($who > 0) {
            $query['WHERE'][] = ["users_id" => $who];
        }

        foreach ($DB->request($query) as $id => $row) {
            // Only show timers of tasks the current user can view (rights and entities)
            if (!self::isAllowedItemtype($row['itemtype'])) {
                continue;
            }
            $task = getItemForItemtype($row['itemtype']);
            if (!$task->getFromDB($row['items_id']) || !$task->canViewItem()) {
                continue;
            }

            $key = $row["actual_begin"] . "$$" . "Task" . $row["id"];
            $interv[$key]['color']            = $options['color'];
            $interv[$key]['event_type_color'] = $options['event_type_color'];
            $interv[$key]['itemtype']         = self::getType();
            $interv[$key]['id']               = $row['id'];
            $interv[$key]["users_id"]         = $row["users_id"];
            $interv[$key]["name"]             = self::getTypeName();
            $interv[$key]["content"]          = Html::timestampToString($row['actual_actiontime']);

            $parent = self::getParentItem($task);
            if ($parent === null) {
                continue;
            }
            $canupdate = $task instanceof CommonITILTask ? $task->canUpdateITILItem() : $task->canUpdateItem();
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
        $html = "<strong>" . htmlescape($val["name"]) . "</strong>";
        $html .= "<br><strong>" . sprintf(__('By %s'), htmlescape(getUserName($val["users_id"]))) . "</strong>";
        $html .= "<br><strong>" . __('Start date') . "</strong> : " . Html::convDateTime($val["begin"]);
        $html .= "<br><strong>" . __('End date') . "</strong> : " . Html::convDateTime($val["end"]);
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

        $config = Config::getInstance();

        $result = [
            'disable' => false,
            'message' => '',
        ];
        if (($config->fields['planned_task'] ?? 0)) {
            if (isset($task->fields['begin'])) {
                if ($task->fields['begin'] > date("Y-m-d H:i:s")) {
                    $result['disable'] = true;
                    $result['message'] = sprintf(__("You cannot start a timer because the task was scheduled for %s.", 'actualtime'), Html::convDateTime($task->fields['begin']));
                    return $result;
                }
            } elseif (isset($task->fields['real_start_date'])) {
                if ($task->fields['real_start_date'] > date("Y-m-d H:i:s")) {
                    $result['disable'] = true;
                    $result['message'] = sprintf(__("You cannot start a timer because the task was scheduled for %s.", 'actualtime'), Html::convDateTime($task->fields['real_start_date']));
                    return $result;
                }
            }
        }

        if (($config->fields['multiple_day'] ?? 0)) {
            $query = [
                'SELECT' => [
                    new QueryExpression(
                        "FROM_UNIXTIME(UNIX_TIMESTAMP(" . $DB->quoteName("actual_end") . "),'%Y-%m-%d') AS date",
                    ),
                ],
                'FROM' => self::getTable(),
                'WHERE' => [
                    'items_id'  => $task->getID(),
                    'itemtype'  => $task->getType(),
                    'NOT'       => ['actual_end' => null],
                ],
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
         * @var \Glpi\Config\ConfigContainer $CFG_GLPI
         */
        global $DB, $CFG_GLPI;

        if ($denied = self::checkTimerAccess($task_id, $itemtype, $origin)) {
            return $denied;
        }

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
            ],
        );

        $tam_class = self::getIntegrationClass('tam', 'Tam');
        $tam_leave_class = self::getIntegrationClass('tam', 'Leave');
        if ($tam_class !== null && $tam_leave_class !== null) {
            if ($tam_leave_class::checkLeave(Session::getLoginUserID())) {
                $result['message'] = __("Today is marked as absence you can not initialize the timer", 'tam');
                return $result;
            } else {
                $timer_id = $tam_class::checkWorking(Session::getLoginUserID());
                if ($timer_id == 0 || $tam_class::checkCurrentTamType() == 'coffee_break') {
                    $link = "<a href='" . $CFG_GLPI['root_doc'] . "/front/preference.php";
                    $link .= "?forcetab=" . urlencode($tam_class . '$1') . "'>" . __s("Timer has not been initialized", 'tam') . "</a>";
                    $result['message'] = $link;
                    return $result;
                }
            }
        }

        $waypoint_class = self::getIntegrationClass('waypoint', 'Waypoint');
        if ($waypoint_class !== null) {
            $waypoint = new $waypoint_class();
            $count = countElementsInTable(
                $waypoint->getTable(),
                [
                    'users_id' => Session::getLoginUserID(),
                    'date_end' => null,
                ],
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
            if ($task->getField('state') != Planning::TODO) {
                $result['message'] = __("Task completed.");
                return $result;
            }
        } else {
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
            if (in_array($task->getField('projectstates_id'), $finished_states_ids)) {
                $result['message'] = __("Task completed.");
                return $result;
            }
        }

        if (isset($task->fields['users_id_tech'])) {
            if (Session::getLoginUserID() != $task->fields['users_id_tech']) {
                $result['message'] = __("Technician not in charge of the task", 'actualtime');
                return $result;
            }
        } else {
            if (!$task->canUpdateItem()) {
                $result['message'] = __("Technician not in charge of the task", 'actualtime');
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
            $parent = self::getParentItem($task);
            $parent_key = $parent->getForeignKeyField();
            $parent_id = $task->fields[$parent_key];

            $active_task_id = 0;
            $active_task_itemtype = '';
            $active_task_parent_id = 0;
            $active_task_parent_itemtype = '';
            $iterator = $DB->request([
                'FROM'  => self::getTable(),
                'WHERE' => [
                    'users_id'      => Session::getLoginUserID(),
                    'actual_end'    => null,
                ],
                'LIMIT' => 1,
            ]);
            if ($row = $iterator->current()) {
                // Active task found, get its id and itemtype
                $active_task_id = $row['items_id'];
                $active_task_itemtype = $row['itemtype'];
                $tmp_task = new $active_task_itemtype();
                if ($tmp_task->getFromDB($active_task_id)) {
                    $dbu = new DbUtils();
                    // get parent id and itemtype, allowing TicketTask, ProblemTask, ChangeTask..
                    if ($tmp_task instanceof CommonITILTask) {
                        $active_task_parent_itemtype = $tmp_task->getItilObjectItemType();
                    } else {
                        //ProjectTask
                        $active_task_parent_itemtype = $tmp_task::$itemtype ?? '';
                    }
                    if ($active_task_parent_itemtype) {
                        $tmp_parent_table = $dbu->getTableForItemType($active_task_parent_itemtype);
                        $tmp_key = $dbu->getForeignKeyFieldForTable($tmp_parent_table);
                        $active_task_parent_id = $tmp_task->fields[$tmp_key] ?? 0;
                    }
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
                ],
            );

            $timer_id = $DB->insertId();
            $parent = self::getParentItem($task);
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

            $gapp_push_class = self::getIntegrationClass('gappextended', 'Push');
            if ($gapp_push_class !== null) {
                $gapp_push_class::sendActualtime(
                    self::getParent(Session::getLoginUserID()),
                    $task_id,
                    $result,
                    Session::getLoginUserID(),
                    true,
                    $parent::getType(),
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
        /**
         * @var \DBmysql $DB
         * @var \Glpi\Config\ConfigContainer $CFG_GLPI
         */
        global $DB, $CFG_GLPI;

        if ($denied = self::checkTimerAccess($task_id, $itemtype, $origin)) {
            return $denied;
        }

        $result = [
            'type'   => 'warning',
        ];

        $config = Config::getInstance();
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
                    ],
                );

                if ($config->autoUpdateDuration()) {
                    $task = new $itemtype();
                    $task->getFromDB($task_id);

                    $totalendtime = Task::totalEndTime($task_id, $itemtype);
                    $time_step = $CFG_GLPI["time_step"] * MINUTE_TIMESTAMP;
                    $ceil = $time_step > 0
                        ? ceil($totalendtime / $time_step) * $time_step
                        : $totalendtime;

                    $sync_input = [
                        'id'                => $task_id,
                        'plugin_actualtime' => true,
                    ];
                    if (isset($task->fields['actiontime'])) {
                        $sync_input['actiontime'] = $ceil;
                    } else {
                        $sync_input['effective_duration'] = $ceil;
                    }
                    $task->update($sync_input);
                }

                $result = [
                    'message'  => __("Timer completed", 'actualtime'),
                    'type'     => 'info',
                    'segment'  => self::getSegment($task_id, $itemtype),
                    'time'     => abs(self::totalEndTime($task_id, $itemtype)),
                    'timer_id' => $timer_id,
                ];

                $gapp_push_class = self::getIntegrationClass('gappextended', 'Push');
                $gapp_timer_class = self::getIntegrationClass('gappextended', 'Timer');
                if ($gapp_push_class !== null && $gapp_timer_class !== null) {
                    $task = new $itemtype();
                    $task->getFromDB($task_id);
                    if (is_a($task, CommonDBChild::class, true)) {
                        $parent = $task::$itemtype;
                    } else {
                        $parent = $task->getItilObjectItemType();
                    }
                    $gapp_push_class::sendActualtime(
                        $task->fields[getForeignKeyFieldForItemType($parent)],
                        $task_id,
                        $result,
                        Session::getLoginUserID(),
                        false,
                        $parent,
                    );

                    $timerquery = [
                        'FROM' => $gapp_timer_class::getTable(),
                        'WHERE' => [
                            'items_id' => $timer_id,
                            // GappExtended stores the legacy class name in older rows
                            'itemtype' => [self::class, 'PluginActualtimeTask'],
                        ],
                    ];

                    foreach ($DB->request($timerquery) as $timerrow) {
                        $timer = new $gapp_timer_class();
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
         * @var \Glpi\Config\ConfigContainer $CFG_GLPI
         */
        global $DB, $CFG_GLPI;

        if ($denied = self::checkTimerAccess($task_id, $itemtype, $origin)) {
            return $denied;
        }

        $result = [
            'type'   => 'warning',
        ];

        $config = Config::getInstance();

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
                    ],
                );

                $input = [];
                $task = new $itemtype();
                $task->getFromDB($task_id);
                $input['id'] = $task_id;
                $input['state'] = Planning::DONE;
                $input['plugin_actualtime'] = true;
                if ($config->autoUpdateDuration()) {
                    $totalendtime = Task::totalEndTime($task_id, $itemtype);
                    $time_step = $CFG_GLPI["time_step"] * MINUTE_TIMESTAMP;
                    $ceil = $time_step > 0
                        ? ceil($totalendtime / $time_step) * $time_step
                        : $totalendtime;
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
                    'segment'   => Task::getSegment($task_id, $itemtype),
                    'time'      => abs(Task::totalEndTime($task_id, $itemtype)),
                    'task_time' => $task->getField('actiontime'),
                    'timer_id'  => $timer_id,
                ];

                $gapp_push_class = self::getIntegrationClass('gappextended', 'Push');
                $gapp_timer_class = self::getIntegrationClass('gappextended', 'Timer');
                if ($gapp_push_class !== null && $gapp_timer_class !== null) {
                    if (is_a($task, CommonDBChild::class, true)) {
                        $parent = $task::$itemtype;
                    } else {
                        $parent = $task->getItilObjectItemType();
                    }
                    $gapp_push_class::sendActualtime(
                        $task->fields[getForeignKeyFieldForItemType($parent)],
                        $task_id,
                        $result,
                        $actualtime->fields['users_id'],
                        false,
                        $parent,
                    );

                    $timerquery = [
                        'FROM' => $gapp_timer_class::getTable(),
                        'WHERE' => [
                            'items_id' => $timer_id,
                            // GappExtended stores the legacy class name in older rows
                            'itemtype' => [self::class, 'PluginActualtimeTask'],
                        ],
                    ];

                    foreach ($DB->request($timerquery) as $timerrow) {
                        $timer = new $gapp_timer_class();
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
            $input['state'] = Planning::DONE;
            $input['pending'] = 0;
            $input['plugin_actualtime'] = true;
            if ($config->autoUpdateDuration()) {
                $totalendtime = Task::totalEndTime($task_id, $itemtype);
                $time_step = $CFG_GLPI["time_step"] * MINUTE_TIMESTAMP;
                $ceil = $time_step > 0
                    ? ceil($totalendtime / $time_step) * $time_step
                    : $totalendtime;
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
                'segment'   => Task::getSegment($task_id, $itemtype),
                'time'      => abs(Task::totalEndTime($task_id, $itemtype)),
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
                $parent = self::getParentItem($task);
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

        $default_charset    = DBConnection::getDefaultCharset();
        $default_collation  = DBConnection::getDefaultCollation();
        $default_key_sign   = DBConnection::getDefaultPrimaryKeySignOption();

        $table = self::getTable();
        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");
            $query = "CREATE TABLE IF NOT EXISTS $table (
                `id` INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `itemtype` VARCHAR(255) NOT NULL,
                `items_id` INT {$default_key_sign} NOT NULL DEFAULT '0',
                `tickettasks_id` INT {$default_key_sign} NOT NULL DEFAULT '0',
                `actual_begin` TIMESTAMP NULL DEFAULT NULL,
                `actual_end` TIMESTAMP NULL DEFAULT NULL,
                `users_id` INT {$default_key_sign} NOT NULL,
                `actual_actiontime` INT {$default_key_sign} NOT NULL DEFAULT 0,
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
            $DB->doQuery($query);
        } else {
            $migration->dropField($table, 'latitude_start');
            $migration->dropField($table, 'longitude_start');
            $migration->dropField($table, 'latitude_end');
            $migration->dropField($table, 'longitude_end');
            $migration->changeField($table, 'origin_end', 'origin_end', 'int', ['value' => 0]);

            $migration->addField($table, 'override_begin', 'timestamp', ['nodefault' => true, 'null' => true]);
            $migration->addField($table, 'override_end', 'timestamp', ['nodefault' => true, 'null' => true]);

            $migration->addField(
                $table,
                'itemtype',
                'varchar(255) NOT NULL',
                ['after' => 'id', 'update' => "'TicketTask'"],
            );
            $migration->addField(
                $table,
                'items_id',
                "int {$default_key_sign} NOT NULL DEFAULT '0'",
                ['after' => 'itemtype', 'update' => $DB->quoteName($table . '.tickettasks_id')],
            );
            $migration->addKey($table, ['itemtype', 'items_id'], 'item');

            $migration->addField($table, 'is_modified', 'bool');

            $migration->addField($table, 'tickettasks_id', 'int', ['value' => 0, 'unsigned' => true]);

            $migration->migrationOneTable($table);
        }

        self::migratePlanningFilters($migration);
    }

    /**
     * 5.0.0: the planning type is now the PSR-4 class name.
     * Keep the color and visibility users had chosen for the legacy one.
     *
     * @param  Migration $migration
     * @return void
     */
    private static function migratePlanningFilters(Migration $migration): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $legacy = 'PluginActualtimeTask';
        $iterator = $DB->request([
            'SELECT' => ['id', 'plannings'],
            'FROM'   => User::getTable(),
            'WHERE'  => ['plannings' => ['LIKE', '%"' . $legacy . '"%']],
        ]);
        foreach ($iterator as $row) {
            $plannings = json_decode((string) $row['plannings'], true);
            if (!is_array($plannings) || !isset($plannings['filters'][$legacy])) {
                continue;
            }
            $plannings['filters'][self::class] ??= $plannings['filters'][$legacy];
            unset($plannings['filters'][$legacy]);
            $DB->update(
                User::getTable(),
                ['plannings' => json_encode($plannings)],
                ['id' => $row['id']],
            );
        }
        if (count($iterator)) {
            $migration->displayMessage(sprintf('Planning filters migrated for %d users', count($iterator)));
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
