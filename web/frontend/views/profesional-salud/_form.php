<?php

use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use kartik\depdrop\DepDrop;
use kartik\select2\Select2;
use yii\widgets\ActiveForm;

use common\models\Profesiones;
use common\models\Especialidades;


/* @var $this yii\web\View */
/* @var $model common\models\ProfesionalSalud */
/* @var $form yii\widgets\ActiveForm */
?>

<?php $form = ActiveForm::begin(); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <div class="header-title">
            <h4 class="card-title">Profesiones</h4>
        </div>
    </div>
    <div class="card-body">

        <?php echo Html::hiddenInput('especialidades_seleccionadas', 
                            json_encode($persona_especialidades), 
                            ['id' => 'especialidades_seleccionadas']); ?>

        <div class="form-group">
            <?php
                $profesiones = ArrayHelper::map(Profesiones::find()->asArray()->all(), 'id_profesion', 'nombre');

                echo Select2::widget([
                    'name' => 'profesiones',
                    'value' => $persona_profesiones,
                    'data' => $profesiones,
                    'theme' => Select2::THEME_DEFAULT,
                    //'size' => Select2::LARGE,
                    'options' => ['multiple' => true, 'placeholder' => 'Seleccione Profesión', 'id' => 'profesiones'],
                    'pluginOptions' => [
                        'allowClear' => true
                    ],
                ]);
            ?>
        </div>
    </div>

    <div class="card-header d-flex justify-content-between">
        <div class="header-title">
            <h4 class="card-title">Especialidades</h4>
        </div>
    </div>
    <div class="card-body">
        <div class="form-group">
            <?php
                echo DepDrop::widget([
                    'name' => 'especialidades',
                    'options' => ['id' => 'especialidades', 'placeholder' => 'Seleccione Especialidad', 'multiple' => true],
                    'type' => DepDrop::TYPE_SELECT2,
                    'select2Options' => ['theme' => Select2::THEME_DEFAULT],
                    'pluginOptions' => [
                        'initialize' => true,
                        'depends' => ['profesiones'],
                        'url' => Url::to(['/profesional-salud/especialidades']),
                        'loadingText' => 'Cargando especialidades ...',
                        'params' => ['especialidades_seleccionadas']
                    ]
                ]);
            ?>
        </div>
    </div>

    <div class="card-body">
        <div class="form-group">
            <div class="form-group float-end">
                <?= Html::submitButton('Siguiente Paso - Asignar Servicio', ['class' => 'btn btn-success']) ?>
            </div>
        </div>
    </div>
</div>
    
<?php ActiveForm::end(); ?>



<?php
$this->registerJs(' 
    $(\'#especialidades\').on(\'change\', function(e) {
        var a = $(this).val().join(\'","\');
        $(\'#especialidades_seleccionadas\').val(\'["\' + a + \'"]\');
    });', yii\web\View::POS_READY);
?>