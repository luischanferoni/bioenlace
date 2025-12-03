<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\CovidEntrevistaTelefonicaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Listado de Entrevistas Telefonicas Post Covid-19';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="covid-entrevista-telefonica-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'rowOptions'=>function($data){            
                return ['class' => $data->getPuntajeEntrevista()[1]];
            },
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

             [
                 'attribute'=>'id_persona',
                 'label'=>'Persona',
                 'format'=>'raw',
                 'value' => function($data) {
                               return  $data->persona->nombreCompleto;
                            }
             ],           
            'resultado',
            [
                 'attribute'=>'fecha_inicio_sintomas',
                 'label'=>'Inicio Sintomas',
                 'format'=>'raw',
                 'value' => function($data) {
                               return  $data->covidInvestigacionEpidemiologica->fecha_inicio_sintomas;
                        }
             ],
             [
                 'attribute'=>'fecha_fin_aislamiento',
                 'label'=>'Fin del aislamiento',
                 'format'=>'raw',
                 'value' => function($data) {
                               return  $data->covidInvestigacionEpidemiologica->fecha_fin_aislamiento;
                        }
             ],
             [
                 'attribute'=>'puntaje_entrevista',
                 'label'=>'Riesgo',
                 'format'=>'raw',
                 'value' => function($data) {
                    $resultado = $data->getPuntajeEntrevista();
                               return  $resultado[0];
                        }
             ],
             [
                 'attribute'=>'id_efector',
                 'label'=>'Efector',
                 'format'=>'raw',
                 'value' => function($data) {
                               return  $data->efector->nombre;                     
                                
                            }
             ], 
            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>


</div>
