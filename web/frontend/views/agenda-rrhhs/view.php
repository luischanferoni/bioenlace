<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use common\models\Persona;
use common\models\Rrhh;
use yii\helpers\ArrayHelper;
use assets\AppAsset; /* AppAsset::register($this);

  /* @var $this yii\web\View */

/* @var $model common\models\Agenda_rrhh */

$this->title = Persona::findOne(["id_persona" => $model->rrhh->id_persona])->nombreCompleto(Persona::FORMATO_NOMBRE_A_N_D);
$this->params['breadcrumbs'][] = ['label' => 'Agenda', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="agenda-rrhh-view">

    <div class="h3"><?= Html::encode('Agenda de '.$this->title) ?></div>
    <div class="h4"><?= Html::encode($model->efector->nombre.' - Días '. $model->tipo_dia->nombre.'es') ?></div>
    <p>
        <?php echo Html::a('Actualizar', ['update', 'id' => $model->id_agenda_rrhh], ['class' => 'btn btn-primary']) ?>
        <?php echo Html::a('Eliminar', ['delete', 'id' => $model->id_agenda_rrhh], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Está seguro que desea eliminar este ítem?',
                'method' => 'post',
            ],
        ])
        ?>
    </p>

    <?=
    DetailView::widget([
        'model' => $model,
        'attributes' => [
//            'id_agenda_rrhh',
//            'id_rr_hh',
//            [   
//                'attribute'=>'id_rr_hh',
//                'value'=>Persona::findOne(["id_persona" => $model->id_rr_hh])->PersonaDatos,
//            ],
            'fecha_inicio',
            'fecha_fin',
            [
            'label' => 'lunes',
            'value' => ($model->lunes == 'SI')?$model->hora_inicio.' a '.$model->hora_fin:'-'
            ],
            [
            'label' => 'martes',
            'value' => ($model->martes == 'SI')?$model->hora_inicio.' a '.$model->hora_fin:'-'
            ],
            [
            'label' => 'miercoles',
            'value' => ($model->miercoles == 'SI')?$model->hora_inicio.' a '.$model->hora_fin:'-'
            ],
            [
            'label' => 'jueves',
            'value' => ($model->jueves == 'SI')?$model->hora_inicio.' a '.$model->hora_fin:'-'
            ],
            [
            'label' => 'viernes',
            'value' => ($model->viernes == 'SI')?$model->hora_inicio.' a '.$model->hora_fin:'-'
            ],
            [
            'label' => 'sabado',
            'value' => ($model->sabado == 'SI')?$model->hora_inicio.' a '.$model->hora_fin:'-'
            ],
            [
            'label' => 'domingo',
            'value' => ($model->domingo == 'SI')?$model->hora_inicio.' a '.$model->hora_fin:'-'
            ],
            
            
        ],
    ])
    ?>

</div>
