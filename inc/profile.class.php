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

class PluginActualtimeProfile extends Profile
{
	static $rightname = 'profile';

	function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
	{
		switch ($item->getType()) {
			case 'Profile':
				return self::createTabEntry('Actualtime');
				break;
		}
	}

	static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
	{
		switch ($item->getType()) {
			case 'Profile':
				$profile = new self();
				$profile->showForm($item->getID());
				break;
		}
		return true;
	}

	function showForm($profiles_id, $options = [])
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

		$profile->showLegend();
		if ($canedit) {
			echo "<div class='center'>";
			echo Html::hidden('id', ['value' => $profiles_id]);
			echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
			echo "</div>\n";
			Html::closeForm();
		}
		echo "</div>";
	}

	public static function getGeneralRights()
	{
		return [[
			'rights' => [READ => __('Read')],
			'label' => __("Running timers", "actualtime"),
			'field' => 'plugin_actualtime_running'
		]];
	}

	static function uninstall()
	{
		global $DB;

		$table = ProfileRight::getTable();
		$query = "DELETE FROM $table WHERE `name` LIKE '%plugin_actualtime%'";
		$DB->query($query) or die($DB->error());
	}
}
