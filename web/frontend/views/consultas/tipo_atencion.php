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

$this->title = 'Seleccione el Tipo de atencion que está por realizar para poder continuar';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="card">
    <div class="card-body">
        <div class="card-header">
            <h3><?= Html::encode($this->title) ?></h3>
        </div>

        <div class="custom-table-effect">
<?php //echo "<pre>";var_dump($provider);echo "</pre>";?>
            <?= GridView::widget([
                'dataProvider' => $provider,                
                'columns' => [
                    [
                        'attribute'=> 1,
                        'label'=>'Tipo de Atención',
                        'format'=>'raw',
                        'value' => function ($data, $key, $index) { 
                            return Html::a($data, ['site/session-encounterclass-redireccionar', 'codigo' => $key]);
                        },

                    ],
            
                ],
            ]); ?>

        </div>
    </div>
</div>