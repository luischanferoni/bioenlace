<?php

use common\models\Persona;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\persona_telefono */

$persona = Persona::findOne($model->id_persona);
$this->title = 'Actualizar Telefono';
$this->params['breadcrumbs'][] = ['label' => 'Persona Telefonos', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id_persona_telefono, 'url' => ['view', 'id' => $model->id_persona_telefono]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="persona-telefono-update">

    <div class="card">
        <div class="card-header bg-soft-info">
            <h1><?= Html::encode($this->title) . 'de : ' . $persona->apellido . ', ' . $persona->nombre ?></h1>
        </div>
        <div class="card-body">
            <?= $this->render('_form', [
                'model' => $model,
            ]) ?>
        </div>
    </div>

</div>