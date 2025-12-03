
<?php

use yii\widgets\ActiveForm;

use common\models\Persona;

?>

<h3>Editando consulta</h3>
<blockquote class="blockquote mb-3">
    Consulta para paciente <b><?=$consulta->paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D)?></b> con fecha del <?= Yii::$app->formatter->asDate($consulta->created_at, 'dd/MM/yyyy');?>
    <br>El editar consulta crea una nueva por sobre la anterior, dejando registrada la consulta editada sin eliminarla
</blockquote>

<div class="text-center pb-5">
    <?php $form = ActiveForm::begin(); ?>
        <button type="submit" class="btn btn-info mt-2">Continuar</button>
    <?php ActiveForm::end(); ?>
</div>