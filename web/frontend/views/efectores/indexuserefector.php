<?php

/**
 * View del index de Efectores para el usuario logueado
 * Muestra el listado con los efectores designados al usuario logueado 
 * desde donde podrá acceder a cada uno mediante un link
 * 
 * @autor: Ivana y Griselda
 * @creacion: 22/10/2015
 * @modificacion: 27/10/2015 - lineas 36 a 67
 */

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\EfectorBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Seleccione el Efector en el cual desea trabajar para poder continuar';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="card">
    <div class="card-body">
        <div class="card-header">
            <h3><?= Html::encode($this->title) ?></h3>
        </div>

        <div class="custom-table-effect">

            <?= GridView::widget([
                'dataProvider' => $dataProvider,                
                'columns' => [
                    [
                        'attribute'=>'nombre',
                        'label'=>'Efector',
                        'format'=>'raw',
                        'value' => function ($model, $key, $index) { 
                            return Html::a($model->nombre, ['site/session-efector-redireccionar', 'id_efector' => $model->id_efector]);
                        },

                    ],
            
                ],
            ]); ?>

        </div>
    </div>
</div>