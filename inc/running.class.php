<?php

class PluginActualtimeRunning extends CommonGLPI {
	
	static function getMenuName(){
		return __("Actualtime","actualtime");
	}

	static function getMenuContent(){
		$menu=[
			'title'=>self::getMenuName(),
			'page'=>self::getSearchURL(false),
			'icon'=>'fas fa-stopwatch'
		];

		return $menu;
	}

	static public function show(){
		global $DB;

		echo "<div class='center'>";
		echo "<h1>".__("Running timers","actualtime")." <i id='refresh' class='fa fa-sync pointer' ></i></h1>";
		echo "</div>";
		echo "<div id='running'>";
		echo "<div>";
		$script=<<<JAVASCRIPT
		$(document).ready(function() {
			setInterval(loadRunning,5000);

			function loadRunning(){
				$.ajax({
					type:'POST',
					url:CFG_GLPI.root_doc+"/"+GLPI_PLUGINS_PATH.actualtime+"/ajax/running.php",
					data:{
						action:'getlist'
					},
					success:function(data){
						$('#running').html(data);
					}
				});
			}
			loadRunning();
			$('#refresh').click(function(){
				loadRunning();
			});
		});
JAVASCRIPT;
		 echo Html::scriptBlock($script);
	}

	static function listRunning(){
		global $DB;

		if (countElementsInTable(PluginActualtimeTask::getTable(),[['NOT' => ['actual_begin' => null],],'actual_end'=>null,])>0) {
			$query=[
				'FROM'=>PluginActualtimeTask::getTable(),
				'WHERE'=>[
					[
						'NOT' => ['actual_begin' => null],
					],
					'actual_end'=>null,
				]
			];
			$html= "<table class='tab_cadre_fixehov'>";
			$html.= "<tr>";
			$html.= "<th class='center'>".__("Technician")."</th>";
			$html.= "<th class='center'>".__("Entity")."</th>";
			$html.= "<th class='center'>".__("Ticket")."-".__("Task")."</th>";
			$html.= "<th class='center'>".__("Time")."</th>";
			$html.= "</tr>";

			foreach ($DB->request($query) as $key => $row) {
				$html.= "<tr class='tab_bg_2'>";
				$user=new User();
				$user->getFromDB($row['users_id']);
				$html.= "<td class='center'><a href='".$user->getLinkURL()."'>".$user->getFriendlyName()."</a></td>";
				$task_id=$row['tasks_id'];
				$task=new TicketTask();
				$task->getFromDB($row['tasks_id']);
				$ticket=new Ticket();
				$ticket->getFromDB($task->fields['tickets_id']);
				$html.= "<td class='center'>".Entity::getFriendlyNameById($ticket->fields['entities_id'])."</td>";
				$html.= "<td class='center'><a href='".$ticket->getLinkURL()."'>".$ticket->getID()." - ".$task->getID()."</a></td>";
				$html.= "<td class='center'>".HTML::timestampToString(PluginActualtimeTask::totalEndTime($row['tasks_id']))."</td>";
				$html.= "</tr>";
			}
			$html.= "</table>";
		} else {
			$html= "<div><p class='center b'>".__('No timer active')."</p></div>";
		}
		return $html;
	}
}