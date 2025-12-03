<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\DomicilioBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Domicilios';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="domicilio-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <p>
        <?php //echo  Html::a('Nuevo Domicilio', ['create'], ['class' => 'btn btn-success']) ?>
         <div role="alert" class="alert alert-success">
             Para agregar un nuevo domicilio, vaya a la <a href="<?= Url::toRoute('personas')?>"><strong>Sección Personas </strong></a>
      </div>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id_domicilio',
            'calle',
            'numero',
            'manzana',
            'lote',
            // 'sector',
            // 'grupo',
            // 'torre',
            // 'depto',
            // 'barrio',
            // 'id_localidad',
            // 'latitud',
            // 'longitud',
            // 'urbano_rural',
            // 'usuario_alta',
            // 'fecha_alta',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>

</div>
