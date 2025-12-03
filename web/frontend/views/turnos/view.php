<?php

use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use common\models\Persona;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\Turno */

$this->title = 'Turnos Otorgados';
$this->params['breadcrumbs'][] = ['label' => 'Turnos', 'url' => ['index']];


$this->params['breadcrumbs'][] = "Turnos Otorgados";
?>
<div class="turno-view">

  <div class="card">
    <div class="card-header bg-soft-info">
      <h4>Turnos Otorgados</h4>
    </div>
    <div class="card-body">

          <?php
          if (is_array($model->turnos) && count($model->turnos)>0 ) {

            echo '<div class="table-responsive mt-2">';
            echo '<table class="table table-striped table-bordered">';
            echo '<tr><th>Fecha y hora</th>'
              . '<th>Centro de Salud</th>';
            echo '<th>Profesional</th>';
            echo '<th>Servicio</th>';
            echo '<th>Programado</th>';
            echo '<th>Referenciado</th>';
            echo '<th>Otorgado por</th>';
            echo '</tr>';


            foreach ($model->turnos as $key => $turno) {

              echo '<tr>';
              echo '<td>';
              echo Yii::$app->formatter->asDate($turno->fecha, 'dd/MM/yyyy') . ' ' . $turno->hora;
              echo '</td>';
              echo '<td>';
              echo $turno->efector->nombre;
              echo '</td>';
              echo '<td>';
              echo isset($turno->rrhhServicioAsignado)?$turno->rrhhServicioAsignado->rrhhEfector->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N):'Sin asignar';
              echo '</td>';
              echo '<td>';
              echo isset($turno->servicio)?$turno->servicio->nombre:$turno->rrhhServicioAsignado->servicio->nombre;
              echo '</td>';
              echo '<td>';
              echo ($turno->programado == 1) ? 'Si' : 'No';
              echo '</td>';
              echo '<td>';
              echo $turno->referenciado;
              echo '</td>';
              echo '<td>';
              if (isset($turno->userAlta)) {
                echo $turno->userAlta->nombre . ' ' . $turno->userAlta->apellido;
              }else{
                echo $turno->rrhhEfector->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N);
              }
              echo '</td>';
              /* echo '<td>';
    echo Html::a('<span class="glyphicon glyphicon-trash" aria-hidden="true"> </span>', 
                  ['turnos/delete','id' =>  $turno->id_turnos],
            ['data-confirm' => '¿Está seguro de eliminar el turno?',
                'data-method'=>'post', 'data-pjax'=>0]);
    echo '</td>';  */
              echo '</tr>';
            }
            echo '</table>';
            echo '</div>';

          }

          else
          {
            echo '<h4 class="text-center"> Este paciente no tiene turnos asignados.</h4>';

          }
          ?>
   
    </div>
  </div>
</div>
</div>