<?php

use yii\helpers\Html;
use yii\grid\GridView;


/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\EspecialidadesBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Especialidades';
$this->params['breadcrumbs'][] = $this->title;
    
?>
<div class="especialidades-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <p>
        <?= Html::a('Agregar Especialidades', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        //'id_profesion'=> $profesion,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
           // 'id_profesion',
            [
            'attribute' => 'id_profesion',
            'value' => function($data) {
                return \common\models\Profesiones::findOne(["id_profesion" => $data->id_profesion])->nombre;
            }
            ],
            'nombre',
            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>

</div>
<div class="especialidades-index">
        
        <?= Html::a('Actualizar',[''],['class' => 'btn btn-success']) ?>
    </div> 

<p>
<div class="especialidades-form">
   
        <?= Html::a('Volver a Profesión', ['..\profesiones'], ['class' => 'btn btn-success']) ?>
    
</div> 
</p>
