<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $sugerencia common\models\AbreviaturasSugeridas */

$this->title = 'Agregar expansión a sugerencia';
?>

<div class="abreviaturas-agregar-expansion">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php if ($sugerencia): ?>
        <p>ID: <?= Html::encode($sugerencia->id) ?></p>
        <p>Abreviatura: <?= Html::encode($sugerencia->abreviatura) ?></p>

        <?php $form = ActiveForm::begin(['action' => ['aprobar']]); ?>
            <?= Html::hiddenInput('id', $sugerencia->id) ?>
            <?= Html::textarea('expansion', $sugerencia->expansion_propuesta, ['class' => 'form-control', 'placeholder' => 'Expansión propuesta']) ?>
            <br>
            <?= Html::textarea('comentarios', '', ['class' => 'form-control', 'placeholder' => 'Comentarios (opcional)']) ?>
            <br>
            <?= Html::submitButton('Aprobar', ['class' => 'btn btn-success']) ?>
        <?php ActiveForm::end(); ?>

    <?php else: ?>
        <p>Sugerencia no encontrada.</p>
    <?php endif; ?>
</div>


