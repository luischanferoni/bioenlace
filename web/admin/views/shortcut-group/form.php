<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\Platform\AssistantShortcutGroup */
/* @var $isNew bool */

$this->title = $isNew ? 'Nuevo grupo de atajos' : 'Editar grupo «' . $model->group_id . '»';
$this->params['breadcrumbs'][] = ['label' => 'Catálogo de permisos', 'url' => ['/permission-catalog/index']];
$this->params['breadcrumbs'][] = ['label' => 'Grupos de atajos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="shortcut-group-form">

    <h1 class="h2 mb-3"><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin(); ?>

    <?php if ($isNew): ?>
        <?= $form->field($model, 'group_id')->textInput([
            'maxlength' => true,
            'placeholder' => 'urgencias',
        ])->hint('Debe coincidir con el prefijo del intent_id (antes del primer punto).') ?>
    <?php else: ?>
        <p class="mb-3">Prefijo: <code><?= Html::encode($model->group_id) ?></code></p>
    <?php endif; ?>

    <?= $form->field($model, 'label')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'sort_order')->input('number', ['min' => 0, 'step' => 10]) ?>

    <div class="form-group">
        <?= Html::submitButton($isNew ? 'Crear' : 'Guardar', ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Cancelar', ['index'], ['class' => 'btn btn-outline-secondary ms-2']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
