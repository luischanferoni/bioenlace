<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\ProfesionesBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Profesiones';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="profesiones-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php  //echo $this->render('_search', ['model' => $searchModel]); ?>

    <p>
        <?= Html::a('Agregar Profesión', ['create'], ['class' => 'btn btn-success']) ?>
    <td>
        <?= Html::a('Agregar Especialidad', ['..\especialidades'], ['class' => 'btn btn-success']) ?>
    </td>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

          //  'id_profesion',
            'nombre',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>
    
</div>
<div class="profesiones-index">
        
        <?= Html::a('Actualizar',[''],['class' => 'btn btn-success']) ?>
    </div> 