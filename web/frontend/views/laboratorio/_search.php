<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

?>

<div class="laboratorio-search">

    <?php $form = ActiveForm::begin([
        'method' => 'post'
    ]); ?>
    
    <div class="form-group">
        <?= $form->field($model, 'tipo_estudio')->DropDownList($model::TIPOS_ESTUDIOS);?>
    </div>

    <div class="form-group">
        <?= $form->field($model, 'dni')->textInput();?>
    </div>

    <?php if($accesolibre) { ?>
                    
            <?= $form->field($model, 'codigoVerificacion')->widget(\yii\captcha\Captcha::classname(), [
                'template' => '<div class="row"><div class="col-lg-12"><div class="col-lg-6">{image}<a id="actualizar_captcha" href="#">Actualizar captcha</a></div><div class="col-lg-6">{input}</div></div></div>'
            ]) ?>
                
    <?php }?>

    <div class="form-group pull-right">
        <?= Html::submitButton('Buscar', ['class' => 'btn btn-primary']) ?>        
    </div>

    <?php ActiveForm::end(); ?>

</div>
<?php $this->registerJs("
    $('#actualizar_captcha').on('click', function(e){
        e.preventDefault();

        $('#laboratoriobusqueda-codigoverificacion-image').yiiCaptcha('refresh');        
    });
    "
);
?>