<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $abreviatura common\models\AbreviaturasMedicas */

$this->title = 'Actualizar abreviatura';
?>

<div class="abreviaturas-actualizar">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php if ($abreviatura): ?>
        <?php $form = ActiveForm::begin(['action' => ['actualizar']]); ?>
            <?= Html::hiddenInput('id', $abreviatura->id) ?>
            <?= Html::input('text', 'abreviatura', $abreviatura->abreviatura, ['class' => 'form-control']) ?>
            <br>
            <?= Html::input('text', 'expansion_completa', $abreviatura->expansion_completa, ['class' => 'form-control']) ?>
            <br>
            <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
        <?php ActiveForm::end(); ?>
    <?php else: ?>
        <p>Abreviatura no encontrada.</p>
    <?php endif; ?>
</div>


