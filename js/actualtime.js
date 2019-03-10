symb_d = '%dd';
symb_day = '%d day';
symb_days = '%d days';
symb_h = '%dh';
symb_hour = '%d hour';
symb_hours = '%d hours';
symb_min = '%dmin';
symb_minute = '%d minute';
symb_minutes = '%d minutes';
symb_s = '%ds';
symb_second = '%d second';
symb_seconds = '%d seconds';
ajax_url = '../plugins/actualtime/ajax/timer.php';

function showtaskform(e){
   e.preventDefault();
   $('<div>')
      .dialog({
         modal:  true,
         width:  'auto',
         height: 'auto',
      })
      .load(ajax_url + '?showform=true', function() {
         $(this).dialog('option', 'position', ['center', 'center'] );
      });
}

function timeToText(time, format) {
   var days = 0;
   var hours = 0;
   var minutes = 0;
   var distance = time;
   var seconds = distance % 60;
   distance -= seconds;
   var text = (format == 3 ? (seconds > 1 ? symb_seconds : symb_second) : symb_s).replace('%d', seconds);;
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

$(document).ready(function(){
   jQuery.ajax({
      type:     'GET',
      url:      ajax_url + '?footer',
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

         if ($("#timer" + result['rand']).length) {
            $("#timer" + result['rand']).remove();
         }

         if (result['task_id']) {
            $("body").append(result['div']);
            var time = result['time'];
            var timerdiv = $("#timer" + result['rand']);
            timer = setInterval(function() {
               time += 1;
               timerdiv.find('span').text(timeToText(time, 10));
            },1000);
            timerdiv.attr('title', result['warning']);
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
               timerdiv
                  .dialog({
                     dialogClass: 'message_after_redirect warn_msg',
                     minHeight:   40,
                     minWidth:    200,
                     position:    {
                        my:        'right bottom',
                        at:        _at,
                        of:        _of,
                        collision: 'none'
                     },
                     autoOpen:    false,
                     show:        {
                        effect:     'slide',
                        direction:  'down',
                        'duration': 800
                     }
                  })
                  .dialog('open');
            });
         }
      }
   });
});
