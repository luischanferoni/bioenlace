<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\ConsultaPracticasOftalmologiaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Consulta Practicas Oftalmologias';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="consulta-practicas-oftalmologia-index">
    <div class="card">
        <div class="card-header">
            <div class="header-title">
                <div class="col-lg-8 col-xs-12">
                    <h1>Consultas Oftalmológicas</h1>
                <p>
                    <?= Html::a('Crear Consulta Oftalmológica', ['create'], ['class' => 'btn btn-success']) ?>
                </p>

                <p>
                    <?= Html::a('Crear Consulta Oftalmológica Medico', ['create-medico'], ['class' => 'btn btn-success']) ?>
                </p>
                </div>
            </div>
        </div>
        <div class="card-body">
            <dl class="row">

            <?php # echo $this->render('_search', ['model' => $searchModel]); ?>

            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel' => $searchModel,
                'columns' => [
                    ['class' => 'yii\grid\SerialColumn'],

                    'id',
                    'id_consulta',
                    'codigo',
                    'ojo',
                    'prueba',
                    //'estado',
                    //'resultado:ntext',
                    //'informe:ntext',
                    //'adjunto:ntext',

                    ['class' => 'yii\grid\ActionColumn'],
                ],
            ]); ?>
            </dl>
        </div>
    </div>
</div>
