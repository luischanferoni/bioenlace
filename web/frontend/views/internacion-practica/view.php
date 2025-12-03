<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionPractica */

$this->title = 'Práctica: '.$model->practicaSnomed->term;
$this->params['breadcrumbs'][] = ['label' => 'Practicas', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="seg-nivel-internacion-practica-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Actualizar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Eliminar', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => '¿Está seguro de elimir este elemento?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            //'id',
            //'conceptId',
            [   //muestra el nombre de la sala seleccionado en el listado
                'label'=> 'Concepto',
                'attribute'=>'conceptId',
                'value'=>$model->practicaSnomed->term,
            ],
            'resultado',
            'informe:ntext',
            //'id_rrhh_solicita',
            [   //muestra el nombre del responsable seleccionado en el listado
                'label'=> 'RRHH Solicita',
                'attribute'=>'id_rrhh_solicita',
                'value'=>$model->rrhhSolicita->rrhh->idPersona->nombreCompleto,
            ],
            //'id_rrhh_realiza',
            [   //muestra el nombre del responsable seleccionado en el listado
                'label'=> 'RRHH Realiza',
                'attribute'=>'id_rrhh_realiza',
                'value'=>$model->rrhhRealiza->rrhh->idPersona->nombreCompleto,
            ],
            [
               'attribute'=>'imageFile',
               'value'=> $model->fileName ? ('practicas/' . $model->fileName): "No registrado",
               'format' => ['image',['width'=>'230','height'=>'200']],
            ]
            //'id_internacion',
        ],
    ]) ?>

    <p>
        <?= Html::a('Volver', ['internacion/view', 'id'=> $model->id_internacion], ['class' => 'btn btn-success']) ?>
    </p>


</div>
