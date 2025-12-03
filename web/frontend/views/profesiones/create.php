<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\Profesiones */

$this->title = 'Agregar Profesión';
$this->params['breadcrumbs'][] = ['label' => 'Profesiones', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="profesiones-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
    

</div>
<div class="profesiones-form">
        
        <?= Html::a('Agregar Especialidad', ['..\especialidades'], ['class' => 'btn btn-success']) ?>
    </div> 
<p>
<div class="profesiones-form">
   
        <?= Html::a('Volver a Profesión', ['..\profesiones'], ['class' => 'btn btn-success']) ?>
    
</div> 
</p>
