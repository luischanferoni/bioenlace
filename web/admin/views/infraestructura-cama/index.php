<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use common\models\InfraestructuraSala;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\InfraestructuraCamaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Camas';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="infraestructura-cama-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Crear Cama', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            //'id',
            'nro_cama',
            'respirador',
            'monitor',
            //'id_sala',
            [
                'attribute' => 'sala.descripcion',
                'label' => 'Sala',                
                'filter' => Html::activeDropDownList($searchModel, 'id_sala', 
                            ArrayHelper::map(InfraestructuraSala::find()->all(),'id_sala', 'descripcion'), 
                            ['class' => 'form-control', 
                            'prompt' => '- Seleccione -'])
            ],
            'estado',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>


</div>
