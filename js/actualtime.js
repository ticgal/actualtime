$(document).ready(function(){
   jQuery.ajax({
      type: "GET",
      url: '../plugins/actualtime/ajax/timer.php',
      dataType: 'json',
      success: function (result) {
         if (result['time']) {
            if ($("#timer" + result['rand']).length) {
               $("#timer" + result['rand']).remove();
            }
            $("body").append(result['div']);
            var time = result['time'];
            x = setInterval(function() {
               time += 1;
               var distance = time;
               var seconds = 0;
               var minutes = 0;
               var hours = 0;
               var days = 0;
               seconds = distance % 60;
               distance -= seconds;
               var text = seconds + " s";
               if (distance > 0) {
                  minutes = (distance % 3600) / 60;
                  distance -= minutes * 60;
                  text = minutes + " min " + text;
                  if (distance > 0) {
                     hours = (distance % 86400) / 3600;
                     distance -= hours * 3600;
                     text = hours + " h " + text;
                     if (distance > 0) {
                        days = distance / 86400;
                        text = days + " d " + text;
                     }
                  }
               }
               $("#timer" + result['rand'] + " span").text(text);
            }, 1000);
            $("#timer" + result['rand']).attr('title', result['title']);
            $(function() {
               var _of = window;
               var _at = 'right-20 bottom-20';
               // calculate relative dialog position
               $('.message_result').each(function() {
                  var _this = $(this);
                  if (_this.attr('aria-describedby') != 'message_result') {
                     _of = _this;
                     _at = 'right top-' + (10 + _this.outerHeight());
                  }
               });

               $("#timer" + result['rand']).dialog({
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

         }
      }
   });
});
