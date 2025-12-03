<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\LaboratorioNbuSnomedBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Equivalencas Nbu Snomed';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="laboratorio-nbu-snomed-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Equivalencias Nbu Snomed', ['create-masivo'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            [
                'attribute' => 'codigo',
                'label' =>'Código NBU',
                'value' => function ($data) {
                    $retorno = $data->laboratorioNbu->codigo.' - '.$data->laboratorioNbu->nombre;
                    return $retorno;
                }
            ],
            [
                'attribute' => 'conceptId',
                'label' =>'Término Snomed',
                'value' => function ($data) {
                    if(is_object($data->snomed)){
                        $retorno = $data->snomed->term;
                    }
                    else {
                        $retorno = $data->conceptId;
                    }
                    return $retorno;
                }
            ],

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>


</div>
