<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model array{name: string, description: string} */

$this->title = 'Nuevo rol';
$this->params['breadcrumbs'][] = ['label' => 'Roles RBAC', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="rbac-role-create">

    <h1 class="h2 mb-3"><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin(); ?>

    <div class="mb-3">
        <label class="form-label" for="role-name">Nombre</label>
        <input type="text" class="form-control" id="role-name" name="name" required
               pattern="[A-Za-z0-9_.-]+" maxlength="191"
               value="<?= Html::encode($model['name']) ?>">
        <div class="form-text">Letras, números, punto, guión o guión bajo.</div>
    </div>

    <div class="mb-3">
        <label class="form-label" for="role-description">Descripción</label>
        <input type="text" class="form-control" id="role-description" name="description"
               maxlength="255" value="<?= Html::encode($model['description']) ?>">
    </div>

    <?= Html::submitButton('Crear', ['class' => 'btn btn-primary']) ?>
    <?= Html::a('Cancelar', ['index'], ['class' => 'btn btn-outline-secondary ms-2']) ?>

    <?php ActiveForm::end(); ?>
</div>
