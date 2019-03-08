<?php
define ('PLUGIN_ACTUALTIME_VERSION', '1.1.0');
// Minimal GLPI version, inclusive
define("PLUGIN_ACTUALTIME_MIN_GLPI", "9.3.0");
// Maximum GLPI version, exclusive
define("PLUGIN_ACTUALTIME_MAX_GLPI", "9.5");

function plugin_version_actualtime() {
   return ['name'       => 'ActualTime',
      'version'        => PLUGIN_ACTUALTIME_VERSION,
      'author'         => '<a href="https://tic.gal">TICgal</a>',
      'homepage'       => 'https://tic.gal/en/project/actualtime-plugin-glpi/',
      'license'        => 'AGPLv3+',
      'requirements'   => [
         'glpi'   => [
            'min' => PLUGIN_ACTUALTIME_MIN_GLPI,
            // Allow all version from PLUGIN_ACTUALTIME_MIN_GLPI
            //'max' => PLUGIN_ACTUALTIME_MAX_GLPI,
         ]
      ]];
}

/**
 * Check plugin's prerequisites before installation
 */
function plugin_actualtime_check_prerequisites() {
   $version = preg_replace('/^((\d+\.?)+).*$/', '$1', GLPI_VERSION);
   // Devel version allowed
   if ($version == '10.0.0') {
      return true;
   }
   $matchMinGlpiReq = version_compare($version, PLUGIN_ACTUALTIME_MIN_GLPI, 'ge');
   $matchMaxGlpiReq = version_compare($version, PLUGIN_ACTUALTIME_MAX_GLPI, 'lt');
   if (!$matchMinGlpiReq || !$matchMaxGlpiReq) {
      if (method_exists('Plugin', 'messageIncompatible')) {
         //since GLPI 9.2
         Plugin::messageIncompatible('core', PLUGIN_ACTUALTIME_MIN_GLPI, PLUGIN_ACTUALTIME_MAX_GLPI);
      } else {
         echo vsprintf(
            'This plugin requires GLPI >= %1$s and < %2$s.',
            [
               PLUGIN_ACTUALTIME_MIN_GLPI,
               PLUGIN_ACTUALTIME_MAX_GLPI,
            ]
         );
      }
      return false;
   }
   return true;
}

/**
 * Check plugin's config before activation
 */
function plugin_actualtime_check_config($verbose = false) {
   return true;
}

function plugin_init_actualtime() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['actualtime'] = true;

   $PLUGIN_HOOKS['post_item_form']['actualtime'] = ['PluginActualtimeTask', 'postForm'];
   $PLUGIN_HOOKS['show_item_stats']['actualtime'] = ['Ticket'=> 'plugin_activetime_item_stats'];
   $PLUGIN_HOOKS['pre_item_update']['actualtime'] = ['TicketTask'=>'plugin_activetime_item_update'];
   $PLUGIN_HOOKS['add_javascript']['actualtime'] = "js/actualtime.js";

}
