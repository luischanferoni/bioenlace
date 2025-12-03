<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use common\models\Efector;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\InfraestructuraPisoBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Pisos';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="infraestructura-piso-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Crear Piso', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

           // 'id',
            'nro_piso',
            'descripcion',
            //'id_efector',
            [
                'attribute' => 'efector.nombre',
                'label' => 'Efector',                
                'filter' => Html::activeDropDownList($searchModel, 'id_efector', 
                            ArrayHelper::map(Efector::find()->all(),'id_efector', 'nombre'), 
                            ['class' => 'form-control', 
                            'prompt' => '- Seleccione -'])
            ],

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>


</div>
