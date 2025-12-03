<?php

use yii\widgets\ActiveForm;
use yii\helpers\Html;

?>


<div class="numhistoriaclinica-form">

<?php $form = ActiveForm::begin([]); ?>

<div class="container-fluid" >
  
    <br><?= $form->field($model, 'numero_hc', ['template' => '{input}{error}{hint}'])->input("text",['placeholder' => "Ingrese el numero de historia clinica"]) ?>

</div>

<div class="container-fluid d-grid gap-2 d-md-flex justify-content-md-end">
    <?= Html::submitButton('Guardar', ['class' => 'mt-5 btn btn-primary']) ?> 
</div>

<?php ActiveForm::end(); ?>

</div>