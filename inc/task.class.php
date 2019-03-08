<?php
if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
*
*/
class PluginActualtimeTask extends CommonDBTM{

   public static $rightname = 'task';

   static function getTypeName($nb = 0) {
      return __('ActualTime', 'Actualtime');
   }

   static public function postForm($params) {
      global $CFG_GLPI;
      $item = $params['item'];
      $text_start=__("Start");
      $text_end=__("End");

      switch ($item->getType()) {
         case 'TicketTask':
            if ($item->getID()) {

               $rand = mt_rand();

               if ($item->getField('state')==1 && self::checkTech($item->getID())) {

                  $time=self::totalEndTime($item->getID());

                  if (self::checkTimerActive($item->getID())) {
                     $value=__("End");
                     $action="end";
                     $color="red";
                  } else {
                     $value=__("Start");
                     $action="start";
                     $color="green";
                  }

                  $html="<tr class='tab_bg_2'>";
                  $html.="<td colspan='2'></td>";
                  $html.="<td colspan='2'><div class='x-split-button'>";
                  $html.="<input type='button' id='actualtime$rand' name='update' task_id='".$item->getID()."'action='".$action."' value='".$value."' class='x-button x-button-main' style='background-color:".$color.";color:white'>";
                  $html.="</div></td></tr>";
                  $html.="<tr class='tab_bg_2'>";
                  $html.="<td class='center'>".__("Start date")."</td><td class='center'>".__("Partial actual duration", 'actualtime')."</td>";
                  $html.="<td>".__('Actual Duration', 'actualtime')." </td><td id='real_clock$rand'>".HTML::timestampToString($time)."</td>";
                  $html.="</tr>";
                  $html.="<tr id='actualtimeseg{$rand}'>";
                  $html.=self::getSegment($item->getID());
                  $html.="</tr>";
                  echo $html;

                  $ajax_url=$CFG_GLPI['root_doc']."/plugins/actualtime/ajax/timer.php";
                  $done=__('Done');

                  $script=<<<JAVASCRIPT
						$(document).ready(function(){
							var x;
							if (!$("#message_result").length) {
								$("body").append("<div id='message_result'></div>");
							}

                     if ($("#timer{$rand}").length) {
                        $("#timer{$rand}").remove();
                     }

							if ($("#actualtime{$rand}").attr("action")=='end') {
								startCount($("#actualtime{$rand}").attr("task_id"));
							}

							function startCount(id) {
								$('#real_clock{$rand}').css('color','red');
								jQuery.ajax({
									type: "POST",
									url: '{$ajax_url}',
									dataType: 'json',
									data: {action: 'count', task_id: id},
									success: function (result) {
										var time=result;
										x=setInterval(function(){
											time+=1;

											var text;
											var distance=time;
											var seconds = 0;
											var minutes = 0;
											var hours = 0;
											var days = 0;

											seconds = distance % 60;
											distance-=seconds;
											text=seconds + " s";

											if (distance>0) {
												minutes = (distance % 3600) / 60;
												distance-=minutes*60;
												text= minutes + " m " +seconds + " s";

												if (distance>0) {
													hours = (distance % 86400) / 3600;
													distance-=hours*3600;
													text= hours + " h " +minutes + " m " +seconds + " s";

													if (distance>0) {
														days = distance / 86400;
														text= days + " d " +hours + " h " +minutes + " m " +seconds + " s";

													}
												}
											}
											$('#real_clock{$rand}').text(text);
										},1000);
									},
								});
							}

							function endCount(realclk){
								clearInterval(x);
								// Correct real time clock with the actual data in database
								$('#real_clock{$rand}').html(realclk);
								$('#real_clock{$rand}').css('color','black');
							}

							$("#actualtime{$rand}").click(function(event){
								var id=$(this).attr("task_id");
								var val=$(this).attr("action");
								var time={$time};

								jQuery.ajax({
									type: "POST",
									url: '{$ajax_url}',
									dataType: 'json',
									data: {action: val, task_id: id},
									success: function (result) {
										if (val=='end' && result['class']=='info_msg') {
											$("table:has(#actualtime{$rand}) select[name='state']").val(2).trigger('change');
											$('#actualtime{$rand}').parentsUntil('.h_item','.h_content.TicketTask').find('span.state.state_1').toggleClass('state_1 state_2').attr('title','$done');
											$('#actualtime{$rand}').remove();
											endCount(result['realclock']);
											// Refresh table of partial time periods for this task
											$('#actualtimeseg{$rand}').html(result['html']);
										}else{
											if (val=='start' && result['class']=='info_msg') {
												$('#actualtime{$rand}').attr('action','end');
												$('#actualtime{$rand}').attr('value','{$text_end}');
												$('#actualtime{$rand}').css('background-color','red');
												startCount(id);
											}
										}
										$('#message_result').html(result['mensage']);
										$('#message_result').attr('title', result['title']);
										$(function() {
					                  var _of = window;
					                  var _at = 'right-20 bottom-20';
					                  //calculate relative dialog position
					                  $('.message_result').each(function() {
					                     var _this = $(this);
					                     if (_this.attr('aria-describedby') != 'message_result') {
					                        _of = _this;
					                        _at = 'right top-' + (10 + _this.outerHeight());
					                     }
					                  });

					                  $('#message_result').dialog({
					                     dialogClass: 'message_after_redirect '+result['class'],
					                     minHeight: 40,
					                     minWidth: 200,
					                     position: {
					                        my: 'right bottom',
					                        at: _at,
					                        of: _of,
					                        collision: 'none'
					                     },
					                     autoOpen: false,
					                     show: {
					                       effect: 'slide',
					                       direction: 'down',
					                       'duration': 800
					                     }
					                  })
					                  .dialog('open');

					                  $(document.body).on('click', function(e){
					                     if ($('#message_result').dialog('isOpen')
					                         && !$(e.target).is('.ui-dialog, a')
					                         && !$(e.target).closest('.ui-dialog').length) {
					                        $('#message_result').dialog('close');
					                        // redo focus on initial element
					                        e.target.focus();
					                     }
	                  				});
					               });
									},
								});
							});

						});
JAVASCRIPT;
                  echo Html::scriptBlock($script);
               } else {
                  $time=self::totalEndTime($item->getID());

                  $html="<tr class='tab_bg_2'>";
                  $html.="<td class='center'>".__("Start date")."</td><td class='center'>".__("Partial actual duration", 'actualtime')."</td>";
                  $html.="<td>".__('Actual Duration', 'actualtime')." </td><td id='real_clock$rand'>".HTML::timestampToString($time)."</td>";
                  $html.="</div></td></tr>";
                  $html.="<tr id='actualtimeseg{$rand}'>";
                  $html.=self::getSegment($item->getID());
                  $html.="</tr>";
                  echo $html;
               }
            }
            break;
      }

   }

   static function checkTech($task_id) {
      global $DB;

      $query=[
         'FROM'=>'glpi_tickettasks',
         'WHERE'=>[
            'id'=>$task_id,
            'users_id_tech'=>Session::getLoginUserID(),
         ]
      ];
      $req=$DB->request($query);
      if ($row=$req->next()) {
         return true;
      } else {
         return false;
      }
   }

   static function checkTimerActive($task_id) {
      global $DB;

      $query=[
         'FROM'=>self::getTable(),
         'WHERE'=>[
            'tasks_id'=>$task_id,
            [
               'NOT' => ['actual_begin' => null],
            ],
            'actual_end'=>null,
         ]
      ];
      $req=$DB->request($query);
      if ($row=$req->next()) {
         return true;
      } else {
         return false;
      }
   }

   static function totalEndTime($task_id) {
      global $DB;

      $query=[
         'FROM'=>self::getTable(),
         'WHERE'=>[
            'tasks_id'=>$task_id,
            [
               'NOT' => ['actual_begin' => null],
            ],
            [
               'NOT' => ['actual_end' => null],
            ],
         ]
      ];

      $seconds=0;
      foreach ($DB->request($query) as $id => $row) {
         $seconds+=$row['actual_actiontime'];
      }

      $querytime=[
         'FROM'=>self::getTable(),
         'WHERE'=>[
            'tasks_id'=>$task_id,
            [
               'NOT' => ['actual_begin' => null],
            ],
            'actual_end'=>null,
         ]
      ];

      $req=$DB->request($querytime);
      if ($row=$req->next()) {
         $seconds+=(strtotime("now")-strtotime($row['actual_begin']));
      }

      return $seconds;
   }

   static function checkUser($task_id, $user_id) {
      global $DB;

      $query=[
         'FROM'=>self::getTable(),
         'WHERE'=>[
            'tasks_id'=>$task_id,
            [
               'NOT' => ['actual_begin' => null],
            ],
            'actual_end'=>null,
            'users_id'=>$user_id,
         ]
      ];
      $req=$DB->request($query);
      if ($row=$req->next()) {
         return true;
      } else {
         return false;
      }
   }

   static function checkUserFree($user_id) {
      global $DB;

      $query=[
         'FROM'=>self::getTable(),
         'WHERE'=>[
            [
               'NOT' => ['actual_begin' => null],
            ],
            'actual_end'=>null,
            'users_id'=>$user_id,
         ]
      ];
      $req=$DB->request($query);
      if ($row=$req->next()) {
         return true;
      } else {
         return false;
      }
   }

   static function getTicket($user_id) {
      global $DB;

      $query=[
         'FROM'=>self::getTable(),
         'WHERE'=>[
            [
               'NOT' => ['actual_begin' => null],
            ],
            'actual_end'=>null,
            'users_id'=>$user_id,
         ]
      ];
      $req=$DB->request($query);
      $row=$req->next();
      $task=new TicketTask();
      $task->getFromDB($row['tasks_id']);
      return $task->fields['tickets_id'];
   }

   static function getActualBegin($task_id) {
      global $DB;

      $query=[
         'FROM'=>self::getTable(),
         'WHERE'=>[
            'tasks_id'=>$task_id,
            'actual_end'=>null,
         ]
      ];
      $req=$DB->request($query);
      $row=$req->next();
      return $row['actual_begin'];
   }

   static public function showStats(Ticket $ticket) {
      global $DB;

      if (Session::getCurrentInterface() == "central") {
         $total_time=$ticket->getField('actiontime');
         $ticket_id=$ticket->getID();
         $actual_totaltime=0;
         $query=[
            'SELECT'=>[
               'glpi_tickettasks.id',
            ],
            'FROM'=>'glpi_tickettasks',
            'WHERE'=>[
               'tickets_id'=>$ticket_id,
            ]
         ];
         foreach ($DB->request($query) as $id => $row) {
            $actual_totaltime+=self::totalEndTime($row['id']);
         }
         $html="<table class='tab_cadre_fixe'>";
         $html.="<tr><th colspan='2'>ActualTime</th></tr>";

         $html.="<tr class='tab_bg_2'><td>".__("Total duration")."</td><td>".HTML::timestampToString($total_time)."</td></tr>";
         $html.="<tr class='tab_bg_2'><td>ActualTime - ".__("Total duration")."</td><td>".HTML::timestampToString($actual_totaltime)."</td></tr>";

         $diff=$total_time-$actual_totaltime;
         if ($diff<0) {
            $color='red';
         } else {
            $color='black';
         }
         $html.="<tr class='tab_bg_2'><td>".__("Duration Diff", "actiontime")."</td><td style='color:".$color."'>".HTML::timestampToString($diff)."</td></tr>";
         if ($total_time==0) {
            $diffpercent=0;
         } else {
            $diffpercent=100*($total_time-$actual_totaltime)/$total_time;
         }
         $html.="<tr class='tab_bg_2'><td>".__("Duration Diff", "actiontime")." (%)</td><td style='color:".$color."'>".round($diffpercent, 2)."%</td></tr>";

         $html.="</table>";

         $html.="<table class='tab_cadre_fixe'>";
         $html.="<tr><th colspan='5'>ActualTime - ".__("Technician")."</th></tr>";
         $html.="<tr><th>".__("Technician")."</th><th>".__("Total duration")."</th><th>ActualTime - ".__("Total duration")."</th><th>".__("Duration Diff", "actiontime")."</th><th>".__("Duration Diff", "actiontime")." (%)</th></tr>";

         $query=[
            'SELECT'=>[
               'glpi_tickettasks.actiontime',
               'glpi_tickettasks.id AS task_id',
               'glpi_users.name',
               'glpi_users.id',
            ],
            'FROM'=>'glpi_tickettasks',
            'INNER JOIN'=>[
               'glpi_users'=>[
                  'FKEY'=>[
                     'glpi_users'=>'id',
                     'glpi_tickettasks'=>'users_id_tech',
                  ]
               ]
            ],
            'WHERE'=>[
               'glpi_tickettasks.tickets_id'=>$ticket_id,
            ],
            'ORDER'=>'glpi_users.id',
         ];
         $list=[];
         foreach ($DB->request($query) as $id => $row) {
            $list[$row['id']]['name']=$row['name'];
            if (self::findKey($list[$row['id']], 'total')) {
               $list[$row['id']]['total']+=$row['actiontime'];
            } else {
               $list[$row['id']]['total']=$row['actiontime'];
            }
            $qtime=[
               'SELECT'=>['SUM'=>'actual_actiontime AS actual_total'],
               'FROM'=>self::getTable(),
               'WHERE'=>[
                  'tasks_id'=>$row['task_id'],
               ],
            ];
            $req = $DB->request($qtime);
            if ($time = $req->next()) {
               if (self::findKey($list[$row['id']], 'actual_total')) {
                  $list[$row['id']]['actual_total']+=$time['actual_total'];
               } else {
                   $list[$row['id']]['actual_total']=$time['actual_total'];
               }
            }
         }

         foreach ($list as $key => $value) {
            $html.="<tr class='tab_bg_2'><td>".$value['name']."</td>";

            $html.="<td>".HTML::timestampToString($value['total'])."</td>";

            $html.="<td>".HTML::timestampToString($value['actual_total'])."</td>";
            if (($value['total']-$value['actual_total'])<0) {
               $color='red';
            } else {
               $color='black';
            }
            $html.="<td style='color:".$color."'>".HTML::timestampToString($value['total']-$value['actual_total'])."</td>";
            if ($value['total']==0) {
               $html.="<td style='color:".$color."'>0%</td></tr>";
            } else {
               $html.="<td style='color:".$color."'>".round(100*($value['total']-$value['actual_total'])/$value['total'])."%</td></tr>";
            }
         }
         $html.="</table>";

         $script=<<<JAVASCRIPT
				$(document).ready(function(){
					$("div.dates_timelines:last").append("{$html}");
				});
JAVASCRIPT;
         echo Html::scriptBlock($script);
      }
   }

   static function findKey($array, $keySearch) {
      foreach ($array as $key => $item) {
         if ($key == $keySearch) {
            return true;
         } else if (is_array($item) && self::findKey($item, $keySearch)) {
            return true;
         }
      }
      return false;
   }

   static function getSegment($task_id) {
      global $DB;

      $query=[
         'FROM'=>self::getTable(),
         'WHERE'=>[
            'tasks_id'=>$task_id,
            [
               'NOT' => ['actual_begin' => null],
            ],
            [
               'NOT' => ['actual_end' => null],
            ],
         ]
      ];
      $html="<td colspan='2'><table class='tab_cadre_fixe'>";
      foreach ($DB->request($query) as $id => $row) {
         $html.="<tr class='tab_bg_2'><td>".$row['actual_begin']."</td><td>".HTML::timestampToString($row['actual_actiontime'])."</td></tr>";
      }
      $html.="</table></td>";
      return $html;
   }

   static function preUpdate(TicketTask $item) {
      global $DB;

      if ($item->input['state']!=1) {
         if (self::checkTimerActive($item->input['id'])) {
            $actual_begin=self::getActualBegin($item->input['id']);
            $seconds=(strtotime(date("Y-m-d H:i:s"))-strtotime($actual_begin));
            $DB->update(
               'glpi_plugin_actualtime_tasks', [
                  'actual_end'      => date("Y-m-d H:i:s"),
                  'actual_actiontime'      => $seconds,
               ], [
                  'tasks_id'=>$item->input['id'],
                  [
                     'NOT' => ['actual_begin' => null],
                  ],
                  'actual_end'=>null,
               ]
            );
         }
      }
   }

   static public function postShowItem($params) {
      global $DB,$CFG_GLPI;

      $query=[
         'FROM'=>self::getTable(),
         'WHERE'=>[
            [
               'NOT' => ['actual_begin' => null],
            ],
            'actual_end'=>null,
            'users_id'=>Session::getLoginUserID(),
         ]
      ];
      $req=$DB->request($query);
      if ($row=$req->next()) {

         $rand = mt_rand();
         $warning=__s('Warning');
         $seconds=self::totalEndTime($row['tasks_id']);
         $ticket_id=self::getTicket(Session::getLoginUserID());

         $div="<div id='timer$rand'>".__("Timer started on", 'actualtime')." <a href='".$CFG_GLPI['root_doc']."/front/ticket.form.php?id=".$ticket_id."'>".__("Ticket")." ".$ticket_id."</a> -> <span>".HTML::timestampToString($seconds)."</span></div>";
         $script=<<<JAVASCRIPT
            $(document).ready(function(){
               if ($("#timer{$rand}").length) {
                  $("#timer{$rand}").remove();
               }
               $("body").append("{$div}");
               var time={$seconds};
               timer=setInterval(function(){
                  time+=1;

                  var text;
                  var distance=time;
                  var seconds = 0;
                  var minutes = 0;
                  var hours = 0;
                  var days = 0;

                  seconds = distance % 60;
                  distance-=seconds;
                  text=seconds + " s";

                  if (distance>0) {
                     minutes = (distance % 3600) / 60;
                     distance-=minutes*60;
                     text= minutes + " m " +seconds + " s";

                     if (distance>0) {
                        hours = (distance % 86400) / 3600;
                        distance-=hours*3600;
                        text= hours + " h " +minutes + " m " +seconds + " s";

                        if (distance>0) {
                           days = distance / 86400;
                           text= days + " d " +hours + " h " +minutes + " m " +seconds + " s";

                        }
                     }
                  }
                  $('#timer{$rand} span').text(text);
               },1000);
               $('#timer{$rand}').attr('title', '{$warning}');
               $(function() {
                  var _of = window;
                  var _at = 'right-20 bottom-20';
                  //calculate relative dialog position
                  $('.message_result').each(function() {
                     var _this = $(this);
                     if (_this.attr('aria-describedby') != 'message_result') {
                        _of = _this;
                        _at = 'right top-' + (10 + _this.outerHeight());
                     }
                  });

                  $('#timer{$rand}').dialog({
                     dialogClass: 'message_after_redirect warn_msg',
                     minHeight: 40,
                     minWidth: 200,
                     position: {
                        my: 'right bottom',
                        at: _at,
                        of: _of,
                        collision: 'none'
                     },
                     autoOpen: false,
                     show: {
                       effect: 'slide',
                       direction: 'down',
                       'duration': 800
                     }
                  })
                  .dialog('open');
               });
            });
JAVASCRIPT;
         print_r(Html::scriptBlock($script));
      }
   }

   static function install(Migration $migration) {
      global $DB;

      $table = self::getTable();

      if (!$DB->tableExists($table)) {
         $migration->displayMessage("Installing $table");

         $query = "CREATE TABLE IF NOT EXISTS $table (
							`id` int(11) NOT NULL auto_increment,
                     `tasks_id` int(11) NOT NULL,
                     `actual_begin` datetime DEFAULT NULL,
                     `actual_end` datetime DEFAULT NULL,
                     `users_id` tinyint(1) NOT NULL,
                     `actual_actiontime` int(11) NOT NULL DEFAULT 0,
                     PRIMARY KEY (`id`),
                     KEY `tasks_id` (`tasks_id`),
                     KEY `users_id` (`users_id`)
			         ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
         $DB->query($query) or die($DB->error());
      }
   }

   static function uninstall(Migration $migration) {

      $table = self::getTable();
      $migration->displayMessage("Uninstalling $table");
      $migration->dropTable($table);
   }
}
