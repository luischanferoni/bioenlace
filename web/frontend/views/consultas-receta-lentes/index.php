<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\ConsultasRecetaLentesBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Consultas Receta Lentes';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="consultas-receta-lentes-index">
    <div class="card">
        <div class="card-header">
            <div class="header-title">
                <div class="col-lg-8 col-xs-12">
                    <h1>Recetas Oftálmicas</h1>
                    <p>
                        <?= Html::a('Crear Receta Oftálmica', ['create'], ['class' => 'btn btn-success']) ?>
                    </p>
                </div>
            </div>
        </div>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>
        <div class="card-body">
            <dl class="row">
                <?= GridView::widget([
                    'dataProvider' => $dataProvider,
                    'filterModel' => $searchModel,
                    'columns' => [
                        ['class' => 'yii\grid\SerialColumn'],

                        'id',
                        'oi_esfera',
                        'od_esfera',
                        'oi_cilindro',
                        'od_cilindro',
                        //'oi_eje',
                        //'od_eje',

                        ['class' => 'yii\grid\ActionColumn'],
                    ],
                ]); ?>
            </dl>
        </div>
    </div>


</div>
