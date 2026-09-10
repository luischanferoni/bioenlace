<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $abreviatura common\models\Clinical\AbreviaturasMedicas */

$this->title = 'Eliminar abreviatura';
?>

<div class="abreviaturas-eliminar">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php if ($abreviatura): ?>
        <p>ID: <?= Html::encode($abreviatura->id) ?></p>
        <p>Abreviatura: <?= Html::encode($abreviatura->abreviatura) ?></p>

        <?php $form = ActiveForm::begin(['action' => ['eliminar']]); ?>
            <?= Html::hiddenInput('id', $abreviatura->id) ?>
            <p>¿Confirma eliminar esta abreviatura?</p>
            <?= Html::submitButton('Eliminar', ['class' => 'btn btn-danger']) ?>
        <?php ActiveForm::end(); ?>

    <?php else: ?>
        <p>Abreviatura no encontrada.</p>
    <?php endif; ?>
</div>


