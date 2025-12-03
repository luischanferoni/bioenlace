<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use common\models\ConsultasConfiguracion;


/* @var $this yii\web\View */
/* @var $model app\models\ConsultasConfiguracion */

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Consultas Configuracions', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="consultas-configuracion-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Update', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>       
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            [
                'attribute' => 'id_servicio',
                'label' => 'Servicio',
                'value' => function($data) {
                    return $data->servicio->nombre;
                }
            ],            
            'pasos:ntext',
            [
                'attribute' => 'encounter_class',
                'label' => 'Tipo Atención',
                'value' => function($data) {
                    return ConsultasConfiguracion::ENCOUNTER_CLASS[$data->encounter_class];
                }
            ],
            [
                'attribute' => 'created_at',
                'label' => 'Creado el día',
                'value' => function($data) {
                    return date('d/m/Y h:i:s', strtotime($data->created_at));
                }
            ], 
            [
                'attribute' => 'created_by',
                'label' => 'Creado por',
                'value' => function($data) {
                    return $data->createdBy->username;
                }
            ],  
            
        ],
    ]) ?>

</div>
