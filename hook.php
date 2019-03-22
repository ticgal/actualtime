<?php
/**
 * Install all necessary elements for the plugin
 *
 * @return boolean True if success
 */
function plugin_actualtime_install() {

   $migration = new Migration(PLUGIN_ACTUALTIME_VERSION);

   // Parse inc directory
   foreach (glob(dirname(__FILE__).'/inc/*') as $filepath) {
      // Load *.class.php files and get the class name
      if (preg_match("/inc.(.+)\.class.php/", $filepath, $matches)) {
         $classname = 'PluginActualtime' . ucfirst($matches[1]);
         include_once($filepath);
         // If the install method exists, load it
         if (method_exists($classname, 'install')) {
            $classname::install($migration);
         }
      }
   }

   // Execute the whole migration
   $migration->executeMigration();

   return true;
}

function plugin_actualtime_item_stats($item) {
   PluginActualtimeTask::showStats($item);
}

function plugin_actualtime_item_update($item) {
   PluginActualtimeTask::preUpdate($item);
}

function plugin_actualtime_item_add($item) {
   PluginActualtimeTask::afterAdd($item);
}

/**
 * Uninstall previously installed elements of the plugin
 *
 * @return boolean True if success
 */
function plugin_actualtime_uninstall() {

   $migration = new Migration(PLUGIN_ACTUALTIME_VERSION);

   // Parse inc directory
   foreach (glob(dirname(__FILE__).'/inc/*') as $filepath) {
      // Load *.class.php files and get the class name
      if (preg_match("/inc.(.+)\.class.php/", $filepath, $matches)) {
         $classname = 'PluginActualtime' . ucfirst($matches[1]);
         include_once($filepath);
         // If the install method exists, load it
         if (method_exists($classname, 'uninstall')) {
            $classname::uninstall($migration);
         }
      }
   }

   // Execute the whole migration
   $migration->executeMigration();

   return true;
}
