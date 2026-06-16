<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\GridView;
use yii\web\JsExpression;

use yii\bootstrap5\Modal;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Agenda Feriados';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="agenda-feriados-index">

    <div class="card">
        <div class="card-header bg-soft-info d-flex">
            <div class="col">
                <h3>Listado de Feriados</h3>
            </div>
            <div class="col">
                <div class="d-flex justify-content-end">
                    <?= Html::a('Nuevo Feriado', ['create'], ['class' => 'btn btn-success']) ?>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                //'filterModel' => $searchModel,
                'columns' => [
                    ['class' => 'yii\grid\SerialColumn'],
                    'titulo',
                    'fecha',
                    'repite_todos_anios',
                    'horario',

                    ['class' => 'yii\grid\ActionColumn'],
                ],
            ]); ?>
        </div>
    </div>


</div>