<?php

use yii\helpers\Html;
use yii\helpers\Url;

use common\models\Persona;


$this->title = 'Lista de Espera';
$this->params['breadcrumbs'][] = $this->title; ?>
<?php
$sec = "120";
header("Refresh: $sec");



// es para la vista desde otros perfiles que no son el profesional
$rr_hh = '';

if ($profesional != '') {    
  $rr_hh = " para " . $profesional->rrhhEfector->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON);
}
// fin

$fecha_espera = isset($_GET['fecha']) ? ' del ' . date('d-m-Y', strtotime($_GET['fecha'])) : ' del ' . date('d-m-Y');
?>

<?php 
// es para la vista desde otros perfiles que no son el profesional
if($profesional != ''){ ?>
<div class="row">
  <div class="col-4 text-center">
    <img src="<?= Yii::getAlias('@web') ?>/images/logo_ministerio_salud.png" style="height: 55px;"/>
  </div>
  <div class="col-4">
    <p class="text-center">
          MINISTERIO DE SALUD - PROVINCIA DE SANTIAGO DEL ESTERO<br/>
          SISSE <?=Yii::$app->user->getNombreEfector()?>
      </p>
  </div>
  <div class="col-4 text-center">
    <img class="" src="<?= Yii::getAlias('@web') ?>/images/logoSISSE2_small.png" style="height: 55px;"/>
  </div>
</div> 
<?php } ?>


<div class="row d-flex align-items-center text-center mb-5">
  <div class="card">
    <div class="card-body">
       <div class="row">
          <div class="col-12">
              <h3 class="float-center mt-2 mb-2"><?= Html::encode($this->title) . $rr_hh . $fecha_espera ?></h3>
            </div>
       </div>

      <div class="row  no-print">
        <div class="col-4">
          <?php
            $fecha1 = date('Y-m-d', strtotime($fecha . ' -1 day'));
            $linkFecha1 = 'turnos/espera?fecha='.$fecha1;

            if($profesional != ''){
              $linkFecha1 .= '&rrhh='.$profesional->id;
            }

            $linkFecha1 = Url::toRoute($linkFecha1);

            echo Html::a('<svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" fill="currentColor" class="bi bi-chevron-left" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
            </svg>', $linkFecha1, ['class' => 'btn btn-primary rounded-pill float-end', 'data-bs-toggle'=>'tooltip', 'data-bs-placement'=>'bottom', 'data-bs-original-title'=>'Dia Anterior']);
          ?>
        </div>

        

        <div class="col-4 justify-content-center d-flex text-center align-items-center">
          <button id="cal-lista-espera" class="btn btn-sm bg-soft-primary w-25 float-start rounded" 
            <?php if($profesional != '') { echo "data-rrhh='$profesional->id'";  } ?> 
            data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-original-title="Buscar por fecha">
            <i class="bi bi-calendar-date" style="font-size: 25px;"></i>
          </button>
        </div>

        <div class="col-4">
          <?php $fecha2 = date('Y-m-d', strtotime($fecha . ' +1 day'));

            $linkFecha2 = '/turnos/espera?fecha='.$fecha2;

            if($profesional != ''){
              $linkFecha2 .= '&rrhh='.$profesional->id;
            }

            $linkFecha2 = Url::toRoute($linkFecha2);

            if ($fecha2 <= date('Y-m-d')) {
              echo Html::a('<svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" fill="currentColor" class="bi bi-chevron-right" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
              </svg>', $linkFecha2, ['class' => 'btn btn-primary rounded-pill float-start', 'data-bs-toggle'=>'tooltip', 'data-bs-placement'=>'bottom', 'data-bs-original-title'=>'Dia Siguiente']);
          }
          ?>
        </div>

      </div>

    </div>
  </div>
</div>

<?php
if (isset($turnos) && count($turnos) == 0) {
  if (isset($_GET['fecha'])) {
    echo "<h3>No existen turnos para la fecha {$_GET['fecha']}.</h3>";
  } else {
    echo "<h3>No existen turnos pendientes.</h3>";
  }
}

$i = 1;
foreach ($turnos as $turno) { ?>
  <div class="card">
    <div class="card-body">

      <div class="row justify-content-center d-flex text-center align-items-center">

        <div <?php echo ($profesional != '')?'class="col-4" " style="font-size: 18px"':'class="col-xl-2 col-lg-3 border-end" " style="font-size: 24px"'?>>
          <h3>TURNO</h3>
          <span>
            <h4>#<?php echo $i; ?></h4>
          </span>
          <div style="font-size: 17px"><i class="bi bi-clock"></i></i> <?= $turno->hora ?></div>
        </div>

        <div class="<?php echo ($profesional != '') ? 'col-4' : 'col-7' ?>">
          <h4 class="mb-2"><?php echo $turno->persona->apellido . ', ' . $turno->persona->nombre; ?></h4>
          <h4 class="mb-2">DNI: <?php echo $turno->persona->documento; ?></h4>
          <?php 
              if($turno->id_consulta_referencia != 0):
                echo '<h4 class="mb-2"><span class="badge bg-info">Referencia</span></h4>';
              endif;
          ?>
          <p>Confirmado: <?php echo $turno->confirmado && $turno->confirmado == 'SI' ? '<span class="badge bg-success">Si</span>' : '<span class="badge bg-warning">No</span>'; ?>
            Programado: <?php echo $turno->programado == 0 ? '<span class="badge bg-warning">No</span>' : '<span class="badge bg-success">Si</span>' ?>
          </p>
        </div>

        <div class="col-3">
        <?php if($profesional != ''){?>
          <div class="col-xs-4">
            <h4>DNI: <?php echo $turno->persona->documento;?> - HC: <?php echo $turno->persona->obtenerNHistoriaClinica(Yii::$app->user->getIdEfector());?></h4>
          </div>
        <?php } else { ?>
            <?php echo Html::a(
              'No se presentó',
              $turno['id_turnos'],
              ['class' => 'btn btn-light', 'id' => 'no_se_presento']
            ); ?>
            <br /><br />
            <?php
            //TODO: agregarle un anchor para que cuando acceda al historial posicione en la linea de tiempo a la altura del turno
            $urlConsulta = Url::toRoute('paciente/historia/'.$turno['id_persona']);
            //$urlConsulta = Url::to(['paciente/historia/'.$turno['id_persona'].'?withOffcanvas='.ConsultasConfiguracion::crearUrlPorServicio($turno->id_servicio_asignado)]);
            echo Html::a('Cargar Consulta', $urlConsulta,
              ['class' => 'btn btn-success', 'id' => 'cargar_consulta']
            ); 
          }
          ?>

        </div>

      </div>
    </div>

  </div>
<?php
  $i++;
}

$this->registerJs(
  "
    $(document).on('click', '#no_se_presento', function(e) {

      if(!confirm('Seguro?')) return false;
      
      e.preventDefault();
      var id_turno = $(this).attr('href');

      var nosepresento = $.ajax({
          type: 'post',
          async: true,
          data:{id_turno:id_turno},
          url: '" . Url::to(['turnos/no-se-presento']) . "'
      });
      nosepresento.done(function(response){   
        $('#no_se_presento').attr('disabled', true);
        $('#cargar_consulta').attr('disabled', true);
        location.reload();
      });
    });"
);

?>