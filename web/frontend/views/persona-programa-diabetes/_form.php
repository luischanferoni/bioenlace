<?php

use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

use common\models\Efector;
use common\models\PersonaProgramaDiabetes;
use kartik\select2\Select2;
use kartik\depdrop\DepDrop;

/* @var $this yii\web\View */
/* @var $model common\models\PersonaProgramaDiabetes */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="persona-programa-diabetes-form">

    <?php $form = ActiveForm::begin(); ?>

    <?php

    $tipoDiabetes = PersonaProgramaDiabetes::TIPO_DIABETES;
    echo $form->field($model, 'tipo_diabetes')->radioList($tipoDiabetes); ?>

    <?= $form->field($model, 'incluir_salud')->radioList(['SI' => 'SI', 'NO' => 'NO',]) ?>


    <br>
    <fieldset>
        <legend>Historia Clinica</legend>

        <div class="row">
            <div class="col-md-5">
                <?php /* peso == 162879003 */ ?>
                <?= Html::label('Peso', '162879003p') ?>
                <?= Html::input('text', '162879003p', '', ['placeHolder' => 'En kg.']) ?>
            </div>

            <div class="col-md-5">
                <?php /* talla == 162879003 */ ?>
                <?= Html::label('Talla', '162879003t') ?>
                <?= Html::input('text', '162879003t', '', ['placeHolder' => 'En cm.']) ?>
            </div>
        </div>

    </fieldset>
    <br>

    <fieldset>
        <legend>Datos de Laboratorio</legend>
        <div class="row">
            <div class="col-md-4">
                <?= $form->field($model, 'fecha_laboratorio')->widget(\yii\jui\DatePicker::className()) ?>
            </div>

            <div class="col-md-4">
                <?= $form->field($model, 'hba1c')->textInput(['placeholder' => 'en % o en G/dl']) ?>
            </div>

            <div class="col-md-4">
                <?= $form->field($model, 'glucemia')->textInput(['placeholder' => 'en mg/dg']) ?>
            </div>
        </div>
    </fieldset>
    <br>

    <fieldset>
        <legend>Complicaciones</legend>
        <div class="row">
            <div class="col-md-3">
                <?= Html::label('Retinopatia', '4855003') ?>
                <?= Html::input('checkbox', '4855003', '', ['id' => '4855003']) ?>
            </div>
            <br>
            <div class="col-md-3">
                <?= Html::label('Nefropatia', '127013003') ?>
                <?= Html::input('checkbox', '127013003', '', ['id' => '127013003']) ?>
            </div>
            <br>
            <div class="col-md-3">
                <?= Html::label('Neuropatia', '230572002') ?>
                <?= Html::input('checkbox', '230572002', '', ['id' => '230572002']) ?>
            </div>
            <br>
            <div class="col-md-3">
                <?= Html::label('Pie diabetico', '280137006') ?>
                <?= Html::input('checkbox', '280137006', '', ['id' => '280137006']) ?>
            </div>
            <br>
            <div class="col-md-3">
                <?= Html::label('Hipertensión arterial', '38341003') ?>
                <?= Html::input('checkbox', '38341003', '', ['id' => '38341003']) ?>
            </div>
            <br>
            <div class="col-md-3">
                <?= Html::label('Angor y/o Infarto', '194828000') ?>
                <?= Html::input('checkbox', '194828000', '', ['id' => '194828000']) ?>
            </div>
        </div>
    </fieldset>
    <br>

    <fieldset>
        <legend>Medicacion y Dosis Diaria</legend>

        <?= $form->field($model, 'ins_lenta_nph')->textInput(['placeholder' => 'Unidades/Dia']) ?>

        <?= $form->field($model, 'ins_lenta_lantus')->textInput(['placeholder' => 'Unidades/Dia']) ?>

        <?= $form->field($model, 'ins_rapida_novorapid')->textInput(['placeholder' => 'Unidades/Dia']) ?>

        <?= $form->field($model, 'metformina_500')->textInput(['placeholder' => 'Comp/Dia']) ?>

        <?= $form->field($model, 'metformina_850')->textInput(['placeholder' => 'Comp/Dia']) ?>

        <?= $form->field($model, 'glibenclamida')->textInput(['placeholder' => 'Comp/Dia']) ?>

        <?= $form->field($model, 'tiras')->radioList([50 => '50', 100 => '100',]) ?>

        <?= $form->field($model, 'monitor')->radioList(['SI' => 'SI', 'NO' => 'NO',]) ?>

        <?= $form->field($model, 'lanceta')->textInput() ?>

    </fieldset>
    <br>

    <fieldset>
        <legend>Datos de la Persona que Retira la Medicacion</legend>

        <?= $form->field($model, 'nombre_persona_autorizada')->textInput() ?>
        <?= $form->field($model, 'apellido_persona_autorizada')->textInput() ?>
        <?= $form->field($model, 'dni_persona_autorizada')->textInput() ?>
        <?= $form->field($model, 'parentesco_persona_autorizada')->dropDownList(PersonaProgramaDiabetes::PARENTESCO, ['prompt' => 'Seleccione uno']); ?>


    </fieldset>
    <br>

    <fieldset>
        <legend>Medico Solicitante</legend>
        <?php

        $efectores = Efector::getTodosLosEfectores();
        $efectores =  ArrayHelper::map($efectores, 'id_efector', 'nombre');
        ?>
        <div class="row">
            <div class="col-md-5">
                <?= '<label class="control-label">Efector</label>' ?>
                <?= $form->field($model, 'id_efector')->widget(Select2::classname(), [
                    'data' => $efectores,
                    'theme' => 'default',
                    'options' => ['placeholder' => 'Seleccione un efector...'],
                    'pluginOptions' => [
                        'allowClear' => true
                    ],
                ])->label(false); ?>
            </div>

            <div class="col-md-5">
                <?= '<label class="control-label">Profesional</label>' ?>
                <?= $form->field($model, 'id_rrhh_efector')->widget(DepDrop::className(), [
                    'type' => DepDrop::TYPE_SELECT2,
                    'select2Options' => ['theme' => 'default'],
                    'pluginOptions' => [
                        'depends' => ['personaprogramadiabetes-id_efector'],
                        'placeholder' => 'Seleccione un profesional',
                        'url' => Url::to(['/rrhh_efectores/profesionales-por-efector'])
                    ]

                ])->label(false); ?>
            </div>
        </div>
    </fieldset>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>