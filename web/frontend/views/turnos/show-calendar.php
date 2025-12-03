<style>
  /*.tooltip{background-color: #0073ea; color: #ffffff} 
  .dayslot {
      float: left;
      margin-left: 2px;
  }
  .fc-agenda-slots .unavailable{
      background-color: #e6e6e6;
  }*/
</style>
<?php

use yii\helpers\Url;

use yii\web\JsExpression;
use yii\web\View;
?>



  <?php
  /*echo \yii2fullcalendar\yii2fullcalendar::widget(
            array('id' => 'calendar',
                'options' => [''],
                'clientOptions' => [
                    'header' => ['left' => 'prev,next', 'center' => '', 'right' => ''],
                    'defaultView' => 'agendaFourDay',
                    //'businessHours' => true,
                    'views' => [
                        'agendaFourDay' => [
                            'type' => 'agenda',
                            'duration' => ['days' => '4'],
                            'buttonText' => '',
                            'allDaySlot' => false,
                            'slotDuration' => '00:10:00',
                        // 'defaultEventMinutes' => 15
                        ]
                    ],
                    'selectable' => true,
                    'selectOverlap'=> false,
                    'eventOverlap' => false,
                    'slotEventOverlap' => false,
                    //'selectHelper'=> true,
                    'eventDurationEditable' => false,
                    'editable' => true,
                    'eventRender' => new JsExpression("function(event, element) {
                        if(event.title != null){
                            $(element).popover({
                                // title: function () {
                                //     return \"<B>\" + event.title + \"</B>\";
                                // },
                                placement:'auto',
                                html: true,
                                trigger : 'click',
                                animation : 'false',
                                content: function () {
                                    return '<span class=\"removeEvent glyphicon glyphicon-trash pull-right\" style=\"cursor:pointer\" id=\"'+event.id+'\"></span>' + event.title
                                },
                                container:'body'
                            }).popover('show');  
                        }
                    }"),
                    'dayClick' => new JsExpression("function(start, end, jsEvent) {                    
                        $('#calendar').fullCalendar('removeEvents', ['temporal']);                
                        var fin = moment(start);
                        fin.add(10, 'minutes');
                        dia = start.format('DD');
                        hora_i = start.format('HH:mm');
                        hora_f = fin.format('HH:mm');
                        /*if (isOverlapping(start.format(), fin.format())) { return false; }*/
  /* $('#fechadesde').val(start.format('YYYY-MM-DD HH:mm'));
                        $('#fechahasta').val(fin.format('YYYY-MM-DD HH:mm'));
                        
                        $('#horario').html('Día ' + dia + ' a las ' + hora_i + 'hs');

                        eventData = {
                          id: 'temporal',
                          title: hora_f,
                          start: start,
                          end: fin,
                          color: '#7856B3',
                          borderColor: '#746094'
                        };
                        $('#calendar').fullCalendar('renderEvent', eventData);
                      }"),
                ],
                'events' => $events,
    ));*/
  ?>
  <div id="calendar"></div>

<?php


$this->registerJs("
    $(document).ready(function () {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
          initialView: 'timeGridFourDay',
          headerToolbar: {
            left: 'prev,next',
            center: 'title',
            right: 'timeGridWeek,timeGridDay',
          },
          views: {
            timeGridFourDay: {
              type: 'timeGrid',
              duration: { days: 4 },
              slotDuration: '00:10:00',
            }
          },
          selectable: true,
          selectOverlap: false,
          eventOverlap: false,
          slotEventOverlap: false,
          eventDurationEditable: false,
          editable: true,
          allDaySlot: false,

        });
        calendar.render();
      });
  ", View::POS_BEGIN);

$this->registerJs("    
      function isOverlapping(start, end){
       var array = $('#calendar').fullCalendar('clientEvents');
       for(i in array){
           if(array[i]._id != event._id){           
              if(!(array[i].start.format() >= end || array[i].end.format() <= start)){
                   return true;
              }
           }
        }
        return false;
      }", View::POS_BEGIN);

$this->registerJs("
    $(document).ready(function () {

      function isOverlapping(event){
       var array = $('#calendar').fullCalendar('clientEvents');
       for(i in array){
           if(array[i]._id != event._id){
               if(!(array[i].start.format() >= event.end.format() || array[i].end.format() <= event.start.format())){
                   return true;
               }
           }
        }
        return false;
      }

      $('#calendar').on('shown.bs.popover', function() {
        var pop = $(this);
        $('.removeEvent').on('click', function() {
          var ev_id = $(this).attr('id');
          var r = confirm('Esta seguro?');
          if(r == true){
            $.post('" . Url::to(['turnos/delete']) . "/'+ev_id,
                  function(data){
                    $('#calendar').fullCalendar('removeEvents', ev_id);
                    $('body').append('<div class=\"alert alert-success\" role=\"alert\">El evento fue eliminado correctamente</div>'); 
                    window.setTimeout(function() { $('.alert').alert('close'); }, 3000);                               
            });
          }
          $('.popover').popover('hide');
        });

        $('body').on('click', function (e) {
            //did not click a popover toggle or popover
            if ($(e.target).data('toggle') !== 'popover'
                && $(e.target).parents('.popover.in').length === 0 && $(e.target).parents('.fc-event').length === 0) { 
                $('.popover').popover('hide');
            }
        }); 
    });
  });                      
");


?>