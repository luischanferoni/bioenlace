<?php

use yii\helpers\Html;
//use yii\bootstrap\ActiveForm;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\ActiveField;
use nex\chosen\Chosen;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use common\models\Provincia;
use common\models\Departamento;
use common\models\Barrios;
use kartik\depdrop\DepDrop;
use wbraganca\dynamicform\DynamicFormWidget;
use kartik\select2\Select2;

$localidades = \common\models\Localidad::find()->indexBy('id_localidad')->asArray()->all();
$lista_localidades = \yii\helpers\ArrayHelper::map($localidades, 'id_localidad', 'nombre');
?>
<style type="text/css">
    div.required label.control-label:after {
        content: " *";
        color: red;
    }
</style>
<?php $form = ActiveForm::begin([
    'options' => ['id' => 'form-personas', 'class' => 'form-horizontal'], 'layout' => 'horizontal',
    'fieldConfig' => [
        'template' => "{label}\n{beginWrapper}\n{input}\n{hint}\n{error}\n{endWrapper}",
        'horizontalCssClasses' => [
            'label' => 'col-sm-4',
            'offset' => 'col-sm-offset-4',
            'wrapper' => 'col-sm-8',
            'error' => '',
            'hint' => '',
            //'field' => 'mb-3 row justify-content-center'
        ],
    ],
]);
?>
<?= $form->errorSummary($model); ?>
<div class="card">
    <div class="card-header bg-soft-info">
        <div class="row">
            <div class="col-sm-12">
                <h4 class="text-gray">Información Adicional</h4>
            </div>

        </div>
    </div>

    <div class="card-body">
        <div class="form-group row">
            <div class="col-sm-8">
                <?php
                echo $form->field($model, 'genero', ['labelOptions' =>  ['class' => 'col-sm-2 control-label align-self-center mb-0']])->inline()->radioList([1 => 'Femenino (F)', 2 => 'Masculino (M)', 3 => 'Otro', 4 => 'Indefinido (-)']);
                ?>
            </div>
            <div class="col-sm-4">
                <?php if (isset($model->otro_apellido) && $model->otro_apellido != '') { ?>
                    <?= $form->field($model, 'otro_apellido')->hiddenInput(['value' => $model->otro_apellido])->label(FALSE) ?><?php } else { ?>
                    <?= $form->field($model, 'otro_apellido', ['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->textInput() ?>
                <?php } ?>
            </div>
        </div>
        <div class="form-group row">
            <div class="col-sm-4">
                <?= $form->field($model, 'apellido_materno', ['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->textInput() ?>
            </div>
            <div class="col-sm-4">
                <?= $form->field($model, 'apellido_paterno', ['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->textInput() ?>
            </div>
            <div class="col-sm-4">
                <?= $form->field($model, 'id_estado_civil', ['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->dropDownList(
                    common\models\EstadoCivil::getListaEstadosCiviles(),
                    ['prompt' => 'Elija una opcion']
                ); ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-soft-info">
        <h3 class="text-gray">Contacto</h3>
    </div>

    <div class="card-body">
        <input type="hidden" name="id" id="id" value="<?= isset($_POST['id']) ? $_POST['id'] : (isset($id) ? $id : ""); ?>">
        <input type="hidden" name="tipo" id="tipo" value="<?= isset($_POST['tipo']) ? $_POST['tipo'] : (isset($tipo) ? $tipo : "") ?>">
        <input type="hidden" name="score" id="score" value="<?= isset($_POST['score']) ? $_POST['score'] : (isset($score) ? $score : ""); ?>">
        <?php

        DynamicFormWidget::begin([
            'widgetContainer' => 'dynamicform_wrapper', // required: only alphanumeric characters plus "_" [A-Za-z0-9_]
            'widgetBody' => '.container-items', // required: css class selector
            'widgetItem' => '.item', // required: css class
            'limit' => 2, // the maximum times, an element can be cloned (default 999)
            'min' => 1, // 0 or 1 (default 1)
            'insertButton' => '.add-item', // css class
            'deleteButton' => '.remove-item', // css class
            'model' => $model_persona_telefono[0],
            'formId' => 'form-personas',
            'formFields' => [
                'id_tipo_telefono',
                'numero',
            ],
        ]); ?>
        <div class="container-items"><!-- widgetContainer -->


            <div class="form-group row mb-5">
                <div class="col-sm-6 align-self-center">
                    <h4>Telefonos de contacto</h4>
                </div>

                <div div class="col-sm-6 align-self-center">
                    <button type="button" class="add-item btn btn-success btn-sm rounded-pill"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-plus" viewBox="0 0 16 16">
                            <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z" />
                        </svg></button>

                </div>
            </div>

            <?php foreach ($model_persona_telefono as $i => $model_persona_tel) : ?>
                <?= $form->errorSummary($model_persona_tel); ?>
                <div class="item"><!-- widgetBody -->

                    <?php
                    // necessary for update action.
                    if (!$model_persona_tel->isNewRecord) {
                        echo Html::activeHiddenInput($model_persona_tel, "[{$i}]id_persona_telefono");
                    }
                    ?>
                    <div class="form-group row mb-5">
                        <div class="col-sm-5">
                            <?php echo $form->field($model_persona_tel, "[{$i}]id_tipo_telefono", ['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->dropdownList(
                                common\models\Tipo_telefono::getTiposTelefonoxCategoria('PERSONA'),
                                ['prompt' => 'Seleccione una opcion']
                            )->label('Tipo de Teléfono'); ?>
                        </div>
                        <div class="col-sm-5">
                            <?= $form->field($model_persona_tel, "[{$i}]numero", ['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->textInput(['maxlength' => true]) ?>
                        </div>
                        <div class="col-sm-2">

                            <button type="button" class="remove-item btn btn-danger btn-sm rounded-pill"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-dash" viewBox="0 0 16 16">
                                    <path d="M4 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 4 8z" />
                                </svg></button>

                        </div>

                    </div><!-- .row -->

                </div>
            <?php endforeach; ?>
        </div>
        <?php DynamicFormWidget::end(); ?>
        <!-- --------------------DATOS EMAIL --------------------------*-->
        <?php DynamicFormWidget::begin([
            'widgetContainer' => 'dynamicform_wrapper_mails', // required: only alphanumeric characters plus "_" [A-Za-z0-9_]
            'widgetBody' => '.container-items-mails', // required: css class selector
            'widgetItem' => '.item-mails', // required: css class
            'limit' => 2, // the maximum times, an element can be cloned (default 999)
            'min' => 0, // 0 or 1 (default 1)
            'insertButton' => '.add-item-mails', // css class
            'deleteButton' => '.remove-item-mails', // css class
            'model' => $model_persona_mails[0],
            'formId' => 'form-personas',
            'formFields' => [
                'id_tipo_telefono',
                'numero',
            ],
        ]); ?>
        <div class="container-items-mails"><!-- widgetContainer -->


            <div class="form-group row mb-5 mt-5">
                <div class="col-sm-6 align-self-center">
                    <h4>Email de Contacto</h4>
                </div>

                <div div class="col-sm-6 align-self-center">
                    <button type="button" class="add-item-mails btn btn-success btn-sm rounded-pill"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-plus" viewBox="0 0 16 16">
                            <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z" />
                        </svg></button>

                </div>
            </div>
            <hr>



            <?php foreach ($model_persona_mails as $i => $model_persona_mail) : ?>
                <?= $form->errorSummary($model_persona_mail); ?>
                <div class="item-mails"><!-- widgetBody -->

                    <?php
                    // necessary for update action.
                    if (!$model_persona_mail->isNewRecord) {
                        echo Html::activeHiddenInput($model_persona_mail, "[{$i}]id_persona_mail");
                    }
                    ?>
                    <div class="form-group row">
                        <div class="col-sm-10">
                            <?= $form->field($model_persona_mail, "[{$i}]mail", ['options' => ['class' => ['widget' => 'mb-3 row justify-content-center']], 'labelOptions' =>  ['class' => 'col-sm-2 control-label align-self-center  ps-5']])->textInput(['maxlength' => true]) ?>
                        </div>
                        <div class="col-sm-2">
                            <button type="button" class="remove-item-mails btn btn-danger btn-sm rounded-pill"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-dash" viewBox="0 0 16 16">
                                    <path d="M4 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 4 8z" />
                                </svg></button>
                        </div>
                    </div><!-- .row -->
                </div>
            <?php endforeach; ?>
        </div>
        <?php DynamicFormWidget::end(); ?>

    </div>

</div>

<div class="card">

    <div class="card-header bg-soft-info">
        <h3 class="text-gray">Domicilio</h3>
    </div>

    <div class="card-body">

        <div class="form-group row mb-3">
            <div class="col-sm-6">
                <?= $form->field($model_domicilio, 'calle', ['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->textInput() ?>
            </div>
            <div class="col-sm-6">
                <?= $form->field($model_domicilio, 'numero', ['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->textInput() ?>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-6">
                <?= $form->field($model_domicilio, 'entre_calle_1',['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->textInput() ?>
            </div>
            <div class="col-sm-6">
                <?= $form->field($model_domicilio, 'entre_calle_2',['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->textInput() ?>
            </div>
        </div>

        <div class="form-group row mb-3">
            <div class="col-sm-4">
                <?= $form->field($model_domicilio, 'manzana', ['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->textInput() ?>
            </div>
            <div class="col-sm-4">
                <?= $form->field($model_domicilio, 'lote', ['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->textInput() ?>
            </div>
            <div class="col-sm-4">
                <?= $form->field($model_domicilio, 'sector',['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->textInput() ?>
            </div>
        </div>
        <div class="form-group row mb-3">
            <div class="col-sm-4">
                <?= $form->field($model_domicilio, 'grupo', ['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->textInput() ?>
            </div>
            <div class="col-sm-4">
                <?= $form->field($model_domicilio, 'torre', ['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->textInput() ?>
            </div>
            <div class="col-sm-4">
                <?= $form->field($model_domicilio, 'depto', ['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->textInput() ?>
            </div>
        </div>
        <div class="form-group row mb-3">
            <?php
            //****************************************************************************
            // Select dependiente de PROVINCIA


            ?>
            <div class="col-sm-6">
                <?php
                $provincia = ArrayHelper::map(Provincia::find()->asArray()->all(), 'id_provincia', 'nombre');

                echo $form->field($model_provincia, 'id_provincia',['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->widget(Select2::classname(), [
                    'data' => $provincia,
                    'theme'=>'classic',
                    'options' => ['placeholder' => 'Seleccione una provincia'],
                    'pluginOptions' => [
                        'allowClear' => true,
                        'width' => '100%'
                    ],

                ]);

                /*echo $form->field($model_provincia, 'id_provincia')->dropDownList($provincia, [ 'id' => 'id_provincia',
            'prompt' => 'Seleccione Provincia',
        ])->label('Provincia');*/
                ?>
            </div>
            <?php
            //******************************************************************
            // Select dependiente de DEPARTAMENTOS

            ?>
            <div class="col-sm-6">
                <?php
                echo $form->field($model_departamento, 'id_departamento', ['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->widget(DepDrop::classname(), [
                    'data' => [$model_departamento->id_departamento => $model_departamento->nombre],
                    'options' => ['id' => 'id_departamento'],
                    'type' => DepDrop::TYPE_SELECT2,
                    'select2Options'=>['theme'=>'classic','pluginOptions'=>['width' => '100%']],
                    'pluginOptions' => [
                        'depends' => ['provincia-id_provincia'],
                        'placeholder' => 'Seleccione Departamento',
                        'url' => Url::to(['/personas/subcat']),

                    ]
                ]);
                ?>
            </div>
        </div>
        <div class="form-group row mb-3">
            <div class="col-sm-6">
                <?php
                echo $form->field($model_domicilio, 'id_localidad', ['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->widget(DepDrop::classname(), [
                    'data' => [$model_localidad->id_localidad => $model_localidad->nombre],
                    'type' => DepDrop::TYPE_SELECT2,
                    'select2Options'=>['theme'=>'classic', 'pluginOptions'=>['width' => '100%']],
                    'pluginOptions' => [
                        'depends' => ['id_departamento'],
                        'placeholder' => 'Seleccione Localidad',
                        'url' => Url::to(['/personas/loc'])
                    ]
                ]);
                ?>
            </div>

            <div class="col-sm-6">
                <?php
                $data = [0 => 'Seleccione un barrio'];
                if (isset($model_domicilio->barrio) && is_object($model_domicilio->modelBarrio)) {
                    $data = [$model_domicilio->barrio => $model_domicilio->modelBarrio->nombre];
                } elseif (isset($model_localidad->id_localidad)) {
                    $localidad = $model_localidad->id_localidad;
                    $data = ArrayHelper::map(Barrios::find()->where(['id_localidad' => $localidad])->asArray()->all(), 'id_barrio', 'nombre');
                    $data[0] = "Seleccione un Barrio";
                }
                echo $form->field($model_domicilio, 'barrio', ['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->widget(DepDrop::classname(), [
                    'type' => DepDrop::TYPE_SELECT2,
                    'select2Options'=>['theme'=>'classic', 'pluginOptions'=>['width' => '100%']],
                    'pluginOptions' => [
                        'depends' => ['domicilio-id_localidad'],
                        'placeholder' => 'Seleccione un Barrio',
                        'url' => Url::to(['/personas/barrio'])
                    ],
                    'data' => $data
                ]);
                ?>
            </div>
        </div>
        <!--******************************************************************-->
        <!--  Select dependiente de LOCALIDADES-->
        <div class="form-group row mb-3">
            <div class="col-sm-6">
                <?=
                $form->field($model_domicilio, 'urbano_rural', ['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']])->inline()->radioList(['U' => 'Urbano', 'R' => 'Rural',])
                ?>
            </div>
        </div>
    </div>

</div>

<div class="row justify-content-center mt-5">
    <div class="form-group col-6 col-md-6">
        <div class="d-grid gap-2 col-6 mx-auto">
            <?= Html::a('<i class="bi bi-x-circle"></i> Cancelar', ['personas/buscar-persona'], ['class' => 'btn btn-warning rounded-pill']) ?>
        </div>
    </div>
    <div class="form-group col-6 col-md-6">
        <div class="d-grid gap-2 col-6 mx-auto">
            <?= Html::submitButton('<i class="bi bi-save"></i> Guardar', ['class' => 'btn btn-success rounded-pill']) ?>
        </div>
    </div>

</div>

<div style="display: none;">
    <?= $form->field($model, 'id_tipodoc')->hiddenInput(['value' => $model->id_tipodoc])->label(FALSE) ?>
    <?= $form->field($model, 'id_persona')->hiddenInput(['value' => $model->id_persona])->label(FALSE) ?>
    <?= $form->field($model, 'documento')->hiddenInput(['value' => $model->documento])->label(FALSE) ?>
    <?= $form->field($model, 'fecha_nacimiento')->hiddenInput(['value' => $model->fecha_nacimiento])->label(FALSE) ?>
    <?= $form->field($model, 'sexo_biologico')->hiddenInput(['value' => $model->sexo_biologico])->label(FALSE) ?>
    <?= $form->field($model, 'nombre')->hiddenInput(['value' => $model->nombre])->label(FALSE) ?>
    <?= $form->field($model, 'otro_nombre')->hiddenInput(['value' => $model->otro_nombre])->label(FALSE) ?>
    <?= $form->field($model, 'apellido')->hiddenInput(['value' => $model->apellido])->label(FALSE) ?>
    <?= $form->field($model, 'acredita_identidad')->hiddenInput(['value' => isset($_POST['Persona']['acredita_identidad']) ? $_POST['Persona']['acredita_identidad'] : 0])->label(FALSE) ?>
    <?= $form->field($model_domicilio, 'id_domicilio')->hiddenInput(['value' => $model_domicilio->id_domicilio])->label(FALSE) ?>
</div>
<?php ActiveForm::end(); ?>
<?php
$script = <<<JS
$(".dynamicform_wrapper, .dynamicform_wrapper_mails").on("beforeInsert", function(e, item) {
    console.log("beforeInsert");
    });

$(".dynamicform_wrapper, .dynamicform_wrapper_mails").on("afterInsert", function(e, item) {
    console.log("afterInsert");
});

$(".dynamicform_wrapper, .dynamicform_wrapper_mails").on("beforeDelete", function(e, item) {
    if (! confirm("¿Realmente desea eliminar este item?")) {
        return false;
    }
    return true;
});

$(".dynamicform_wrapper, .dynamicform_wrapper_mails").on("afterDelete", function(e) {
    console.log("El item ha sido eliminado!");
});

$(".dynamicform_wrapper, .dynamicform_wrapper_mails").on("limitReached", function(e, item) {
    alert("No es posible agregar más elementos");
});
JS;
$this->registerJs($script);
?>