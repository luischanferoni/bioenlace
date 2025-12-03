<style>
  .tooltip {
    background-color: #0073ea;
    color: #ffffff
  }
</style>
<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\web\View;
use yii\helpers\Url;
use yii\bootstrap5\Modal;
use kartik\select2\Select2;
use yii\web\JsExpression;
use yii\helpers\ArrayHelper;
use common\models\Persona;
use common\models\Rrhh;
use common\models\Rrhh_efector;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\TurnoBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

/* @var $this yii\web\View */
/* @var $model common\models\Turno */
/* @var $form yii\widgets\ActiveForm */


$this->title = 'Turnos';
$this->params['breadcrumbs'][] = $this->title;

?>
<div class="iq-loader-box" id="cover-spin">
  <div class="iq-loader-14"></div>
</div>

<div class="card">
  <div class="card-body">
    <?php

    $j = 0;
    $ref_html = array();
    foreach ($referencias as $referencia) {
      $cant[$referencia->id_servicio] = isset($cant[$referencia->id_servicio]) ? $cant[$referencia->id_servicio] + 1 : 1;
      $ref_html[$referencia->id_servicio][$referencia->id_referencia] = '<a href="#" class="link_referencia" id_referencia="' . $referencia->id_referencia . '" id_persona="' . $referencia->consulta->turno->persona->id_persona . '" 
    id_servicio="' . $referencia->id_servicio . '" persona_hc="' . $referencia->consulta->turno->persona->obtenerNHistoriaClinica(Yii::$app->user->getIdEfector()) . '"><span class="label label-warning">' . $referencia->consulta->turno->persona->nombreCompleto(Persona::FORMATO_NOMBRE_A_N_D) . '</span></a>';
    }

    $s_ant = null;

    echo ('<div class = "row">');

    echo ('<div class= "col-lg-4" style="height: 75vh; overflow: auto;">');

    foreach ($rr_hhs as $rr_hh) {
      if ($s_ant !== $rr_hh->servicio->nombre) {
        if ($s_ant != null) { ?>
  </div>
</div>
</div>
<?php }
        $s_ant = $rr_hh->servicio->nombre;
        $parametros = unserialize($rr_hh->servicio->parametros);
        if (isset($parametros['color'])) {
          $color = $parametros['color'];
        } else {
          # code...
          $color = "#000";
        }
?>

<div class="card">

  <div class="card-header" style="background-color: <?php echo $color; ?>">
    <b style="color: white"><?php echo $rr_hh->servicio->nombre; ?></b>
    <?php
        if (isset($cant[$rr_hh->id_servicio])) {
          $texto = $cant[$rr_hh->id_servicio] > 1 ? ' referencias' : ' referencia';
          echo '&nbsp;<span class="badge bg-dark">' . $cant[$rr_hh->id_servicio] . $texto . '</span>';
        }
    ?>
  </div>

  <div class="card-body bg-soft-secondary">


    <div class="list-group">
    <?php }
      if (is_object($rr_hh->rrhhEfector->persona)) {
    ?>
      <div class="list-group-item">
        <a href="#" id="<?php echo $rr_hh->id_rr_hh ?>" class="mostrar-turnos btn text-white w-75 list-group-item" servicio="<?php echo $rr_hh->id_servicio; ?>" style="background-color: <?php echo $color; ?>"> <?php echo $rr_hh->rrhhEfector->persona->nombre ?> <?php echo $rr_hh->rrhhEfector->persona->apellido ?> </a>
        <a class="btn btn-light list-group-item" style="background-color: <?php echo $color; ?>" href="<?= Url::to(['turnos/espera', 'id_user' => $rr_hh->rrhhEfector->persona->id_user]) ?>" target="_blank"><i class="bi bi-printer text-white"></i></a>

      </div>


  <?php }
    } ?>

    </div>
  </div>
</div>
</div>


<div class="col-lg-8">
  <div class="card">
    <div class="card-body">
      <div id="calendar"></div>
    </div>
  </div>

</div>

</div>
</div>
<?php



$this->registerJs("var ref_html = " . json_encode($ref_html) . ";", View::POS_HEAD);
$this->registerJsFile("//cdn.jsdelivr.net/npm/luxon@2.3.0/build/global/luxon.min.js");
$this->registerJsFile("//cdn.jsdelivr.net/npm/fullcalendar@6.1.7/index.global.min.js");
$this->registerJsFile("//cdn.jsdelivr.net/npm/@fullcalendar/luxon2@6.1.7/index.global.min.js");


$this->registerJs("

    $(document).ready(function () {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
          initialView: 'timeGridFourDay',
          locale: 'es',
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
          dateClick: function(info) {
            temporal = calendar.getEventById('temporal');
            if (temporal) {
              temporal.remove();
            }

            var inicio = FullCalendar.Luxon2.toLuxonDateTime(info.date, calendar); //cambiamos el formato de fecha desde Fullcalendar a Luxon
            var aux = inicio.plus({ minutes: 15 });
            var fin = aux.toISO(); //cambiamos el formato de luxon a ISO 8601 para que funcione en Fullcalendar
            
            //if (isOverlapping(inicio, aux)) { return false; }

            eventData = {
              id: 'temporal',
              title: 'Aqui va el turno',
              start: info.date,
              end: fin, 
              className:'alert-left ps-2',
              backgroundColor:'#FCE1D1',
              borderColor:'#F16A1B',
              color:'#F16A1B',
              textColor:'#F16A1B'
            };
            
            calendar.addEvent(eventData);
          }

        });
        calendar.render();
        $(document).on('click','.mostrar-turnos', function() {
          $('.list-group-item').removeClass( 'bg-soft-danger' );
          $('.bi-caret-right-fill').remove();
          $(this).parent().append(\"<i class='bi bi-caret-right-fill'></i>\");
          $( this ).parent().addClass( 'bg-soft-danger' );
          var rrhh = $(this).attr('id');
          var id_servicio = $(this).attr('servicio');
          $('#id_servicio').val(id_servicio);
          $('#id_rr_hh').val(rrhh);
          
          $.post('" . Url::to(['turnos/eventos']) . "', {id_rr_hh:rrhh},function(data){
            //$('#cover-spin').show();
            calendar.removeAllEvents();
            calendar.addEventSource(data);
            calendar.refetchEvents();
            //$('#cover-spin').hide();
          });
         
  
          $(document).on('click','#horario',function() {
            eventData = {
              id: 'temporal',
              title: 'gg',
              color: '#7856B3',
              borderColor: '#746094'
            };
            
            //console.log();          
            //
            //$('#calendar').fullCalendar('renderEvent', eventData);          
          });
          $(document).on('click','.link_referencia',function(){        
            var id_persona = $(this).attr('id_persona'); 
            var persona_hc = $(this).attr('persona_hc');          
            // $('#paciente').append('<option value=\"' + $(this).children('span').html() + '\" selected>' + $(this).html() + '</option>');    
  
            if(persona_hc == '--'){
              $('#hc_container').html(
                '<small class=\"text-danger\">Paciente sin N° de Historia Clínica</small><input id=\"nro_hc\" class=\"form-control\" ><small>Sugerencia: ' + $('#ultimo_hc').val() + '</small>'
              );                      
            }else{
              $('#hc_container').html('');
            }
  
            $('#id_persona').val(id_persona);
            $('#id_referencia').val($(this).attr('id_referencia'));
            $('#id_servicio').val($(this).attr('id_servicio'));
  
            var tmp = $(this).children('span').html().split('-');
            $('#datos_detalle').html('Apellido y Nombre: ' + tmp[0] + '<br/>N° Doc: ' + tmp[1] + '<br>N° Historia Clínica: ' + persona_hc);
  
          });        
  
          $('#modal').on('shown.bs.modal', function () {
            $('.fc-next-button').trigger('click');
            $('.fc-prev-button').trigger('click');
          });
          
          $('#modal').on('hidden.bs.modal', function () {
            $('#fechadesde').val('');
            $('#fechahasta').val('');
            $('#id_rr_hh').val('');
            $('#fechadesde').val('');
            $('#id_referencia').val('');
            $('#nro_hc').val('');
  
            $('#programado').attr('checked', false);
  
            $('#hc_container').html('');
            $('#datos_detalle').html('');                  
            $('#horario').html('Seleccione el horario en el calendario');
            $('#paciente').select2('val', '');
          })        
      }); 
      });

    

  ");

$this->registerJs("
    function isOverlapping(start, end){
      var array = calendar.getEvents();
      for(i in array){
            inicio = FullCalendar.Luxon2.toLuxonDateTime(array[i]['start'], calendar); 
            console.log(inicio);
            fin = FullCalendar.Luxon2.toLuxonDateTime(array[i]['end'], calendar);  
            console.log(fin);
            if(!(inicio >= end || end <= start)){

                  return true;
            }
          
      }
      return false;
    }", View::POS_BEGIN
    );

$this->registerJs(
  "
    $('#nuevo-evento').submit(function(){
      var error = false;
      if($('#id_persona').val() == ''){
        $('#paciente').parent('.form-group').addClass('has-error');
        error = 'Por favor seleccione el paciente';
      }
      if($('#fechadesde').val() == ''){
        error = 'Por favor seleccione el horario';
      }
      if(error){
        $('.alert-container').append('<div id=\"turnos-alert-id\" class=\"alert alert-danger\" role=\"alert\">'
                            + error + '</div>'); 
        window.setTimeout(function() { $('#turnos-alert-id').alert('close'); }, 3000);  
        return false;    
      }       
      $.post('" . Url::to(['turnos/create']) . "',
              { 
                fecha:$('#fechadesde').val(),
                id_rr_hh:$('#id_rr_hh').val(),
                id_servicio:$('#id_servicio').val(),
                id_persona:$('#id_persona').val(),
                referencia:$('#id_referencia').val(),
                nro_hc:$('#nro_hc').val(),
                programado:$('#programado').is(':checked')?1:0
              },
              function(data){
                if(data == 'OK'){                         
                  if($('#id_referencia').val() != ''){
                    delete ref_html[$('#id_servicio').val()][$('#id_referencia').val()];
                  }
                  $('#fechadesde').val('');
                  $('#fechahasta').val('');
                  $('#id_rr_hh').val('');
                  $('#fechadesde').val('');
                  $('#id_referencia').val('');
                  $('#nro_hc').val('');

                  $('#programado').attr('checked', false);

                  $('#ultimo_hc').val(parseInt($('#ultimo_hc').val()) + 1);
                  $('#hc_container').html('');
                  $('#datos_detalle').html('');                  
                  $('#horario').html('Seleccione el horario en el calendario');
                  $('#paciente').select2('val', '');

                 // $('#paciente').select2('updateResults');

                  $('#modal').modal('hide'); 
                  $('#modal').find('#calendario_container').html('');
                  $('.alert-container').append('<div id=\"turnos-alert-id\" class=\"alert alert-success\" role=\"alert\">'
                                      +'Turno agregado correctamente</div>'); 
                  window.setTimeout(function() { $('#turnos-alert-id').alert('close'); }, 3000);                          
                }else{
                    $('.alert-container').append('<div id=\"turnos-alert-id\" class=\"alert alert-danger\" role=\"alert\">'
                                        + data + '</div>'); 
                    window.setTimeout(function() { $('#turnos-alert-id').alert('close'); }, 3000);  
                    return false;                    
                }

      });
      return false;
    });

    /* submit if elements of class=auto_submit_item in the form changes */
    $(function() {
     $('#medico_select').change(function() {
       $('#personal-busqueda').submit();
     });
    });

    "
);

?>