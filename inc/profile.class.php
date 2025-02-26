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

class PluginActualtimeProfile extends Profile
{
    public static $rightname = 'profile';

    /**
     * {@inheritDoc}
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        switch ($item->getType()) {
            case 'Profile':
                return self::createTabEntry(PLUGIN_ACTUALTIME_NAME);
        }
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        switch ($item->getType()) {
            case Profile::class:
                /** @var Profile $item */
                $profile = new self();
                $profile->showForm($item->getID());
                break;
        }
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function showForm($profiles_id, array $options = []): bool
    {
        if (!Session::haveRight("profile", READ)) {
            return false;
        }
        $canedit = Session::haveRight("profile", UPDATE);

        $profile = new Profile();
        $profile->getFromDB($profiles_id);

        echo "<form action='" . Profile::getFormUrl() . "' method='post'>";
        echo "<table class='tab_cadre_fixe'>";

        $general_rights = self::getGeneralRights();

        $profile->displayRightsChoiceMatrix(
            $general_rights,
            [
                'canedit'       => $canedit,
                'default_class' => 'tab_bg_2',
                'title'         => __('General', 'actualtime')
            ]
        );

        if ($profile->fields['interface'] == 'central') {
            $profile->displayRightsChoiceMatrix(
                self::getCentralRights(),
                [
                    'canedit'       => $canedit,
                    'default_class' => 'tab_bg_2',
                ]
            );
        }

        $profile->showLegend();
        if ($canedit) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $profiles_id]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
            echo "</div>\n";
            Html::closeForm();
        }
        echo "</div>";

        return true;
    }

    /**
     * getGeneralRights
     *
     * @return array
     */
    public static function getGeneralRights(): array
    {
        return [
            [
                'rights' => [READ => __('Read')],
                'label' => __("Running timers", "actualtime"),
                'field' => 'plugin_actualtime_running'
            ]
        ];
    }

    /**
     * getCentralRights
     *
     * @return array
     */
    public static function getCentralRights(): array
    {
        return [
            [
                'itemtype' => PluginActualtimeSourcetimer::getType(),
                'label' => __("Modify timers", "actualtime"),
                'field' => PluginActualtimeSourcetimer::$rightname
            ]
        ];
    }

    /**
     * uninstall
     *
     * @param Migration $migration
     * @return void
     */
    public static function uninstall(Migration $migration): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $migration->displayMessage("Deleting actualtime profile rights");
        $table = ProfileRight::getTable();
        $query = "DELETE FROM $table WHERE `name` LIKE '%plugin_actualtime%'";
        $DB->doQueryOrDie($query, $DB->error());
    }
}
