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

include('../../../inc/includes.php');

global $CFG_GLPI;
// Check if plugin is activated...
$plugin = new Plugin();
if (!$plugin->isInstalled('actualtime') || !$plugin->isActivated('actualtime')) {
   Html::displayNotFoundError();
}

Session::checkRight('config', UPDATE);

$config = new PluginActualtimeConfig();

if (isset($_POST["update"])) {
   $config->update($_POST);

   PluginActualtimeConfig::getConfig(true);
   Html::back();

} else {
   Html::redirect($CFG_GLPI["root_doc"]."/front/config.form.php?forcetab=".
      urlencode('PluginActualtimeConfig$1'));
}
