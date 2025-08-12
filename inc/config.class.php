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

use Glpi\Application\View\TemplateRenderer;

/**
 * Class PluginActualtimeConfig
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
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
        $config = new self();
        $displayvalues = [
            0 => __('In Standard interface only (default)', 'actualtime'),
            1 => __('Both in Standard and Helpdesk interfaces', 'actualtime'),
        ];

        $template = "@actualtime/forms/config.html.twig";
        TemplateRenderer::getInstance()->display($template, [
            'item'          => $config,
            'displayvalues' => $displayvalues,
            'options'       => [
                'full_width' => true,
            ],
        ]);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string|array
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
            return self::showConfigForm();
        }

        return false;
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

        $default_charset    = DBConnection::getDefaultCharset();
        $default_collation  = DBConnection::getDefaultCollation();
        $default_key_sign   = DBConnection::getDefaultPrimaryKeySignOption();

        $table = self::getTable();
        $config = new self();
        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");
            $query = "CREATE TABLE IF NOT EXISTS $table (
                `id` INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `displayinfofor` SMALLINT NOT NULL DEFAULT '0',
                `showtimerpopup` TINYINT NOT NULL DEFAULT '1',
                `showtimerinbox` TINYINT NOT NULL DEFAULT '1',
                `autoopenrunning` TINYINT NOT NULL DEFAULT '0',
                `autoupdate_duration` TINYINT NOT NULL DEFAULT '0',
                `planned_task` TINYINT NOT NULL DEFAULT '0',
                `multiple_day` TINYINT NOT NULL DEFAULT '0',
                `daily_limit` INT NOT NULL DEFAULT '8',
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset}
            COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);
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
            // * 3.2.2
            // daily limit in hours for actualtime
            $migration->addField($table, 'daily_limit', 'int', ['value' => 8]);

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
