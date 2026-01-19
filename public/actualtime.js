/* global CFG_GLPI */
window.actualTime = new function() {
   this.ajax_url = CFG_GLPI.root_doc + '/plugins/actualtime/ajax/timer.php';
   var timer;
   var popup_div = '';
// Translations
   var symb_d = '%dd';
   var symb_day = '%d day';
   var symb_days = '%d days';
   var symb_h = '%dh';
   var symb_hour = '%d hour';
   var symb_hours = '%d hours';
   var symb_min = '%dmin';
   var symb_minute = '%d minute';
   var symb_minutes = '%d minutes';
   var symb_s = '%ds';
   var symb_second = '%d second';
   var symb_seconds = '%d seconds';
   var text_pause = 'Pause';
   var text_restart = 'Restart';
   var text_done = 'Done';
   var toast = null;
   var modal = null;

   this.showTaskForm = function(e) {
      e.preventDefault();
      if (modal == null) {
         var html = `<div class="modal fade" id="modal_actualtime" role="dialog">';
            <div class="modal-dialog modal-lg">
               <div id="modal_content" class="modal-content">
               </div>
            </div>
         </div>`;
         $('body').append(html);
         modal = new bootstrap.Modal(document.getElementById('modal_actualtime'),{});
      }
      $("#modal_content").load(this.ajax_url + '?showform=true');
      modal.show();
   }

   this.timeToText = function(time, format) {
      var days = 0;
      var hours = 0;
      var minutes = 0;
      var distance = time;
      var seconds = distance % 60;
      distance -= seconds;
      var text = (format == 3 ? (seconds > 1 ? symb_seconds : symb_second) : symb_s).replace('%d', seconds);
      ;
      if (distance > 0) {
         minutes = (distance % 3600) / 60;
         distance -= minutes * 60;
         text = (format == 3 ? (minutes > 1 ? symb_minutes : symb_minute) : symb_min).replace('%d', minutes) + ' ' + text;
         if (distance > 0) {
            if (format == 2) {
               hours = distance / 3600;
               if (minutes < 10) {
                  minutes = '0' + minutes;
               }
               return symb_h.replace('%d', hours) + (seconds > 0 ? symb_min.replace('%d', minutes) + symb_s.replace('%d', (seconds < 10 ? '0' : '') + seconds) : minutes);
            }
            hours = (distance % 86400) / 3600;
            distance -= hours * 3600;
            text = (format == 3 ? (hours > 1 ? symb_hours : symb_hour) : symb_h).replace('%d', hours) + ' ' + text;
            if (distance > 0) {
               days = distance / 86400;
               text = (format == 3 ? (days > 1 ? symb_days : symb_day) : symb_d).replace('%d', days) + ' ' + text;
            }
         }
      }
      return text;
   }

   this.showTimerPopup = function(id, link, name) {
      // only if enabled in settings
      if (popup_div && toast != null) {
         popup_div = popup_div.replace(/%t/g, id);
         popup_div = popup_div.replace(/%l/g, link);
         popup_div = popup_div.replace(/%n/g, name);
         $("#toast_body").html(popup_div);
         toast.show();
      }
   }

   this.startCount = function(task, time) {
      timer = setInterval(function () {
         time += 1;
         var timestr = window.actualTime.timeToText(time, 1);
         $("[id^='actualtime_timer_" + task + "_']").text(timestr);
         $("#toast_body span").text(timestr);
      }, 1000);
   }

   this.endCount = function() {
      clearInterval(timer);
   }

   this.fillCurrentTime = function(task, time) {
      var timestr = window.actualTime.timeToText(time, 1);
      $("[id^='actualtime_timer_" + task + "_']").text(timestr);
   }

   this.pressedButton = function(task, itemtype, val) {
      jQuery.ajax({
         type: "POST",
         url: this.ajax_url,
         dataType: 'json',
         data: {action: val, task_id: task, itemtype: itemtype},
         success: function (result) {
            if (result['type'] == 'info') {
               if (val == 'start') {
                  window.actualTime.startCount(task, result['time']);
                  $("[id^='actualtime_timer_" + task + "_']").css('color', 'red');
                  $("[id^='actualtime_button_" + task + "_1_']").attr('value', text_pause).attr('action', 'pause').css('background-color', 'orange').prop('disabled', false);
                  $("[id^='actualtime_button_" + task + "_1_']").html('<span>' + text_pause + '</span>');
                  $("[id^='actualtime_button_" + task + "_2_']").attr('action', 'end').css('background-color', 'red').prop('disabled', false);
                  window.actualTime.showTimerPopup(result['parent_id'], result['link'], result['name']);
                  $("[id^='actualtime_faclock_" + task + "_']").addClass('fa-clock').css('color', 'red');
                  return;
               } else if ((val == 'end') || (val == 'pause')) {
                  window.actualTime.endCount();
                  //$("#actualtime_popup").remove();
                  toast.hide();
                  // Update all forms of this task (normal and modal)
                  $("[id^='actualtime_timer_" + task + "_']").css('color', 'black');
                  $("[id^='actualtime_faclock_" + task + "_']").css('color', 'black');
                  var timestr = window.actualTime.timeToText(result['time'], 1);
                  $("[id^='actualtime_timer_" + task + "_']").text(timestr);
                  $("[id^='actualtime_segment_" + task + "_']").html(result['segment']);
                  if (val == 'end') {
                     // Update state fields also (as Done)
                     $("select[name='state']").attr('data-track-changes', '');
                     $("span.state.state_1[onclick='change_task_state(" + task + ", this)']").attr('title', text_done).toggleClass('state_1 state_2');
                     $("input[type='hidden'][name='id'][value='" + task + "']").closest("div[data-itemtype='"+itemtype+"'][data-items-id='"+task+"']").find("select[name='state']").val(2).trigger('change');
                     $("select[name='state']").removeAttr('data-track-changes');
                     $("[id^='actualtime_button_" + task + "_']").attr('action', '').css('background-color', 'gray').prop('disabled', true);
                     if (typeof result["task_time"] !== 'undefined' && result["task_time"] != 0) {
                        var actiontime = $("input[type='hidden'][name='id'][value='" + task + "']").closest("div[data-itemtype='"+itemtype+"'][data-items-id='"+task+"']").find("select[name='actiontime']");
                        actiontime.attr('data-track-changes', '');
                        actiontime.val(result['task_time']).trigger('change');
                        actiontime.removeAttr('data-track-changes');
                        $("div[data-itemtype='"+itemtype+"'][data-items-id='"+task+"'] span.actiontime").text(window.actualTime.timeToText(result['task_time'], 1));
                     }
                  } else {
                     $("[id^='actualtime_button_" + task + "_1_']").attr('value', text_restart).attr('action', 'start').css('background-color', 'green').prop('disabled', false);
                     $("[id^='actualtime_button_" + task + "_1_']").html('<span>' + text_restart + '</span>');
                  }
               }
            }
            switch (result['type']) {
               case 'warning':
                  var title = __('Warning');
                  var css_class = 'bg-warning';
                  break;
               case 'info':
                  var title = _n("Information", "Informations", 1);
                  var css_class = 'bg-info';
                  break;
               default:
                  var title = __('Error');
                  var css_class = 'bg-danger';
                  break;
            }
            toast_id++;

            const html = `<div class='toast-container bottom-0 end-0 p-3 messages_after_redirect'>
               <div id='toast_js_${toast_id}' class='toast border-0 animate_animated animate__delay-2s animate__slow' role='alert' aria-live='assertive' aria-atomic='true'>
                  <div class='toast-header ${css_class} text-white'>
                     <strong class='me-auto'>${title}</strong>
                     <button type='button' class='btn-close' data-bs-dismiss='toast' aria-label='${__('Close')}'></button>
                  </div>
                  <div class='toast-body'>
                     ${result['message']}
                  </div>
               </div>
            </div>`;
            $('body').append(html);

            const toasttemp = new bootstrap.Toast(document.querySelector('#toast_js_' + toast_id), {
               delay: 10000,
            });
            toasttemp.show();
         }
      });
   }

   this.init = function(ajax_url) {
      window.actualTime.ajax_url = ajax_url;
      if (!$("#toast_actualtime").length) {
         const html = `<div class='toast-container bottom-0 start-0 p-3 messages_after_redirect'  id='toast_actualtime'>
            <div class='toast border-0 animate__animated animate__tada animate__delay-2s animate__slow' role='alert' aria-live='assertive' aria-atomic='true'>
               <div class='toast-header bg-info text-white'>
                  <strong class='me-auto'>${_n('Information', 'Informations', 1)}</strong>
                  <button type='button' class='btn-close' data-bs-dismiss='toast' aria-label='${__('Close')}'></button>
               </div>
               <div id='toast_body' class='toast-body'></div>
            </div>
         </div>`;
         $('body').append(html);
         toast = new bootstrap.Toast(document.querySelector('#toast_actualtime .toast:not(.show)'), {autohide:false});
      }

      // Initialize
      jQuery.ajax({
         type: 'GET',
         url: window.actualTime.ajax_url + '?footer',
         dataType: 'json',
         success: function (result) {
            symb_d = result['symb_d'];
            symb_day = result['symb_day'];
            symb_days = result['symb_days'];
            symb_h = result['symb_h'];
            symb_hour = result['symb_hour'];
            symb_hours = result['symb_hours'];
            symb_min = result['symb_min'];
            symb_minute = result['symb_minute'];
            symb_minutes = result['symb_minutes'];
            symb_s = result['symb_s'];
            symb_second = result['symb_second'];
            symb_seconds = result['symb_seconds'];
            text_warning = result['text_warning'];
            text_pause = result['text_pause'];
            text_restart = result['text_restart'];
            text_done = result['text_done'];
            popup_div = result['popup_div'];

            if (result['parent_id']) {
               window.actualTime.startCount(result['task_id'], result['time']);
               window.actualTime.showTimerPopup(result['parent_id'], result['link'], result['name']);
            }
         }
      });
   }
}();

$(document).ready(function(){
   var url = CFG_GLPI.root_doc+"/"+GLPI_PLUGINS_PATH.actualtime+"/ajax/timer.php";
   window.actualTime.init(url);
});