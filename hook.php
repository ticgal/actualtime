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

/**
 * plugin_actualtime_install
 * Install all necessary elements for the plugin
 *
 * @return bool
 */
function plugin_actualtime_install(): bool
{
    $migration = new Migration(PLUGIN_ACTUALTIME_VERSION);

   // Parse inc directory
    foreach (glob(__DIR__ . '/inc/*') as $filepath) {
       // Load *.class.php files and get the class name
        if (preg_match("/inc.(.+)\.class.php/", $filepath, $matches)) {
            $classname = 'PluginActualtime' . ucfirst($matches[1]);
            include_once($filepath);
           // If the install method exists, load it
            if (method_exists($classname, 'install')) {
                $classname::install($migration);
            }
        }
    }

   // Execute the whole migration
    $migration->executeMigration();

    return true;
}

/**
 * plugin_actualtime_item_stats
 *
 * @param  mixed $item
 * @return void
 */
function plugin_actualtime_item_stats($item): void
{
    PluginActualtimeTask::showStats($item);
}

/**
 * plugin_actualtime_item_update
 *
 * @param  mixed $item
 * @return mixed
 */
function plugin_actualtime_item_update($item)
{
    return PluginActualtimeTask::preUpdate($item);
}

/**
 * plugin_actualtime_item_add
 *
 * @param  mixed $item
 * @return mixed
 */
function plugin_actualtime_item_add($item)
{
    PluginActualtimeTask::afterAdd($item);
}

/**
 * plugin_actualtime_postshowitem
 *
 * @param  array $params
 * @return void
 */
function plugin_actualtime_postshowitem($params = []): void
{
    $item = isset($params['item']) ? $params['item'] : null;
    if (is_null($item)) {
        return;
    }
    PluginActualtimeTask::postShowItem($params);

    if (
        isset($_SESSION['glpiactiveprofile']['interface'])
        && $_SESSION['glpiactiveprofile']['interface'] == 'central'
    ) {
        PluginActualtimeSourcetimer::postShowItem($params);
    }
}

/**
 * plugin_actualtime_preSolutionAdd
 *
 * @param  ITILSolution $solution
 * @return void
 */
function plugin_actualtime_preSolutionAdd(ITILSolution $solution): void
{
    /** @var \DBmysql $DB */
    global $DB;

    if (empty($solution->input)) {
        return;
    }

    if (
        $solution->input['itemtype'] == Ticket::getType()
        || $solution->input['itemtype'] == Change::getType()
        || $solution->input['itemtype'] == Problem::getType()
    ) {
        $parent = new $solution->input['itemtype']();
        $taskitemtype = $parent->getTaskClass();
        $ttask = $taskitemtype::getTable();
        $parent_key = getForeignKeyFieldForItemType($parent::getType());

        $parent_id = $solution->input['items_id'];

        $query = [
            'SELECT' => [
                PluginActualtimeTask::getTable() . '.id',
                PluginActualtimeTask::getTable() . '.items_id',
            ],
            'FROM' => $ttask,
            'INNER JOIN' => [
                PluginActualtimeTask::getTable() => [
                    'ON' => [
                        PluginActualtimeTask::getTable() => 'items_id',
                        $ttask => 'id', [
                            'AND' => [
                                PluginActualtimeTask::getTable() . '.itemtype' => $taskitemtype,
                            ]
                        ]
                    ]
                ],
            ],
            'WHERE' => [
                $parent_key => $parent_id,
                'actual_end' => null,
            ]
        ];
        foreach ($DB->request($query) as $id => $row) {
            $task_id = $row['items_id'];

            PluginActualtimeTask::stopTimer($task_id, $taskitemtype, PluginActualtimeTask::AUTO);
        }
    }
}

/**
 * plugin_actualtime_item_purge
 *
 * @param  CommonDBTM $item
 * @return void
 */
function plugin_actualtime_item_purge(CommonDBTM $item): void
{
    /** @var \DBmysql $DB */
    global $DB;

    $DB->delete(
        PluginActualtimeTask::getTable(),
        [
            'items_id' => $item->fields['id'],
            'itemtype' => $item->getType(),
        ]
    );
}

/**
 * plugin_actualtime_parent_delete
 *
 * @param  CommonITILObject $parent
 * @return void
 */
function plugin_actualtime_parent_delete(CommonITILObject $parent): void
{
    /** @var \DBmysql $DB */
    global $DB;

    $tactualtime = PluginActualtimeTask::getTable();
    $tparent = $parent::getTable();
    $taskitemtype = $parent->getTaskClass();
    $ttask = $taskitemtype::getTable();

    $query = [
        'SELECT' => [
            $tactualtime . '.actual_begin',
            $tactualtime . '.id',
        ],
        'FROM' => $tactualtime,
        'INNER JOIN' => [
            $ttask => [
                'ON' => [
                    $ttask => 'id',
                    $tactualtime => 'items_id', [
                        'AND' => [
                            $tactualtime . '.itemtype' => $taskitemtype,
                        ]
                    ]
                ]
            ],
            $tparent => [
                'ON' => [
                    $tparent => 'id',
                    $ttask =>  $parent->getForeignKeyField()
                ]
            ]
        ],
        'WHERE' => [
            'NOT' => [$tactualtime . '.actual_begin' => null],
            $tactualtime . '.actual_end' => null,
            $tparent . '.id' => $parent->fields['id']
        ]
    ];
    foreach ($DB->request($query) as $result) {
        $seconds = (strtotime(date("Y-m-d H:i:s")) - strtotime($result['actual_begin']));
        $DB->update(
            $tactualtime,
            [
                'actual_end'        => date("Y-m-d H:i:s"),
                'actual_actiontime' => $seconds,
                'origin_end'        => PluginActualtimeTask::AUTO,
            ],
            [
                'id' => $result['id']
            ]
        );
    }
}

/**
 * plugin_actualtime_project_delete
 *
 * @param  Project $project
 * @return void
 */
function plugin_actualtime_project_delete(Project $project): void
{
    /** @var \DBmysql $DB */
    global $DB;

    $tactualtime = PluginActualtimeTask::getTable();
    $ttask = ProjectTask::getTable();

    $query = [
        'SELECT' => [
            $tactualtime . '.actual_begin',
            $tactualtime . '.id',
        ],
        'FROM' => $tactualtime,
        'INNER JOIN' => [
            $ttask => [
                'ON' => [
                    $ttask => 'id',
                    $tactualtime => 'items_id', [
                        'AND' => [
                            $tactualtime . '.itemtype' => 'ProjectTask',
                        ]
                    ]
                ]
            ],
        ],
        'WHERE' => [
            'NOT' => [$tactualtime . '.actual_begin' => null],
            $tactualtime . '.actual_end' => null,
            $ttask . '.projects_id' => $project->fields['id']
        ]
    ];
    foreach ($DB->request($query) as $result) {
        $seconds = (strtotime(date("Y-m-d H:i:s")) - strtotime($result['actual_begin']));
        $DB->update(
            $tactualtime,
            [
                'actual_end'        => date("Y-m-d H:i:s"),
                'actual_actiontime' => $seconds,
                'origin_end'        => PluginActualtimeTask::AUTO,
            ],
            [
                'id' => $result['id']
            ]
        );
    }
}

/**
 * plugin_actualtime_getAddSearchOptions
 *
 * @param  mixed $itemtype
 * @return array
 */
function plugin_actualtime_getAddSearchOptions($itemtype): array
{
    $tab = [];

    switch ($itemtype) {
        case Ticket::getType():
            $config = new PluginActualtimeConfig();
            if ((Session::getCurrentInterface() == "central") || $config->showInHelpdesk()) {
                $tab['actualtime'] = PLUGIN_ACTUALTIME_NAME;

                $tab['7000'] = [
                    'table'         => PluginActualtimeTask::getTable(),
                    'field'         => 'actual_actiontime',
                    'name'          => __('Total duration'),
                    'datatype'      => 'specific',
                    'parent'        => Ticket::class,
                    'joinparams'    => [
                        'beforejoin' => [
                            'table' => 'glpi_tickettasks',
                            'joinparams' => [
                                'jointype' => 'child'
                            ]
                        ],
                        'jointype'          => 'itemtype_item',
                        'specific_itemtype' => TicketTask::class,
                    ],
                    'type' => 'total'
                ];

                $tab['7001'] = [
                    'table'         => PluginActualtimeTask::getTable(),
                    'field'         => 'actual_actiontime',
                    'name'          => __("Duration Diff", "actiontime"),
                    'datatype'      => 'specific',
                    'parent'        => Ticket::class,
                    'joinparams'    => [
                        'beforejoin' => [
                            'table' => 'glpi_tickettasks',
                            'joinparams' => [
                                'jointype' => 'child'
                            ]
                        ],
                        'jointype'          => 'itemtype_item',
                        'specific_itemtype' => TicketTask::class,
                    ],
                    'type' => 'diff'
                ];

                $tab['7002'] = [
                    'table'         => PluginActualtimeTask::getTable(),
                    'field'         => 'actual_actiontime',
                    'name'          => __("Duration Diff", "actiontime") . " (%)",
                    'datatype'      => 'specific',
                    'parent'        => Ticket::class,
                    'joinparams'    => [
                        'beforejoin' => [
                            'table' => 'glpi_tickettasks',
                            'joinparams' => [
                                'jointype' => 'child'
                            ]
                        ],
                        'jointype'          => 'itemtype_item',
                        'specific_itemtype' => TicketTask::class,
                    ],
                    'type' => 'diff%'
                ];
            }
            break;
        case 'TicketTask':
            $config = new PluginActualtimeConfig();
            if ((Session::getCurrentInterface() == "central") || $config->showInHelpdesk()) {
                $tab['actualtime'] = 'ActualTime';

                $tab['7003'] = [
                    'table'         => PluginActualtimeTask::getTable(),
                    'field'         => 'actual_actiontime',
                    'name'          => __('Task duration'),
                    'datatype'      => 'specific',
                    'parent'        => Ticket::class,
                    'joinparams'    => [
                        'beforejoin' => [
                            'table' => 'glpi_tickettasks',
                            'joinparams' => [
                                'jointype' => 'child'
                            ]
                        ],
                        'jointype'          => 'itemtype_item',
                        'specific_itemtype' => TicketTask::class,
                    ],
                    'type' => 'task'
                ];
            }
            break;
    }

    return $tab;
}

/**
 * plugin_actualtime_uninstall
 * Uninstall previously installed elements of the plugin
 *
 * @return bool
 */
function plugin_actualtime_uninstall(): bool
{
    $migration = new Migration(PLUGIN_ACTUALTIME_VERSION);

   // Parse inc directory
    foreach (glob(__DIR__ . '/inc/*') as $filepath) {
       // Load *.class.php files and get the class name
        if (preg_match("/inc.(.+)\.class.php/", $filepath, $matches)) {
            $classname = 'PluginActualtime' . ucfirst($matches[1]);
            include_once($filepath);
           // If the install method exists, load it
            if (method_exists($classname, 'uninstall')) {
                $classname::uninstall($migration);
            }
        }
    }

   // Execute the whole migration
    $migration->executeMigration();

    return true;
}
