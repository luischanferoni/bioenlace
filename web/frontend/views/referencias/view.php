<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\referencia */

$this->title = $model->id_referencia;
$this->params['breadcrumbs'][] = ['label' => 'Referencias', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="referencia-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Modificar', ['update', 'id' => $model->id_referencia, 'idc' => $model->id_consulta], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Eliminar', ['delete', 'id' => $model->id_referencia], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Realmente desea borrar este registro?',
                'method' => 'post',
            ],
        ]) ;
  
?>
    </p>
    <style>
        div .alert{
            position: relative !important;
        }
    </style>
 <div role="alert" class="alert alert-success">
     <p><strong> Datos de la  Referencia </strong></p>
        <p>Paciente: <strong><?php echo $persona[0]['apellido'].", ". $persona[0]['nombre']; ?></strong></p>
        <p>Turno: <strong><span class="glyphicon glyphicon-calendar"></span> <?php echo $persona[0]['fecha']." ". $persona[0 ]['hora'];?></strong></p>
    </div>
    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
//            'id_referencia',
//            'id_consulta',
            //'id_efector_referenciado',
           
           [                     
            'label' => 'Efector Referenciado',
            'value' =>  common\models\Efector::findOne($model->id_efector_referenciado)->nombre,
            ],
            //'id_motivo_derivacion',
            [                     
            'label' => 'Motivo de Derivación',
            'value' => common\models\MotivoDerivacion::findOne($model->id_motivo_derivacion)->nombre,
            ],
//            'id_servicio',
            [                     
            'label' => 'Servicio',
            'value' => common\models\Servicio::findOne($model->id_servicio)->nombre,
            ],
            'estudios_complementarios:ntext',
//            'resumen_hc:ntext',
            [
             'label' => "Diagn&oacute;stico Presuntivo",
             'value' => $model->resumen_hc,
            ],
            'tratamiento_previo',
            'tratamiento:ntext',
            //'id_estado',
            [                     
            'label' => 'Estado',
            'value' => common\models\Estado_solicitud::findOne($model->id_estado)->nombre,
            ],
            'fecha_turno',
            'hora_turno',
            'observacion:ntext',
        ],
    ]) ?>

</div>
