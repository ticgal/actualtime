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

/**
 * PHPUnit bootstrap.
 *
 * Runs against an installed GLPI 12 (config/config_db.php), the way the shared plugin CI does it:
 * GLPI installed with `database:install`, the plugin installed and activated, then
 * `vendor/bin/phpunit` from the plugin directory. GLPI core's own tests/bootstrap.php is not
 * usable here: it needs the "testing" environment and its dataset.
 */

use Composer\Autoload\ClassLoader;
use Glpi\Kernel\Kernel;

$glpi_root = getenv('GLPI_ROOT') ?: dirname(__DIR__, 3);
if (!is_file($glpi_root . '/vendor/autoload.php')) {
    echo "GLPI root not found, set GLPI_ROOT.\n";
    exit(1);
}
chdir($glpi_root);

// Only GLPI's autoloader: the plugin's own vendor/ holds dev tools (symfony/console...) that would
// redeclare core classes. GLPI registers the plugin's src/ itself, as it does in production.
require_once $glpi_root . '/vendor/autoload.php';
$tests_autoloader = new ClassLoader();
$tests_autoloader->addPsr4('GlpiPlugin\\Actualtime\\Tests\\', __DIR__ . '/');
$tests_autoloader->register();

// PHPUnit includes this file from inside a method: make the kernel the global that core reads
// (TemplateRenderer::getInstance())
global $kernel;
$kernel = new Kernel();
$kernel->boot();

// Log in as the super-admin the way core's tests do (Session::init(), no password check)
$auth = new Auth();
$auth->user = new User();
if (!$auth->user->getFromDBbyName('glpi')) {
    echo "User 'glpi' not found.\n";
    exit(1);
}
$auth->auth_succeded = true;
$auth->setAuthType((int) $auth->user->fields['authtype']);
Session::init($auth);

// Install and activate the plugin when the runner did not do it (local runs)
$plugin = new Plugin();
if (!Plugin::isPluginActive('actualtime')) {
    $plugin->checkPluginState('actualtime');
    $plugin->getFromDBByCrit(['directory' => 'actualtime']);
    $plugin->install($plugin->fields['id']);
    $plugin->activate($plugin->fields['id']);
    // activate() only updates the DB row: refresh the in-memory state loaded at boot
    $plugin->bootPlugins();
    $plugin->init();
    Session::init($auth);
}

if (!Plugin::isPluginActive('actualtime')) {
    echo "ActualTime plugin could not be activated.\n";
    exit(1);
}
