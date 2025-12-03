<?php

use yii\helpers\Html;
use yii\grid\GridView;


/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\MensajesBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Mensajes';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="mensajes-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <p><td>
        <?= Html::a('Nuevo Mensaje', ['create'], ['class' => 'btn btn-success']) ?>
       </td> </p>
    

  <?php //   GridView::widget([
       // 'dataProvider' => $dataProvider,
      //  'filterModel' => $searchModel,
        //'columns' => [
          //  ['class' => 'yii\grid\SerialColumn'],

            //'id',
            //'id_emisor',
            //'id_receptor',
            //'texto:ntext',
           // 'estado',
            // 'fecha',

         //   ['class' => 'yii\grid\ActionColumn'],
      //  ],
  //  ]);  ?> 
<p><td>
        <?= Html::a('Mensajes Recibidos', ['/recibidos'], ['class' => 'btn btn-success'] )?>
       </td> </p>
<p><td>
        <?= Html::a('Mensajes Enviados', ['/enviados'], ['class' => 'btn btn-success']) ?>
       </td> </p>
</div>
