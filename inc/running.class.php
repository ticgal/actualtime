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

class PluginActualtimeRunning extends CommonGLPI
{
    public static $rightname = 'plugin_actualtime_running';

    /**
     * {@inheritDoc}
     */
    public static function getMenuName(): string
    {
        return PLUGIN_ACTUALTIME_NAME;
    }

    /**
     * {@inheritDoc}
     */
    public static function getMenuContent(): array
    {
        $menu = [
            'title' => self::getMenuName(),
            'page' => self::getSearchURL(false),
            'icon' => 'fas fa-stopwatch'
        ];

        return $menu;
    }

    /**
     * show
     *
     * @return bool
     */
    public static function show(): bool
    {
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
        return true;
    }

    /**
     * listRunning
     *
     * @return string
     */
    public static function listRunning(): string
    {
        /** @var \DBmysql $DB */
        global $DB;

        $atable = PluginActualtimeTask::getTable();
        $locationtable = Location::getTable();

        //Ticket
        $tasktable = TicketTask::getTable();
        $tickettable = Ticket::getTable();

        $queryticket = new \QuerySubQuery([
            'SELECT' => [
                $atable . '.*',
                $tasktable . '.tickets_id',
            ],
            'FROM' => $atable,
            'INNER JOIN' => [
                $tasktable => [
                    'ON' => [
                        $tasktable => 'id',
                        $atable => 'items_id',[
                            'AND' => [
                                $atable . '.itemtype' => TicketTask::getType()
                            ]
                        ]
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
                    'NOT' => [$atable . '.actual_begin' => null],
                ],
                $atable . '.actual_end' => null,
            ] + getEntitiesRestrictCriteria($tickettable),
        ]);

        //Change
        $changetable = Change::getTable();
        $tasktable = ChangeTask::getTable();

        $querychange = new \QuerySubQuery([
            'SELECT' => [
                $atable . '.*',
                $tasktable . '.changes_id',
            ],
            'FROM' => $atable,
            'INNER JOIN' => [
                $tasktable => [
                    'ON' => [
                        $tasktable => 'id',
                        $atable => 'items_id',[
                            'AND' => [
                                $atable . '.itemtype' => ChangeTask::getType()
                            ]
                        ]
                    ]
                ],
                $changetable => [
                    'ON' => [
                        $changetable => 'id',
                        $tasktable => 'changes_id'
                    ]
                ],
            ],
            'WHERE' => [
                [
                    'NOT' => [$atable . '.actual_begin' => null],
                ],
                $atable . '.actual_end' => null,
            ] + getEntitiesRestrictCriteria($changetable),
        ]);

        //Problem
        $problemtable = Problem::getTable();
        $tasktable = ProblemTask::getTable();

        $queryproblem = new \QuerySubQuery([
            'SELECT' => [
                $atable . '.*',
                $tasktable . '.problems_id',
            ],
            'FROM' => $atable,
            'INNER JOIN' => [
                $tasktable => [
                    'ON' => [
                        $tasktable => 'id',
                        $atable => 'items_id',[
                            'AND' => [
                                $atable . '.itemtype' => ProblemTask::getType()
                            ]
                        ]
                    ]
                ],
                $problemtable => [
                    'ON' => [
                        $problemtable => 'id',
                        $tasktable => 'problems_id'
                    ]
                ],
            ],
            'WHERE' => [
                [
                    'NOT' => [$atable . '.actual_begin' => null],
                ],
                $atable . '.actual_end' => null,
            ] + getEntitiesRestrictCriteria($problemtable),
        ]);

        //Project
        $projecttable = Project::getTable();
        $tasktable = ProjectTask::getTable();

        $queryproject = new \QuerySubQuery([
            'SELECT' => [
                $atable . '.*',
                $tasktable . '.projects_id',
            ],
            'FROM' => $atable,
            'INNER JOIN' => [
                $tasktable => [
                    'ON' => [
                        $tasktable => 'id',
                        $atable => 'items_id',[
                            'AND' => [
                                $atable . '.itemtype' => ProjectTask::getType()
                            ]
                        ]
                    ]
                ],
                $projecttable => [
                    'ON' => [
                        $projecttable => 'id',
                        $tasktable => 'projects_id'
                    ]
                ],
            ],
            'WHERE' => [
                [
                    'NOT' => [$atable . '.actual_begin' => null],
                ],
                $atable . '.actual_end' => null,
            ] + getEntitiesRestrictCriteria($projecttable),
        ]);

        $union = new \QueryUnion([$queryticket, $querychange, $queryproblem, $queryproject]);

        $iteratortime = $DB->request(['FROM' => $union]);
        if ($iteratortime->count() > 0) {
            $html = "<table class='tab_cadre_fixehov'>";
            $html .= "<tr>";
            $html .= "<th class='center'>" . __("Technician") . "</th>";
            $html .= "<th class='center'>" . Entity::getTypeName() . "</th>";
            $html .= "<th class='center'>" . Location::getTypeName() . "</th>";
            $html .= "<th class='center'>" . _n('Associated element', 'Associated elements', 1) . "</th>";
            $html .= "<th class='center'>" . CommonITILObject::getTypeName() . " - " . CommonITILTask::getTypeName() . "</th>";
            $html .= "<th class='center'>" . __("Time") . "</th>";
            $html .= "</tr>";

            foreach ($iteratortime as $key => $row) {
                $task = new $row['itemtype']();
                $task->getFromDB($row['items_id']);
                if (is_a($task, CommonDBChild::class, true)) {
                    $parent = getItemForItemtype($task::$itemtype);
                } else {
                    $parent = getItemForItemtype($task->getItilObjectItemType());
                }
                $parent->getFromDB($task->fields[$parent->getForeignKeyField()]);
                $html .= "<tr class='tab_bg_2'>";
                $user = new User();
                $user->getFromDB($row['users_id']);
                $html .= "<td class='center'><a href='" . $user->getLinkURL() . "'>" . $user->getFriendlyName() . "</a></td>";
                $html .= "<td class='center'>" . Entity::getFriendlyNameById($parent->fields['entities_id']) . "</td>";
                if (isset($parent->fields['locations_id'])) {
                    $html .= "<td class='center'>" . Location::getFriendlyNameById($parent->fields['locations_id']) . "</td>";
                } else {
                    $html .= "<td class='center'></td>";
                }
                $html .= "<td class='center'>";
                $html .= "<ul class='list left'>";
                if (is_a($parent, CommonITILObject::class, true)) {
                    $item_link = getItemForItemtype($parent->getItemLinkClass());
                } else {
                    $item_link = new Item_Project();
                }
                $types_iterator = $item_link::getDistinctTypes($task->fields[$parent->getForeignKeyField()]);
                foreach ($types_iterator as $type) {
                    $itemtype = $type['itemtype'];
                    if (!($item = getItemForItemtype($itemtype))) {
                        continue;
                    }
                    $html .= "<li>" . $item::getTypeName() . "</li>";
                    $iterator = $item_link::getTypeItems($task->fields[$parent->getForeignKeyField()], $itemtype);
                    $html .= "<ul>";
                    foreach ($iterator as $data) {
                        $html .= "<li>" . $data['name'] . "</li>";
                    }
                    $html .= "</ul>";
                }
                $html .= "</ul>";
                $html .= "</td>";
                $html .= "<td class='center'><a href='" . $parent->getLinkURL() . "'>";
                $html .= $parent->getTypeName(1) . " - " . $parent->getID() . " - " . $row['items_id'] . "</a></td>";
                $html .= "<td class='center'>";
                $html .= Html::timestampToString(PluginActualtimeTask::totalEndTime($row['items_id'], $row['itemtype']));
                $html .= "</td>";
                $html .= "</tr>";
            }
            $html .= "</table>";
        } else {
            $html = "<div><p class='center b'>" . __('No timer active') . "</p></div>";
        }
        return $html;
    }
}
