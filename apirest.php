<?php

define('GLPI_ROOT', substr(__DIR__,0,strpos(__DIR__,"/plugins")));
define('DO_NOT_CHECK_HTTP_REFERER', 1);
ini_set('session.use_cookies', 0);

include_once (GLPI_ROOT . "/inc/based_config.php");
include_once(GLPI_ROOT."/plugins/actualtime/inc/apirest.class.php");

$GLPI_CACHE = Config::getCache('cache_db');

$api = new PluginActualtimeApirest();
$api->call();