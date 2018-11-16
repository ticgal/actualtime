<?php

include ("../../../inc/includes.php");
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

if (isset($_POST["action"])) {
   $task_id=$_POST["task_id"];
   switch ($_POST["action"]) {
      case 'start':
         if (PluginActualtimeTask::checkTimerActive($task_id)) {
            $result=[
               'mensage'=>__("A user is already performing the task", 'actualtime'),
               'title' => __s('Warning'),
               'class' => 'warn_msg',
            ];
         } else {
            if (PluginActualtimeTask::checkUserFree(Session::getLoginUserID())) {
               $opcional=PluginActualtimeTask::getTicket(Session::getLoginUserID());
               $result=[
                  'mensage'=>__("You are already doing a task", 'actualtime')." <a href='/front/ticket.form.php?id=".$opcional."'>".__("Ticket")."</a>",
                  'title' => __s('Warning'),
                  'class' => 'warn_msg',
               ];
            } else {
               $DB->insert(
                  'glpi_plugin_actualtime_tasks', [
                     'tasks_id'      => $task_id,
                     'actual_begin' => date("Y-m-d H:i:s"),
                     'users_id'=>Session::getLoginUserID(),
                  ]
               );
               $result=[
                  'mensage'=>__("Timer started", 'actualtime'),
                  'title' => __s('Information'),
                  'class' => 'info_msg',
               ];
            }
         }
         echo json_encode($result);
         break;

      case 'end':
         if (PluginActualtimeTask::checkTimerActive($task_id)) {
            if (PluginActualtimeTask::checkUser($task_id, Session::getLoginUserID())) {
               $actual_begin=PluginActualtimeTask::getActualBegin($task_id);
               $seconds=(strtotime(date("Y-m-d H:i:s"))-strtotime($actual_begin));
               $DB->update(
                  'glpi_plugin_actualtime_tasks', [
                     'actual_end'      => date("Y-m-d H:i:s"),
                     'actual_actiontime'      => $seconds,
                  ], [
                     'tasks_id'=>$task_id,
                     [
                        'NOT' => ['actual_begin' => null],
                     ],
                     'actual_end'=>null,
                  ]
               );
               $DB->update(
                  'glpi_tickettasks', [
                     'state'=>2,
                  ], [
                     'id'=>$task_id,
                  ]
               );

               $result=[
                  'mensage'=>__("Timer completed", 'actualtime'),
                  'title' => __s('Information'),
                  'class' => 'info_msg',
               ];
            } else {
               $result=[
                  'mensage'=>__("Only the user who initiated the task can close it", 'actualtime'),
                  'title' => __s('Warning'),
                  'class' => 'warn_msg',
               ];
            }
         } else {
            $result=[
               'mensage'=>__("The task had not been initialized", 'actualtime'),
               'title' => __s('Warning'),
               'class' => 'warn_msg',
            ];
         }
         echo json_encode($result);
         break;

      case 'count':
         echo abs(PluginActualtimeTask::totalEndTime($task_id));
         break;
   }
}