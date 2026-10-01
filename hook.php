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

use GlpiPlugin\Actualtime\Config;
use GlpiPlugin\Actualtime\Dashboard;
use GlpiPlugin\Actualtime\Profile as ActualtimeProfile;
use GlpiPlugin\Actualtime\Running;
use GlpiPlugin\Actualtime\Sourcetimer;
use GlpiPlugin\Actualtime\Task;

/**
 * plugin_actualtime_install
 * Install all necessary elements for the plugin
 *
 * @return bool
 */
function plugin_actualtime_install(): bool
{
    $migration = new Migration(PLUGIN_ACTUALTIME_VERSION);

    Config::install($migration);
    Dashboard::install($migration);
    Sourcetimer::install($migration);
    Task::install($migration);

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
    Task::showStats($item);
}

/**
 * plugin_actualtime_item_update
 *
 * @param  mixed $item
 * @return mixed
 */
function plugin_actualtime_item_update($item)
{
    return Task::preUpdate($item);
}

/**
 * plugin_actualtime_item_add
 *
 * @param  mixed $item
 * @return mixed
 */
function plugin_actualtime_item_add($item)
{
    Task::afterAdd($item);
}

/**
 * plugin_actualtime_getAddSearchOptionsNew
 *
 * @param  string $itemtype
 * @return array
 */
function plugin_actualtime_getAddSearchOptionsNew(string $itemtype): array
{
    if ($itemtype !== Profile::class && $itemtype !== 'Profile') {
        return [];
    }

    return [
        [
            'id'         => 3979,
            'table'      => 'glpi_profilerights',
            'field'      => 'rights',
            'name'       => __('ActualTime', 'actualtime'),
            'datatype'   => 'right',
            'rightclass' => Task::class,
            'rightname'  => Task::$rightname,
            'joinparams' => [
                'jointype'  => 'child',
                'condition' => ['NEWTABLE.name' => Task::$rightname],
            ],
        ],
        [
            'id'         => 3980,
            'table'      => 'glpi_profilerights',
            'field'      => 'rights',
            'name'       => __('Running timers', 'actualtime'),
            'datatype'   => 'right',
            'rightclass' => Running::class,
            'rightname'  => Running::$rightname,
            'joinparams' => [
                'jointype'  => 'child',
                'condition' => ['NEWTABLE.name' => Running::$rightname],
            ],
        ],
        [
            'id'         => 3981,
            'table'      => 'glpi_profilerights',
            'field'      => 'rights',
            'name'       => __('Modify timers', 'actualtime'),
            'datatype'   => 'right',
            'rightclass' => Sourcetimer::class,
            'rightname'  => Sourcetimer::$rightname,
            'joinparams' => [
                'jointype'  => 'child',
                'condition' => ['NEWTABLE.name' => Sourcetimer::$rightname],
            ],
        ],
    ];
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
    Task::postShowItem($params);

    if (
        isset($_SESSION['glpiactiveprofile']['interface'])
        && $_SESSION['glpiactiveprofile']['interface'] == 'central'
    ) {
        Sourcetimer::postShowItem($params);
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
                Task::getTable() . '.id',
                Task::getTable() . '.items_id',
            ],
            'FROM' => $ttask,
            'INNER JOIN' => [
                Task::getTable() => [
                    'ON' => [
                        Task::getTable() => 'items_id',
                        $ttask => 'id',
                        [
                            'AND' => [
                                Task::getTable() . '.itemtype' => $taskitemtype,
                            ],
                        ],
                    ],
                ],
            ],
            'WHERE' => [
                $parent_key => $parent_id,
                'actual_end' => null,
            ],
        ];
        foreach ($DB->request($query) as $id => $row) {
            $task_id = $row['items_id'];

            Task::stopTimer($task_id, $taskitemtype, Task::AUTO);
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
        Task::getTable(),
        [
            'items_id' => $item->fields['id'],
            'itemtype' => $item->getType(),
        ],
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

    $tactualtime = Task::getTable();
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
                    $tactualtime => 'items_id',
                    [
                        'AND' => [
                            $tactualtime . '.itemtype' => $taskitemtype,
                        ],
                    ],
                ],
            ],
            $tparent => [
                'ON' => [
                    $tparent => 'id',
                    $ttask =>  $parent->getForeignKeyField(),
                ],
            ],
        ],
        'WHERE' => [
            'NOT' => [$tactualtime . '.actual_begin' => null],
            $tactualtime . '.actual_end' => null,
            $tparent . '.id' => $parent->fields['id'],
        ],
    ];
    foreach ($DB->request($query) as $result) {
        $seconds = (strtotime(date("Y-m-d H:i:s")) - strtotime($result['actual_begin']));
        $DB->update(
            $tactualtime,
            [
                'actual_end'        => date("Y-m-d H:i:s"),
                'actual_actiontime' => $seconds,
                'origin_end'        => Task::AUTO,
            ],
            [
                'id' => $result['id'],
            ],
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

    $tactualtime = Task::getTable();
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
                    $tactualtime => 'items_id',
                    [
                        'AND' => [
                            $tactualtime . '.itemtype' => 'ProjectTask',
                        ],
                    ],
                ],
            ],
        ],
        'WHERE' => [
            'NOT' => [$tactualtime . '.actual_begin' => null],
            $tactualtime . '.actual_end' => null,
            $ttask . '.projects_id' => $project->fields['id'],
        ],
    ];
    foreach ($DB->request($query) as $result) {
        $seconds = (strtotime(date("Y-m-d H:i:s")) - strtotime($result['actual_begin']));
        $DB->update(
            $tactualtime,
            [
                'actual_end'        => date("Y-m-d H:i:s"),
                'actual_actiontime' => $seconds,
                'origin_end'        => Task::AUTO,
            ],
            [
                'id' => $result['id'],
            ],
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
            $config = Config::getInstance();
            if ((Session::getCurrentInterface() == "central") || $config->showInHelpdesk()) {
                $tab['actualtime'] = PLUGIN_ACTUALTIME_NAME;

                $tab['7000'] = [
                    'table'         => Task::getTable(),
                    'field'         => 'actual_actiontime',
                    'name'          => __('Total duration'),
                    'datatype'      => 'specific',
                    'parent'        => Ticket::class,
                    'joinparams'    => [
                        'beforejoin' => [
                            'table' => 'glpi_tickettasks',
                            'joinparams' => [
                                'jointype' => 'child',
                            ],
                        ],
                        'jointype'          => 'itemtype_item',
                        'specific_itemtype' => TicketTask::class,
                    ],
                    'type' => 'total',
                ];

                $tab['7001'] = [
                    'table'         => Task::getTable(),
                    'field'         => 'actual_actiontime',
                    'name'          => __("Duration Diff", "actualtime"),
                    'datatype'      => 'specific',
                    'parent'        => Ticket::class,
                    'joinparams'    => [
                        'beforejoin' => [
                            'table' => 'glpi_tickettasks',
                            'joinparams' => [
                                'jointype' => 'child',
                            ],
                        ],
                        'jointype'          => 'itemtype_item',
                        'specific_itemtype' => TicketTask::class,
                    ],
                    'type' => 'diff',
                ];

                $tab['7002'] = [
                    'table'         => Task::getTable(),
                    'field'         => 'actual_actiontime',
                    'name'          => __("Duration Diff", "actualtime") . " (%)",
                    'datatype'      => 'specific',
                    'parent'        => Ticket::class,
                    'joinparams'    => [
                        'beforejoin' => [
                            'table' => 'glpi_tickettasks',
                            'joinparams' => [
                                'jointype' => 'child',
                            ],
                        ],
                        'jointype'          => 'itemtype_item',
                        'specific_itemtype' => TicketTask::class,
                    ],
                    'type' => 'diff%',
                ];
            }
            break;
        case 'TicketTask':
            $config = Config::getInstance();
            if ((Session::getCurrentInterface() == "central") || $config->showInHelpdesk()) {
                $tab['actualtime'] = 'ActualTime';

                $tab['7003'] = [
                    'table'         => Task::getTable(),
                    'field'         => 'actual_actiontime',
                    'name'          => __('Task duration'),
                    'datatype'      => 'specific',
                    'parent'        => Ticket::class,
                    'joinparams'    => [
                        'beforejoin' => [
                            'table' => 'glpi_tickettasks',
                            'joinparams' => [
                                'jointype' => 'child',
                            ],
                        ],
                        'jointype'          => 'itemtype_item',
                        'specific_itemtype' => TicketTask::class,
                    ],
                    'type' => 'task',
                ];

                $tab['7004'] = [
                    'table'         => Task::getTable(),
                    'field'         => 'is_modified',
                    'name'          => __('Is modified'),
                    'datatype'      => 'bool',
                    'parent'        => Ticket::class,
                    'joinparams'    => [
                        'beforejoin' => [
                            'table' => 'glpi_tickettasks',
                            'joinparams' => [
                                'jointype' => 'child',
                            ],
                        ],
                        'jointype'          => 'itemtype_item',
                        'specific_itemtype' => TicketTask::class,
                    ],
                    'type' => 'task',
                ];

                $tab['7005'] = [
                    'table'         => Sourcetimer::getTable(),
                    'field'         => 'source_actiontime',
                    'name'          => __('Source Actiontime'),
                    'datatype'      => 'timestamp',
                    'parent'        => Ticket::class,
                    'joinparams'    => [
                        'beforejoin' => [
                            'table' => Task::getTable(),
                            'linkfield' => 'plugin_actualtime_tasks_id',
                            'joinparams' => [
                                'beforejoin' => [
                                    'table'     => TicketTask::getTable(),
                                ],
                            ],
                        ],
                        'jointype'          => 'child',
                        'specific_itemtype' => TicketTask::class,
                    ],
                    'type' => 'task',
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

    Config::uninstall($migration);
    ActualtimeProfile::uninstall($migration);
    Sourcetimer::uninstall($migration);
    Task::uninstall($migration);

    // Execute the whole migration
    $migration->executeMigration();

    return true;
}
