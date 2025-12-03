<?php

use yii\helpers\Html;
use yii\grid\GridView;
//use common\models\Usuarios;
use \webvimark\modules\UserManagement\models\User;
/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\RecibidosBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Recibidos';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="recibidos-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        //'filterModel' => $searchModel,
        'columns' => [
            [
            'attribute' => 'id_receptor',
            'value' => function($data) {
            return User::findOne(
            ["id" => $data->id_receptor])-> username;
            }
            ],
            'texto:ntext',
             'fecha',
            // 'estado',
           ['class' => 'yii\grid\ActionColumn', 'template' => '{view} {delete}'],
        ],
    ]); ?>
    
    <p>
        <?= Html::a('Volver', ['/mensajes'], ['class' => 'btn btn-success']) ?>
    </p>
</div>
