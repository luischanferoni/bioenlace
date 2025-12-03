<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

?>

<div class="row">
    <div class="col-sm-5">
        <div class="alert alert-info"> 
            <p>Ingrese la información solicitada para obtener todos los turnos pendientes</p>
            <ul>
                <li>Si no entiende las letras del codigo de verificación presione Actualizar captcha</li>
            </ul>
        </div>
    </div>
    <div class="col-sm-5">
        <div class="card">
            <div class="card-body">
                <?php $form = ActiveForm::begin([
                    'method' => 'post'
                ]); ?>

                <div class="form-group">
                    <?= $form->field($model, 'dni')->textInput();?>
                </div>  
                
                <?= $form->field($model, 'codigoVerificacion')->widget(\yii\captcha\Captcha::classname(), [
                    'template' => '<div class="col-12">{image}<a id="actualizar_captcha" href="#">Actualizar captcha</a></div>{input}'
                ]) ?>   

                <div class="form-group text-end">
                    <?= Html::submitButton('Buscar', ['id'=>'submitBtn','class' => 'btn btn-primary']) ?>        
                </div>

                <?php ActiveForm::end(); ?>
            </div>
        </div>
    </div>
</div>

<?php $this->registerJs("
    $('#actualizar_captcha').on('click', function(e){
        e.preventDefault();

        $('#turnolibrebusqueda-codigoverificacion-image').yiiCaptcha('refresh');        
    });


    $(document).ready(function() {
        $('#turnolibrebusqueda-codigoverificacion').on('keyup',function() {
           $('#submitBtn').removeClass('disabled');
        });
    });

    "
);
?>