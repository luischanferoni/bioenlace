<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

?>

<div class="row">
    <div class="col-sm-5">
        <div class="alert alert-info"> 
            <p>Ingrese la información solicitada para obtener los resultados de sus estudios.</p>
            <ul>
                <li>Si no entiende las letras del codigo de verificación presione Actualizar captcha</li>
            </ul>
        </div>
    </div>
    <div class="col-sm-7">
        <div class="card card-block card-stretch card-height">
            <div class="card-body">
                <?php $form = ActiveForm::begin([
                    'method' => 'post'
                ]); ?>
                
                <div class="form-group">
                    <?= $form->field($model, 'tipo_estudio')->DropDownList($model::TIPOS_ESTUDIOS);?>
                </div>

                <div class="form-group">
                    <?= $form->field($model, 'dni')->textInput();?>
                </div>
                                
                <?= $form->field($model, 'codigoVerificacion')->widget(\yii\captcha\Captcha::classname(), [
                    'template' => '<div class="row"><div class="col-12">{image}<a id="actualizar_captcha" href="#">Actualizar captcha</a></div><div class="col-12">{input}</div></div>'
                ]) ?>    

                <div class="form-group pull-right">
                    <?= Html::submitButton('Buscar', ['class' => 'btn btn-primary']) ?>        
                </div>

                <?php ActiveForm::end(); ?>
            </div>
        </div>
    </div>
</div>

<?php $this->registerJs("
    $('#actualizar_captcha').on('click', function(e){
        e.preventDefault();

        $('#laboratoriobusqueda-codigoverificacion-image').yiiCaptcha('refresh');        
    });
    "
);
?>