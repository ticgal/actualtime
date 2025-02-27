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

$USEDBREPLICATE = 1;
$DBCONNECTION_REQUIRED = 0;

include("../../../../inc/includes.php");

$report = new PluginReportsAutoReport(__('ActualTimeUser'));
//Filtro fecha
new PluginReportsDateIntervalCriteria(
    $report,
    'glpi_tickets.closedate',
    __("Close date")
);
//Filtro user
$choices = [
    0 => __('Technician'),
    1 => __('Requester')
];
$filter_active = new PluginReportsArrayCriteria(
    $report,
    'glpi_tickets_users.type',
    _('Group by'),
    $choices
);

$report->displayCriteriasForm();
$report->setColumns([
    new PluginReportsColumnLink(
        'user_id',
        __('User'),
        'User',
        [
            'with_navigate' => true
        ]
    ),
    new PluginReportsColumnTimestamp(
        'duration',
        __("Total duration")
    ),
    new PluginReportsColumnTimestamp(
        'totalduration',
        "ActualTime - " . __("Total duration")
    ),
    new PluginReportsColumnTimestamp(
        'diff',
        __(
            "Duration Diff",
            "actiontime"
        )
    ),
    new PluginReportsColumn(
        'diffpercent',
        __("Duration Diff", "actiontime") . " (%)"
    )
]);
if ($filter_active->getParameterValue() == 1) {
    $query = "SELECT glpi_tickets_users.users_id as user_id,";
    $group = "
AND glpi_tickets_users.type=1
GROUP BY glpi_tickets_users.users_id";
} else {
    $query = "SELECT glpi_tickettasks.users_id_tech as user_id,";
    $group = "
GROUP BY glpi_tickettasks.users_id_tech";
}
$report->delCriteria('glpi_tickets_users.type');
$query .= "
    sum(glpi_tickettasks.actiontime) AS duration,
    sum(actual_actiontime) AS totalduration,
    (sum(glpi_tickettasks.actiontime) - sum(actual_actiontime)) AS diff,
    concat(round(((sum(glpi_tickettasks.actiontime) - sum(actual_actiontime)) / sum(actual_actiontime) * 100 ),2),'%') AS diffpercent
FROM glpi_plugin_actualtime_tasks
    RIGHT JOIN glpi_tickettasks ON glpi_tickettasks.id = glpi_plugin_actualtime_tasks.tasks_id
    INNER JOIN glpi_tickets ON glpi_tickets.id = glpi_tickettasks.tickets_id
    INNER JOIN glpi_tickets_users ON glpi_tickets_users.tickets_id = glpi_tickets.id
WHERE status = 6
";
$query .= $report->addSqlCriteriasRestriction();
$query .= $group;

$report->setSqlRequest($query);
$report->execute();
