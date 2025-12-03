<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Especialidades */

$this->title = 'Actualizar Especialidades: ' . ' ' . $model->id_especialidad;
$this->params['breadcrumbs'][] = ['label' => 'Especialidades', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id_especialidad, 'url' => ['view', 'id' => $model->id_especialidad]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="especialidades-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
 <div class="especialidades-form">
        
        <?= Html::a('Volver a Profesión', ['..\profesiones'], ['class' => 'btn btn-success']) ?>
    </div> 