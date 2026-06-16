<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\ServicioBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Servicios';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="servicio-index">   

    <div class="col-12">
    <div class="card">
        <div class="card-header">
            <div class="header-title d-flex align-items-self justify-content-between">
                <h2 class="card-title mt-1"><?= Html::encode($this->title) ?></h2>
                <?= Html::a('Nuevo Servicio', ['create'], ['class' => 'btn btn-success']) ?>
            </div>
        </div>
        <div class="card-body">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [            
            
            'nombre',
            'acepta_turnos',
            'acepta_practicas',
            [
                'attribute' => 'parametros',
                'label' => 'Color',
                'format' => 'raw',
                'value' => function($data) {
                    $params = unserialize($data->parametros);
                    //$params = json_decode($params);
                    //print_r($params);die;
                    $color = isset($params['color'])?$params['color']:'#fff';
                    return '<svg xmlns="http://www.w3.org/2000/svg" class="icon-32" width="32" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="7.5" fill="'.$color.'" stroke="currentColor"></circle>
                    </svg>';
                }
            ],
            [
                'attribute' => 'item_name',
                'label' => 'Rol',
                'value' => function($data) {
                    return $data->item_name;
                }
            ],
            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>

</div>
</div>
    </div>
</div>