<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model app\models\persona_telefono */

$this->title = 'Nuevo Telefono';
$this->params['breadcrumbs'][] = ['label' => 'Persona Telefonos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="persona-telefono-create">

    <div class="card">
        <div class="card-header bg-soft-info">
            <h1><?= Html::encode($this->title) ." para ".$model_persona->apellido . ', ' . $model_persona->nombre ?></h1>
        </div>


        <div class="card-body">
            <?= $this->render('_form', [
                'model' => $model,
                'model_persona' => $model_persona,
            ]) ?>
        </div>
    </div>

</div>