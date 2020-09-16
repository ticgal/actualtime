<?php

class PluginActualtimeRunning extends CommonGLPI {
	
	static function getMenuName(){
		return __("Actualtime","actualtime");
	}

	static function getMenuContent(){
		$menu=[
			'title'=>self::getMenuName(),
			'page'=>self::getSearchURL(false),
		];

		return $menu;
	}
}