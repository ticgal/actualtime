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

/**
 * Class PluginActualtimeConfig
 */
class PluginActualtimeConfig extends CommonDBTM
{
    public static $rightname = 'config';

    private static $instance = null;

    /**
     * {@inheritDoc}
     */
    public function __construct()
    {
        /** @var \DBmysql $DB */
        global $DB;

        if ($DB->tableExists($this->getTable())) {
            $this->getFromDB(1);
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function getTypeName($nb = 0): string
    {
        return __("ActualTime Setup", "actualtime");
    }

    /**
     * getInstance
     *
     * @return PluginActualtimeConfig
     */
    public static function getInstance(): PluginActualtimeConfig
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
            if (!self::$instance->getFromDB(1)) {
                self::$instance->getEmpty();
            }
        }
        return self::$instance;
    }

    /**
     * showConfigForm
     *
     * @return bool
     */
    public static function showConfigForm(): bool
    {
        $rand = mt_rand();

        $config = new self();
        $config->getFromDB(1);

        $config->showFormHeader(['colspan' => 4]);

        $values = [
            0 => __('In Standard interface only (default)', 'actualtime'),
            1 => __('Both in Standard and Helpdesk interfaces', 'actualtime'),
        ];
        echo "<table class='tab_cadre_fixe'><thead>";
        echo "<th colspan='4'>" . self::getTypeName() . '</th></thead>';

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __("Enable timer on tasks", "actualtime") . "</td><td>";
        Dropdown::showFromArray(
            'displayinfofor',
            $values,
            [
                'value' => $config->fields['displayinfofor']
            ]
        );
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __("Display pop-up window with current running timer", "actualtime") . "</td><td>";
        Dropdown::showYesNo('showtimerpopup', $config->showTimerPopup(), -1);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __("Display actual time in closed task box ('Processing ticket' list)", "actualtime") . "</td><td>";
        Dropdown::showYesNo('showtimerinbox', $config->showTimerInBox(), -1);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1' name='optional$rand'>";
        echo "<td>" . __("Automatically open task with timer running", "actualtime") . "</td><td>";
        Dropdown::showYesNo('autoopenrunning', $config->autoOpenRunning(), -1);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1' name='optional$rand'>";
        echo "<td>" . __("Automatically update the duration", "actualtime") . "</td><td>";
        Dropdown::showYesNo('autoupdate_duration', $config->autoUpdateDuration(), -1);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1' name='optional$rand'>";
        echo "<td>" . __("Enable Timer Only on Scheduled Task Day", "actualtime") . "</td><td>";
        Dropdown::showYesNo('planned_task', $config->fields['planned_task'], -1);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1' name='optional$rand'>";
        echo "<td>" . __("Enable Timer Only on Task's Start Day", "actualtime") . "</td><td>";
        Dropdown::showYesNo('multiple_day', $config->fields['multiple_day'], -1);
        echo "</td>";
        echo "</tr>";

        $config->showFormButtons(['candel' => false]);

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if ($item->getType() == 'Config') {
            return PLUGIN_ACTUALTIME_NAME;
        }
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item->getType() == 'Config') {
            self::showConfigForm();
        }
        return true;
    }

    /**
     * showTimerPopup
     * Is displaying timer pop-up on every page enabled in plugin settings?
     *
     * @return bool
     */
    public function showTimerPopup(): bool
    {
        return ($this->fields['showtimerpopup'] ? true : false);
    }

    /**
     * showInHelpdesk
     * Is actual time information (timers) shown also in Helpdesk interface?
     *
     * @return bool
     */
    public function showInHelpdesk(): bool
    {
        return ($this->fields['displayinfofor'] == 1);
    }

    /**
     * showTimerInBox
     * Is timer shown in closed task box at 'Actions historical' page?
     *
     * @return bool
     */
    public function showTimerInBox(): bool
    {
        return ($this->fields['showtimerinbox'] ? true : false);
    }

    /**
     * autoOpenRunning
     * Auto open the form for the task with a currently running timer
     * when listing tickets' tasks?
     *
     * @return bool
     */
    public function autoOpenRunning(): bool
    {
        return ($this->fields['autoopenrunning'] ? true : false);
    }

    /**
     * autoUpdateDuration
     * return numeric boolean
     *
     * @return bool
     */
    public function autoUpdateDuration(): bool
    {
        return $this->fields['autoupdate_duration'];
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
        $config = new self();
        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");

            $query = "CREATE TABLE IF NOT EXISTS $table (
                `id` int {$default_key_sign} NOT NULL auto_increment,
                `displayinfofor` smallint NOT NULL DEFAULT '0',
                `showtimerpopup` TINYINT NOT NULL DEFAULT '1',
                `showtimerinbox` TINYINT NOT NULL DEFAULT '1',
                `autoopenrunning` TINYINT NOT NULL DEFAULT '0',
                `autoupdate_duration` TINYINT NOT NULL DEFAULT '0',
                `planned_task` TINYINT NOT NULL DEFAULT '0',
                `multiple_day` TINYINT NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset}
            COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->doQueryOrDie($query, $DB->error());
            $config->add([
                'id' => 1,
                'displayinfofor' => 0,
            ]);
        } else {
            $migration->changeField($table, 'showtimerpopup', 'showtimerpopup', 'bool', ['value' => 1]);
            $migration->changeField($table, 'showtimerinbox', 'showtimerinbox', 'bool', ['value' => 1]);
            $migration->changeField($table, 'autoopenrunning', 'autoopenrunning', 'bool', ['value' => 0]);
            $migration->dropField($table, 'autoopennew');

            $migration->addField($table, 'planned_task', 'bool');
            $migration->addField($table, 'multiple_day', 'bool');

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
        /** @var \DBmysql $DB */
        global $DB;

        $table = self::getTable();
        if ($DB->TableExists($table)) {
            $migration->displayMessage("Uninstalling $table");
            $migration->dropTable($table);
        }
    }
}
