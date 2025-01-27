<?php

use Glpi\Cache\CacheManager;

if (strpos(__DIR__, "plugins")) {
    define('GLPI_ROOT', substr(__DIR__, 0, (strpos(__DIR__, "plugins") - 1)));
} elseif (strpos(__DIR__, "marketplace")) {
    define('GLPI_ROOT', substr(__DIR__, 0, (strpos(__DIR__, "marketplace") - 1)));
}
define('DO_NOT_CHECK_HTTP_REFERER', 1);
ini_set('session.use_cookies', 0);

include_once (GLPI_ROOT . "/inc/based_config.php");
include_once(Plugin::getPhpDir('actualtime')."/inc/apirest.class.php");

// Init loggers
$GLPI = new GLPI();
$GLPI->initLogger();
$GLPI->initErrorHandler();

//init cache
$cache_manager = new CacheManager();
$GLPI_CACHE = $cache_manager->getCoreCacheInstance();

$api = new PluginActualtimeApirest();
$api->call();
