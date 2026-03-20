<?php

use yii\helpers\Html;
use yii\grid\GridView;
use common\models\Cirugia;

/** @var yii\web\View $this */
/** @var common\models\busquedas\CirugiaBusqueda $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var int $idEfector */

$this->title = 'Agenda quirúrgica';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="quirofano-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Nueva cirugía', ['create-cirugia', 'id_efector' => $idEfector], ['class' => 'btn btn-success']) ?>
        <?= Html::a('Salas de quirófano', ['salas', 'id_efector' => $idEfector], ['class' => 'btn btn-default']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            'id',
            'id_persona',
            [
                'label' => 'Sala',
                'value' => function ($m) {
                    return $m->sala ? $m->sala->nombre : '';
                },
            ],
            [
                'attribute' => 'estado',
                'filter' => Cirugia::ESTADOS,
                'value' => function ($m) {
                    return $m->getEstadoLabel();
                },
            ],
            'fecha_hora_inicio',
            'fecha_hora_fin_estimada',
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{update}',
                'urlCreator' => function ($action, $model) {
                    return $action === 'update'
                        ? ['update-cirugia', 'id' => $model->id]
                        : '#';
                },
            ],
        ],
    ]); ?>
</div>
