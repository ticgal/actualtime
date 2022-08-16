<?php
/*
 -------------------------------------------------------------------------
 ActualTime plugin for GLPI
 Copyright (C) 2018-2022 by the TICgal Team.
 https://www.tic.gal/
 -------------------------------------------------------------------------
 LICENSE
 This file is part of the ActualTime plugin.
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
if (!defined('GLPI_ROOT')) {
	die("Sorry. You can't access directly to this file");
}

class PluginActualtimeProvider extends CommonDBTM
{

	static function moreActualtimeTasksByDay($params = [])
	{
		$DB = DBConnection::getReadConnection();

		$data = [
			'labels' => [],
			'series' => []
		];

		$year   = date("Y") - 15;
		$begin  = date("Y-m-d", mktime(1, 0, 0, (int)date("m"), (int)date("d"), $year));
		$end    = date("Y-m-d");
		if (isset($params['apply_filters']['dates']) && count($params['apply_filters']['dates']) == 2) {
			$begin = date("Y-m-d", strtotime($params['apply_filters']['dates'][0]));
			$end   = date("Y-m-d", strtotime($params['apply_filters']['dates'][1]));
			unset($params['apply_filters']['dates']);
		}

		$task_table = TicketTask::getTable();
		$actualtime_table = PluginActualtimeTask::getTable();
		$table = Ticket::getTable();

		$sql = [
			'SELECT' => [
				"COUNT DISTINCT" => $task_table . ".id AS nb_task",
				$task_table . ".users_id_tech",
			],
			'FROM' => $task_table,
			'INNER JOIN' => [
				$actualtime_table => [
					'FKEY' => [
						$task_table => 'id',
						$actualtime_table => 'tickettasks_id',
					]
				],
				$table => [
					'FKEY' => [
						$table => 'id',
						$task_table => 'tickets_id'
					]
				]
			],
			'WHERE' => [
				$task_table . '.state' => 2,
				$actualtime_table . '.actual_begin' => ['>=', $begin],
				$actualtime_table . '.actual_end' => ['<=', $end],
			] + getEntitiesRestrictCriteria($table),
			'ORDER' => ["nb_task DESC"],
			'GROUP' => ['users_id_tech'],
			'LIMIT' => 20
		];

		$techs_id = [];
		foreach ($DB->request($sql) as $result) {
			$techs_id[] = $result['users_id_tech'];
		}

		if (count($techs_id) > 0) {
			$query = [
				'SELECT' => [
					new QueryExpression(
						"FROM_UNIXTIME(UNIX_TIMESTAMP(" . $DB->quoteName("$task_table.date") . "),'%Y-%m-%d') AS period"
					),
					"COUNT DISTINCT" => $task_table . ".id AS nb_task",
					$task_table . ".users_id_tech AS tech",
				],
				'FROM' => $task_table,
				'INNER JOIN' => [
					$actualtime_table => [
						'FKEY' => [
							$task_table => 'id',
							$actualtime_table => 'tickettasks_id',
						]
					],
					$table => [
						'FKEY' => [
							$table => 'id',
							$task_table => 'tickets_id'
						]
					]
				],
				'WHERE' => [
					$task_table . '.state' => 2,
					$actualtime_table . '.actual_begin' => ['>=', $begin],
					$actualtime_table . '.actual_end' => ['<=', $end],
					$task_table . ".users_id_tech" => $techs_id,
				] + getEntitiesRestrictCriteria($table),
				'ORDER' => ['period DESC', "nb_task DESC"],
				'GROUP' => ['period', "tech"],
			];

			$tmp = [];
			foreach ($DB->request($query) as $result) {
				if (!in_array($result['period'], $data['labels'])) {
					$data['labels'][] = $result['period'];
				}
				$tmp[$result['tech']][$result['period']] = $result['nb_task'];
			}
			sort($data['labels']);

			foreach ($tmp as $key => $value) {
				$aux = [];
				$aux['name'] = getUserName($key);
				foreach ($data['labels'] as $id => $period) {
					if (array_key_exists($period, $value)) {
						$aux['data'][] = [
							'value' => $value[$period],
						];
					} else {
						$aux['data'][] = [
							'value' => 0,
						];
					}
				}
				$data['series'][] = $aux;
			}
		}

		return [
			'data' => $data,
			'label' => $params['label'],
			'icon' => 'fa-solid fa-stopwatch'
		];
	}

	static function lessActualtimeTasksByDay($params = [])
	{
		$DB = DBConnection::getReadConnection();

		$data = [
			'labels' => [],
			'series' => []
		];

		$year   = date("Y") - 15;
		$begin  = date("Y-m-d", mktime(1, 0, 0, (int)date("m"), (int)date("d"), $year));
		$end    = date("Y-m-d");

		if (isset($params['apply_filters']['dates']) && count($params['apply_filters']['dates']) == 2) {
			$begin = date("Y-m-d", strtotime($params['apply_filters']['dates'][0]));
			$end   = date("Y-m-d", strtotime($params['apply_filters']['dates'][1]));
			unset($params['apply_filters']['dates']);
		}

		$task_table = TicketTask::getTable();
		$actualtime_table = PluginActualtimeTask::getTable();
		$table = Ticket::getTable();

		$sql = [
			'SELECT' => [
				"COUNT DISTINCT" => $task_table . ".id AS nb_task",
				$task_table . ".users_id_tech",
			],
			'FROM' => $task_table,
			'INNER JOIN' => [
				$actualtime_table => [
					'FKEY' => [
						$task_table => 'id',
						$actualtime_table => 'tickettasks_id',
					]
				],
				$table => [
					'FKEY' => [
						$table => 'id',
						$task_table => 'tickets_id'
					]
				]
			],
			'WHERE' => [
				$task_table . '.state' => 2,
				$actualtime_table . '.actual_begin' => ['>=', $begin],
				$actualtime_table . '.actual_end' => ['<=', $end],
			] + getEntitiesRestrictCriteria($table),
			'ORDER' => ["nb_task ASC"],
			'GROUP' => ['users_id_tech'],
			'HAVING' => [
				'nb_task' => ['>', 0],
			],
			'LIMIT' => 20
		];

		$techs_id = [];
		foreach ($DB->request($sql) as $result) {
			$techs_id[] = $result['users_id_tech'];
		}

		if (count($techs_id) > 0) {
			$query = [
				'SELECT' => [
					new QueryExpression(
						"FROM_UNIXTIME(UNIX_TIMESTAMP(" . $DB->quoteName("$task_table.date") . "),'%Y-%m-%d') AS period"
					),
					"COUNT DISTINCT" => $task_table . ".id AS nb_task",
					$task_table . ".users_id_tech AS tech",
				],
				'FROM' => $task_table,
				'INNER JOIN' => [
					$actualtime_table => [
						'FKEY' => [
							$task_table => 'id',
							$actualtime_table => 'tickettasks_id',
						]
					],
					$table => [
						'FKEY' => [
							$table => 'id',
							$task_table => 'tickets_id'
						]
					]
				],
				'WHERE' => [
					$task_table . '.state' => 2,
					$task_table . ".users_id_tech" => $techs_id,
					$actualtime_table . '.actual_begin' => ['>=', $begin],
					$actualtime_table . '.actual_end' => ['<=', $end],
				] + getEntitiesRestrictCriteria($table),
				'ORDER' => ['period DESC', "nb_task DESC"],
				'GROUP' => ['period', "tech"],
			];

			$tmp = [];
			foreach ($DB->request($query) as $result) {
				if (!in_array($result['period'], $data['labels'])) {
					$data['labels'][] = $result['period'];
				}
				$tmp[$result['tech']][$result['period']] = $result['nb_task'];
			}
			sort($data['labels']);

			foreach ($tmp as $key => $value) {
				$aux = [];
				$aux['name'] = getUserName($key);
				foreach ($data['labels'] as $id => $period) {
					if (array_key_exists($period, $value)) {
						$aux['data'][] = [
							'value' => $value[$period],
						];
					} else {
						$aux['data'][] = [
							'value' => 0,
						];
					}
				}
				$data['series'][] = $aux;
			}
		}

		return [
			'data' => $data,
			'label' => $params['label'],
			'icon' => 'fa-solid fa-stopwatcht'
		];
	}

	static function moreActualtimeUsageByDay($params = [])
	{
		$DB = DBConnection::getReadConnection();

		$data = [
			'labels' => [],
			'series' => []
		];

		$year   = date("Y") - 15;
		$begin  = date("Y-m-d", mktime(1, 0, 0, (int)date("m"), (int)date("d"), $year));
		$end    = date("Y-m-d");

		if (isset($params['apply_filters']['dates']) && count($params['apply_filters']['dates']) == 2) {
			$begin = date("Y-m-d", strtotime($params['apply_filters']['dates'][0]));
			$end   = date("Y-m-d", strtotime($params['apply_filters']['dates'][1]));
			unset($params['apply_filters']['dates']);
		}

		$task_table = TicketTask::getTable();
		$actualtime_table = PluginActualtimeTask::getTable();
		$table = Ticket::getTable();

		$query = [
			'SELECT' => [
				'SUM' => $actualtime_table.'.actual_actiontime AS total',
				'users_id_tech'
			],
			'FROM' => $actualtime_table,
			'INNER JOIN' => [
				$task_table => [
					'ON' => [
						$task_table => 'id',
						$actualtime_table => 'tickettasks_id'
					]
				]
			],
			'WHERE' => [
				$task_table.'.state' => 2,
				'date' => ['>=', $begin],
				'AND' => [
					'date' => ['<=', $end],
				]
			],
			'ORDER' => ["total DESC"],
			'GROUP' => ['users_id_tech'],
			'LIMIT' => 20,
		];

		$techs_id = [];
		foreach ($DB->request($query) as $result) {
			$techs_id[] = $result['users_id_tech'];
		}

		if (count($techs_id) > 0) {
			$sql = [
				'SELECT' => [
					new QueryExpression(
						"FROM_UNIXTIME(UNIX_TIMESTAMP(" . $DB->quoteName("$task_table.date") . "),'%Y-%m-%d') AS period"
					),
					'SUM' => 'actual_actiontime AS total',
					'users_id_tech',
				],
				'FROM' => $actualtime_table,
				'INNER JOIN' => [
					$task_table => [
						'ON' => [
							$task_table => 'id',
							$actualtime_table => 'tickettasks_id'
						]
					]
				],
				'WHERE' => [
					$task_table.'.state' => 2,
					'users_id_tech' => $techs_id,
					'date' => ['>=', $begin],
					'AND' => [
						'date' => ['<=', $end],
					]
				],
				'ORDER' => ['period DESC', "total DESC"],
				'GROUP' => ['period', "users_id_tech"],
			];

			$tmp = [];
			foreach ($DB->request($sql) as $result) {
				if (!in_array($result['period'], $data['labels'])) {
					$data['labels'][] = $result['period'];
				}
				$tmp[$result['users_id_tech']][$result['period']] = $result['total'];
			}
			sort($data['labels']);

			foreach ($tmp as $key => $value) {
				$aux = [];
				$aux['name'] = getUserName($key);
				foreach ($data['labels'] as $id => $period) {
					if (array_key_exists($period, $value)) {
						$aux['data'][] = [
							'value' => round($value[$period]/HOUR_TIMESTAMP,2),
						];
					} else {
						$aux['data'][] = [
							'value' => 0,
						];
					}
				}
				$data['series'][] = $aux;
			}
		}

		return [
			'data' => $data,
			'label' => $params['label'],
			'icon' => 'fa-solid fa-stopwatcht'
		];
	}

	static function lessActualtimeUsageByDay($params = [])
	{
		$DB = DBConnection::getReadConnection();

		$data = [
			'labels' => [],
			'series' => []
		];

		$year   = date("Y") - 15;
		$begin  = date("Y-m-d", mktime(1, 0, 0, (int)date("m"), (int)date("d"), $year));
		$end    = date("Y-m-d");

		if (isset($params['apply_filters']['dates']) && count($params['apply_filters']['dates']) == 2) {
			$begin = date("Y-m-d", strtotime($params['apply_filters']['dates'][0]));
			$end   = date("Y-m-d", strtotime($params['apply_filters']['dates'][1]));
			unset($params['apply_filters']['dates']);
		}

		$task_table = TicketTask::getTable();
		$actualtime_table = PluginActualtimeTask::getTable();
		$table = Ticket::getTable();

		$query = [
			'SELECT' => [
				'SUM' => $actualtime_table.'.actual_actiontime AS total',
				'users_id_tech'
			],
			'FROM' => $actualtime_table,
			'INNER JOIN' => [
				$task_table => [
					'ON' => [
						$task_table => 'id',
						$actualtime_table => 'tickettasks_id'
					]
				]
			],
			'WHERE' => [
				$task_table.'.state' => 2,
				'date' => ['>=', $begin],
				'AND' => [
					'date' => ['<=', $end],
				]
			],
			'ORDER' => ["total ASC"],
			'GROUP' => ['users_id_tech'],
			'LIMIT' => 20,
		];

		$techs_id = [];
		foreach ($DB->request($query) as $result) {
			$techs_id[] = $result['users_id_tech'];
		}

		if (count($techs_id) > 0) {
			$sql = [
				'SELECT' => [
					new QueryExpression(
						"FROM_UNIXTIME(UNIX_TIMESTAMP(" . $DB->quoteName("$task_table.date") . "),'%Y-%m-%d') AS period"
					),
					'SUM' => 'actual_actiontime AS total',
					'users_id_tech',
				],
				'FROM' => $actualtime_table,
				'INNER JOIN' => [
					$task_table => [
						'ON' => [
							$task_table => 'id',
							$actualtime_table => 'tickettasks_id'
						]
					]
				],
				'WHERE' => [
					$task_table.'.state' => 2,
					'users_id_tech' => $techs_id,
					'date' => ['>=', $begin],
					'AND' => [
						'date' => ['<=', $end],
					]
				],
				'ORDER' => ['period DESC', "total DESC"],
				'GROUP' => ['period', "users_id_tech"],
			];

			$tmp = [];
			foreach ($DB->request($sql) as $result) {
				if (!in_array($result['period'], $data['labels'])) {
					$data['labels'][] = $result['period'];
				}
				$tmp[$result['users_id_tech']][$result['period']] = $result['total'];
			}
			sort($data['labels']);

			foreach ($tmp as $key => $value) {
				$aux = [];
				$aux['name'] = getUserName($key);
				foreach ($data['labels'] as $id => $period) {
					if (array_key_exists($period, $value)) {
						$aux['data'][] = [
							'value' => round($value[$period]/HOUR_TIMESTAMP,2),
						];
					} else {
						$aux['data'][] = [
							'value' => 0,
						];
					}
				}
				$data['series'][] = $aux;
			}
		}

		return [
			'data' => $data,
			'label' => $params['label'],
			'icon' => 'fa-solid fa-stopwatcht'
		];
	}

	static function morePercentageActualtimeTasksByDay($params = [])
	{
		$DB = DBConnection::getReadConnection();

		$data = [
			'labels' => [],
			'series' => []
		];

		$year   = date("Y") - 15;
		$begin  = date("Y-m-d", mktime(1, 0, 0, (int)date("m"), (int)date("d"), $year));
		$end    = date("Y-m-d");

		if (isset($params['apply_filters']['dates']) && count($params['apply_filters']['dates']) == 2) {
			$begin = date("Y-m-d", strtotime($params['apply_filters']['dates'][0]));
			$end   = date("Y-m-d", strtotime($params['apply_filters']['dates'][1]));
			unset($params['apply_filters']['dates']);
		}

		$task_table = TicketTask::getTable();
		$actualtime_table = PluginActualtimeTask::getTable();
		$table = Ticket::getTable();

		$sql = [
			'SELECT' => [
				"COUNT DISTINCT" => $task_table . ".id AS nb_task",
				$task_table . ".users_id_tech",
			],
			'FROM' => $task_table,
			'INNER JOIN' => [
				$actualtime_table => [
					'FKEY' => [
						$task_table => 'id',
						$actualtime_table => 'tickettasks_id',
					]
				],
				$table => [
					'FKEY' => [
						$table => 'id',
						$task_table => 'tickets_id'
					]
				]
			],
			'WHERE' => [
				$task_table . '.state' => 2,
				$actualtime_table . '.actual_begin' => ['>=', $begin],
				$actualtime_table . '.actual_end' => ['<=', $end],
			] + getEntitiesRestrictCriteria($table),
			'ORDER' => ["nb_task DESC"],
			'GROUP' => ['users_id_tech'],
			'LIMIT' => 20
		];

		$techs_id = [];
		foreach ($DB->request($sql) as $result) {
			$techs_id[] = $result['users_id_tech'];
		}

		if (count($techs_id) > 0) {
			$query = [
				'SELECT' => [
					new QueryExpression(
						"FROM_UNIXTIME(UNIX_TIMESTAMP(" . $DB->quoteName("$task_table.date") . "),'%Y-%m-%d') AS period"
					),
					"COUNT DISTINCT" => $task_table . ".id AS nb_task",
					$task_table . ".users_id_tech AS tech",
				],
				'FROM' => $task_table,
				'INNER JOIN' => [
					$actualtime_table => [
						'FKEY' => [
							$task_table => 'id',
							$actualtime_table => 'tickettasks_id',
						]
					],
					$table => [
						'FKEY' => [
							$table => 'id',
							$task_table => 'tickets_id'
						]
					]
				],
				'WHERE' => [
					$task_table . '.state' => 2,
					$actualtime_table . '.actual_begin' => ['>=', $begin],
					$actualtime_table . '.actual_end' => ['<=', $end],
					$task_table . ".users_id_tech" => $techs_id,
				] + getEntitiesRestrictCriteria($table),
				'ORDER' => ['period DESC', "nb_task DESC"],
				'GROUP' => ['period', "tech"],
			];

			$tmp = [];
			foreach ($DB->request($query) as $result) {
				if (!in_array($result['period'], $data['labels'])) {
					$data['labels'][] = $result['period'];
				}
				$tmp[$result['tech']][$result['period']] = $result['nb_task'];
			}
			sort($data['labels']);

			foreach ($tmp as $key => $value) {
				$aux = [];
				$aux['name'] = getUserName($key);
				foreach ($data['labels'] as $id => $period) {
					$sqltotal = [
						'SELECT' => [
							"COUNT DISTINCT" => $task_table . ".id AS nb_task",
						],
						'FROM' => $task_table,
						'INNER JOIN' => [
							$table => [
								'FKEY' => [
									$table => 'id',
									$task_table => 'tickets_id'
								]
							]
						],
						'WHERE' => [
							$task_table . '.state' => 2,
							$task_table . ".users_id_tech" => $key,
							$task_table . ".date" => ['LIKE', $period . '%']
						] + getEntitiesRestrictCriteria($table),
					];
					$total = 0;
					$req = $DB->request($sqltotal);
					if ($row = $req->next()) {
						$total = $row['nb_task'];
					}
					if (array_key_exists($period, $value) && $total > 0) {
						$aux['data'][] = [
							'value' => round(($value[$period] / $total) * 100, 2),
						];
					} else {
						$aux['data'][] = [
							'value' => 0,
						];
					}
				}
				$data['series'][] = $aux;
			}
		}

		return [
			'data' => $data,
			'label' => $params['label'],
			'icon' => 'fa-solid fa-stopwatch'
		];
	}

	static function lessPercentageActualtimeTasksByDay($params = [])
	{
		$DB = DBConnection::getReadConnection();

		$data = [
			'labels' => [],
			'series' => []
		];

		/*$year   = date("Y") - 15;
		$begin  = date("Y-m-d", mktime(1, 0, 0, (int)date("m"), (int)date("d"), $year));
		$end    = date("Y-m-d");

		if (isset($params['apply_filters']['dates']) && count($params['apply_filters']['dates']) == 2) {
			$begin = date("Y-m-d", strtotime($params['apply_filters']['dates'][0]));
			$end   = date("Y-m-d", strtotime($params['apply_filters']['dates'][1]));
			unset($params['apply_filters']['dates']);
		}

		$task_table = TicketTask::getTable();
		$actualtime_table = PluginActualtimeTask::getTable();
		$table = Ticket::getTable();

		$sql = [
			'SELECT' => [
				"COUNT DISTINCT" => $task_table . ".id AS nb_task",
				$task_table . ".users_id_tech",
			],
			'FROM' => $task_table,
			'INNER JOIN' => [
				$actualtime_table => [
					'FKEY' => [
						$task_table => 'id',
						$actualtime_table => 'tickettasks_id',
					]
				],
				$table => [
					'FKEY' => [
						$table => 'id',
						$task_table => 'tickets_id'
					]
				]
			],
			'WHERE' => [
				$task_table . '.state' => 2,
				$actualtime_table . '.actual_begin' => ['>=', $begin],
				$actualtime_table . '.actual_end' => ['<=', $end],
			] + getEntitiesRestrictCriteria($table),
			'ORDER' => ["nb_task ASC"],
			'GROUP' => ['users_id_tech'],
			'HAVING' => [
				'nb_task' => ['>', 0],
			],
			'LIMIT' => 20
		];

		$techs_id = [];
		foreach ($DB->request($sql) as $result) {
			$techs_id[] = $result['users_id_tech'];
		}

		if (count($techs_id) > 0) {
			$query = [
				'SELECT' => [
					new QueryExpression(
						"FROM_UNIXTIME(UNIX_TIMESTAMP(" . $DB->quoteName("$task_table.date") . "),'%Y-%m-%d') AS period"
					),
					"COUNT DISTINCT" => $task_table . ".id AS nb_task",
					$task_table . ".users_id_tech AS tech",
				],
				'FROM' => $task_table,
				'INNER JOIN' => [
					$table => [
						'FKEY' => [
							$table => 'id',
							$task_table => 'tickets_id'
						]
					]
				],
				'WHERE' => [
					$task_table . '.state' => 2,
					$task_table . ".users_id_tech" => $techs_id,
					$actualtime_table . '.actual_begin' => ['>=', $begin],
					$actualtime_table . '.actual_end' => ['<=', $end],
				] + getEntitiesRestrictCriteria($table),
				'ORDER' => ['period DESC', "nb_task DESC"],
				'GROUP' => ['period', "tech"],
			];

			$tmp = [];
			foreach ($DB->request($query) as $result) {
				if (!in_array($result['period'], $data['labels'])) {
					$data['labels'][] = $result['period'];
				}
				$tmp[$result['tech']][$result['period']] = $result['nb_task'];
			}
			sort($data['labels']);

			foreach ($tmp as $key => $value) {
				$aux = [];
				$aux['name'] = getUserName($key);
				foreach ($data['labels'] as $id => $period) {
					$sqltotal = [
						'SELECT' => [
							"COUNT DISTINCT" => $task_table . ".id AS nb_task",
						],
						'FROM' => $task_table,
						'INNER JOIN' => [
							$actualtime_table => [
								'FKEY' => [
									$task_table => 'id',
									$actualtime_table => 'tickettasks_id',
								]
							],
							$table => [
								'FKEY' => [
									$table => 'id',
									$task_table => 'tickets_id'
								]
							]
						],
						'WHERE' => [
							$task_table . '.state' => 2,
							$task_table . ".users_id_tech" => $key,
							$task_table . ".date" => ['LIKE', $period . '%']
						] + getEntitiesRestrictCriteria($table),
					];
					$total = 0;
					$req = $DB->request($sqltotal);
					if ($row = $req->next()) {
						$total = $row['nb_task'];
					}
					if (array_key_exists($period, $value)) {
						$aux['data'][] = [
							'value' => round(($total / $value[$period]) * 100, 2),
						];
					} else {
						$aux['data'][] = [
							'value' => 0,
						];
					}
				}
				$data['series'][] = $aux;
			}
		}*/

		return [
			'data' => $data,
			'label' => $params['label'],
			'icon' => 'fa-solid fa-stopwatch'
		];
	}
}
