<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\Especialidades */

$this->title = $model->id_especialidad;
$this->params['breadcrumbs'][] = ['label' => 'Especialidades', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="especialidades-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Actualizar', ['update', 'id' => $model->id_especialidad], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Borrar', ['delete', 'id' => $model->id_especialidad], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Realmente desea borrar este registro?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
          //  'nbre_profesion',
            'id_especialidad',
            'nombre',
        ],
    ]) ?>
 <div class="especialidades-form">
        
        <?= Html::a('Agregar Otra Especialidad', ['..\especialidades'], ['class' => 'btn btn-success']) ?>
    </div> 
</div>
<p>
<div class="especialidades-form">
   
        <?= Html::a('Volver a Profesión', ['..\profesiones'], ['class' => 'btn btn-success']) ?>
    
</div> 
</p>
