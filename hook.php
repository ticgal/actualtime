<?php
/*
 -------------------------------------------------------------------------
 ActualTime plugin for GLPI
 Copyright (C) 2018-2022 by the TICgal Team.
 https://www.tic.gal/
 -------------------------------------------------------------------------
 LICENSE
 This file is part of theOneTimeSecret plugin.
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

/**
 * Install all necessary elements for the plugin
 *
 * @return boolean True if success
 */
function plugin_actualtime_install()
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

function plugin_actualtime_item_stats($item)
{
   PluginActualtimeTask::showStats($item);
}

function plugin_actualtime_item_update($item)
{
   PluginActualtimeTask::preUpdate($item);
}

function plugin_actualtime_item_add($item)
{
   PluginActualtimeTask::afterAdd($item);
}

function plugin_actualtime_preSolutionAdd(ITILSolution $solution)
{
   global $DB, $CFG_GLPI;

   if ($solution->input['itemtype'] == Ticket::getType()) {

      $config = new PluginActualtimeConfig();
      
      $ticket_id = $solution->input['items_id'];

      $query = [
         'SELECT' => [
            PluginActualtimeTask::getTable().'.id',
            PluginActualtimeTask::getTable().'.items_id',
         ],
         'FROM' => 'glpi_tickettasks',
         'INNER JOIN' => [
            PluginActualtimeTask::getTable() => [
               'ON' => [
                  PluginActualtimeTask::getTable() => 'items_id',
                  'glpi_tickettasks' => 'id', [
                     'AND' => [
                        PluginActualtimeTask::getTable().'.itemtype' => 'TicketTask',
                     ]
                  ]
               ]
            ],
         ],
         'WHERE' => [
            'tickets_id' => $ticket_id,
            'actual_end' => null,
         ]
      ];
      $task = new TicketTask();
      foreach ($DB->request($query) as $id => $row) {
         $task_id = $row['items_id'];

         $actual_begin = PluginActualtimeTask::getActualBegin($task_id);
         $seconds = (strtotime(date("Y-m-d H:i:s")) - strtotime($actual_begin));

         $DB->update(
            'glpi_plugin_actualtime_tasks',
            [
               'actual_end'        => date("Y-m-d H:i:s"),
               'actual_actiontime' => $seconds,
               'origin_end' => PluginActualtimetask::AUTO,
            ],
            [
               'items_id' => $task_id,
               'itemtype' => 'TicketTask',
               [
                  'NOT' => ['actual_begin' => null],
               ],
               'actual_end' => null,
            ]
         );
         $task->getFromDB($task_id);
         $input['id'] = $task_id;
         $input['tickets_id'] = $task->fields['tickets_id'];
         $input['state'] = 2;
         if ($config->autoUpdateDuration()) {
            $input['actiontime'] = ceil(PluginActualtimeTask::totalEndTime($task_id, $task->getType()) / ($CFG_GLPI["time_step"] * MINUTE_TIMESTAMP)) * ($CFG_GLPI["time_step"] * MINUTE_TIMESTAMP);
         }
         $task->update($input);
      }
   }
}

function plugin_actualtime_item_purge(CommonITILTask $item)
{
   global $DB;

   $DB->delete(
      PluginActualtimeTask::getTable(),
      [
         'items_id' => $item->fields['id'],
         'itemtype' => $item->getType(),
      ]
   );
}

function plugin_actualtime_ticket_delete(CommonITILObject $parent)
{
   global $DB;

   $tactualtime = PluginActualtimeTask::getTable();
   $tparent = $parent::getTable();
   $taskitemtype = $parent->getTaskClass();
   $ttask = $taskitemtype::getTable();

   $query = [
      'SELECT' => [
         $tactualtime.'.actual_begin',
         $tactualtime.'.id',
      ],
      'FROM' => $tactualtime,
      'INNER JOIN' => [
         $ttask => [
            'ON' => [
               $ttask => 'id',
               $tactualtime => 'items_id', [
						'AND' => [
							$tactualtime.'.itemtype' => $taskitemtype,
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
         'NOT' => [$tactualtime.'.actual_begin' => null],
         $tactualtime.'.actual_end' => null,
         $tparent.'.id' => $parent->fields['id']
      ]
   ];
   foreach ($DB->request($query) as $result) {
      $seconds = (strtotime(date("Y-m-d H:i:s")) - strtotime($result['actual_begin']));
      $DB->update(
         $tactualtime,
         [
            'actual_end'      => date("Y-m-d H:i:s"),
            'actual_actiontime'      => $seconds,
            'origin_end' => PluginActualtimetask::AUTO,
         ],
         [
            'id' => $result['id']
         ]
      );
   }
}

function plugin_actualtime_getAddSearchOptions($itemtype)
{
   $tab = [];

   switch ($itemtype) {
      case Ticket::getType():
         $config = new PluginActualtimeConfig();
         if ((Session::getCurrentInterface() == "central") || $config->showInHelpdesk()) {
            $tab['actualtime'] = PLUGIN_ACTUALTIME_NAME;

            $tab['7000'] = [
               'table' => PluginActualtimeTask::getTable(),
               'field' => 'actual_actiontime',
               'name' => __('Total duration'),
               'datatype' => 'specific',
               'parent' => Ticket::class,
               'joinparams' => [
                  'beforejoin' => [
                     'table' => 'glpi_tickettasks',
                     'joinparams' => [
                        'jointype' => 'child'
                     ]
                  ],
                  'jointype' => 'child',
               ],
               'type' => 'total'
            ];
            $tab['7001'] = [
               'table' => PluginActualtimeTask::getTable(),
               'field' => 'actual_actiontime',
               'name' => __("Duration Diff", "actiontime"),
               'datatype' => 'specific',
               'parent' => Ticket::class,
               'joinparams' => [
                  'beforejoin' => [
                     'table' => 'glpi_tickettasks',
                     'joinparams' => [
                        'jointype' => 'child'
                     ]
                  ],
                  'jointype' => 'child',
               ],
               'type' => 'diff'
            ];
            $tab['7002'] = [
               'table' => PluginActualtimeTask::getTable(),
               'field' => 'actual_actiontime',
               'name' => __("Duration Diff", "actiontime") . " (%)",
               'datatype' => 'specific',
               'parent' => Ticket::class,
               'joinparams' => [
                  'beforejoin' => [
                     'table' => 'glpi_tickettasks',
                     'joinparams' => [
                        'jointype' => 'child'
                     ]
                  ],
                  'jointype' => 'child',
               ],
               'type' => 'diff%'
            ];
         }
         break;
      case 'TicketTask':
         $config = new PluginActualtimeConfig;
         if ((Session::getCurrentInterface() == "central") || $config->showInHelpdesk()) {
            $tab['actualtime'] = 'ActualTime';

            $tab['7003'] = [
               'table' => PluginActualtimeTask::getTable(),
               'field' => 'actual_actiontime',
               'name' => __('Task duration'),
               'datatype' => 'specific',
               'parent' => Ticket::class,
               'joinparams' => [
                  'beforejoin' => [
                     'table' => 'glpi_tickettasks',
                     'joinparams' => [
                        'jointype' => 'child'
                     ]
                  ],
                  'jointype' => 'child',
                  'linkfield' => 'tasks_id'
               ],
               'type' => 'task'
            ];
         }
         break;
   }

   return $tab;
}

/**
 * Uninstall previously installed elements of the plugin
 *
 * @return boolean True if success
 */
function plugin_actualtime_uninstall()
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
