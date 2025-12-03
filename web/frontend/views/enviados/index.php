<?php

use yii\helpers\Html;
use yii\grid\GridView;
use common\models\Usuarios;


/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\EnviadosBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Enviados';
$this->params['breadcrumbs'][] = $this->title;

?>
<div class="enviados-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>


    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        //'filterModel' => $searchModel,
        'columns' => [
            [
            'attribute' => 'id_emisor',
                'value' => function($data) {
            return \common\models\Usuarios::findOne(
            ["id" => $data->id_emisor])-> username;
            }
            ],
           // array('attribute'=>'texto'),
            'texto:ntext',
            'fecha',
           // 'estado',
            ['class' => 'yii\grid\ActionColumn', 'template' => '{view} {delete}'],
        ],
    ]); ?>
<p><td>
        <?= Html::a('Volver', ['/mensajes'], ['class' => 'btn btn-success']) ?>
       </td> </p>
</div>
