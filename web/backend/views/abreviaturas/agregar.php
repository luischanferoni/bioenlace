<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */

$this->title = 'Agregar abreviatura';
?>

<div class="abreviaturas-agregar">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin(['action' => ['agregar']]); ?>
        <?= Html::input('text', 'abreviatura', '', ['class' => 'form-control', 'placeholder' => 'Abreviatura']) ?>
        <br>
        <?= Html::input('text', 'expansion_completa', '', ['class' => 'form-control', 'placeholder' => 'Expansión completa']) ?>
        <br>
        <?= Html::submitButton('Agregar', ['class' => 'btn btn-primary']) ?>
    <?php ActiveForm::end(); ?>
</div>


