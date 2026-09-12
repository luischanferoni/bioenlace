<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use kartik\switchinput\SwitchInput;
use yii\widgets\MaskedInput;
use yii\helpers\Url;
use frontend\assets\PersonasBuscarAsset;
/* @var $this yii\web\View */
/* @var $model common\models\Person\PersonaBusqueda */
/* @var $form yii\widgets\ActiveForm */
/* @var $lectorDefault int|null */

$lectorDefault = isset($lectorDefault) ? (int) $lectorDefault : 1;
PersonasBuscarAsset::register($this);

?>

<div class="iq-loader-box" id="cover-spin">
  <div class="iq-loader-1"></div>
</div>

<?php $form = ActiveForm::begin([
  'action' => ['lista-candidatos'],
  'method' => 'post',
  'id' => 'buscar',
  'options' => [
    'data-url-renaper' => Url::to(['personas/buscar-renaper']),
  ],
]); ?>
<?= $form->errorSummary($model); ?>
<input type="hidden" name="id" id="id" value="">
<input type="hidden" name="tipo" id="tipo" value="">
<input type="hidden" name="score" id="score" value="">

<div class="card">
  <div class="card-body">
    <div class="row align-items-center">
      <div class="col-6 col-md-2">
        <label class="control-label" for="lector_qr" class="mt-2">Lector QR</label>
      </div>

      <div class="col-6 col-md-10">
        <?php
        echo SwitchInput::widget([
          'name' => 'lector_qr',
          'pluginOptions' => [
            'onText' => 'Si',
            'offText' => 'No',
          ],
          'containerOptions' => [
            'class' => false,
          ],
          'options' => ['id' => 'lector_qr'],
          'value' => $lectorDefault,
        ])
        ?>
        <input type="hidden" name="lector" id="lector" value="<?= (int) $lectorDefault ?>">
      </div>
    </div>
    <hr>

    <div class="row">
      <div class="col-6 col-md-2">
        <label for="acredita_identidad" class="mt-2">Persona Presenta Documento</label>
      </div>
      <div class="col-6 col-md-2">

        <?php
        if (is_null($model->acredita_identidad)) {
          $model->acredita_identidad = true;
        }


        echo $form->field($model, 'acredita_identidad')->widget(
          SwitchInput::classname(),
          [
            'pluginOptions' => [
              'onText' => 'Si',
              'offText' => 'No',
            ],
            'containerOptions' => [
              'class' => false,
            ],
            'options' => ['id' => 'acredita_identidad'],
          ]
        )->label(false);

        ?>

      </div>

      <div class="col-md-3">
        <?php
        $lista_motivos = [
          1 => 'Persona Indocumentada',
          2 => 'Extravió el DNI',
          3 => 'Persona no porta el DNI'
        ];
        ?>
        <?= $form->field($model, 'motivo_acredita')->dropDownList($lista_motivos, ['prompt' => ' -- Elija una opcion --'])->label(false); ?>

      </div>
      <div class="col-md-3">
        <input class="form-control" type="text" placeholder="Observaciones" id="observaciones">
      </div>
    </div>

  </div>
</div>

<div class="card">

  <div class="card-body">

    <div class="row">

      <div class="form-group col-md-2">
        <label for="sexo">Sexo Biológico</label>
      </div>

      <div class="form-group col-md-10 mb-2">
        <?php
        echo $form->field($model, 'sexo_biologico')->inline()->radioList([1 => 'Femenino (F)', 2 => 'Masculino (M)'])->label(false);
        ?>
      </div>

    </div>

    <div class="form-group row">

      <div class="form-group col-md-2">
        <label for="tipo_documento" class="mt-2">Documento</label>
      </div>
      <div class="form-group col-md-5">
        <input type="hidden" id="hidden_id_tipodoc" name="Persona[id_tipodoc]" value="<?= (isset($_POST['Persona']['id_tipodoc'])) ? $_POST['Persona']['id_tipodoc'] : '' ?>">
        <?= $form->field($model, 'id_tipodoc')->dropDownList(common\models\Person\Tipo_documento::getListaTiposDocumento('BUSQUEDA'), ['prompt' => ' -- Elija una opcion --'])->label(false); ?>

      </div>
      <div class="form-group col-md-5">
        <?= $form->field($model, 'documento')->textInput(['placeholder' => 'Número de documento'])->label(false) ?>

      </div>
    </div>

    <div class="row">

      <div class="form-group col-md-2">
        <label for="nombres" class="mt-2">Nombres</label>
      </div>

      <div class="form-group col-md-5">
        <?= $form->field($model, 'nombre')->textInput(['placeholder' => 'Nombre'])->label(false) ?>
      </div>
      <div class="form-group col-md-5">
        <?= $form->field($model, 'otro_nombre')->textInput(['placeholder' => 'Otros Nombres'])->label(false) ?>
      </div>
    </div>

    <div class="row">
      <div class="form-group col-md-2">
        <label for="apellido">Apellidos</label>
      </div>
      <div class="form-group col-md-5">
        <?= $form->field($model, 'apellido')->textInput(['placeholder' => 'Apellido'])->label(false) ?>
      </div>
      <div class="form-group col-md-5">
        <?= $form->field($model, 'otro_apellido')->textInput(['placeholder' => 'Otro apellido'])->label(false) ?>
      </div>
    </div>

    <div class="row">
      <div class="form-group col-md-2">
        &nbsp;
      </div>
      <div class="form-group col-md-5">
        <?= $form->field($model, 'apellido_materno')->textInput(['placeholder' => 'Apellido materno'])->label(false) ?>
      </div>
      <div class="form-group col-md-5">
        <?= $form->field($model, 'apellido_paterno')->textInput(['placeholder' => 'Apellido paterno'])->label(false) ?>
      </div>
    </div>

    <div class="row">
      <div class="form-group col-md-2">
        <label for="sexo">Género Legal</label>
      </div>
      <div class="form-group col-md-10">
        <?php
        echo $form->field($model, 'genero')->inline()->radioList([1 => 'Femenino (F)', 2 => 'Masculino (M)', 3 => 'Otro', 4 => 'Indefinido (-)'])->label(false);
        ?>
      </div>
    </div>

    <div class="row">
      <div class="form-group col-md-2">
        <label for="fecha_nacimiento"  class="mt-2">Fecha de Nacimiento</label>
      </div>
      <div class="form-group col-md-5">
        <?= $form->field($model, 'fecha_nacimiento')->textInput(['type' => 'date'])->label(false) ?>
      </div>
      <div class="form-group col-md-5">
        <?= $form->field($model, 'fecha_nacimiento_1')->textInput(['type' => 'date'])->label(false) ?>
        <?php //echo $form->field($model, 'fecha_nacimiento_1')->widget(MaskedInput::className(), ['mask' => '99/99/9999','type'=>'date','clientOptions' => ['alias' => '**/**/****']]) 
        ?>
      </div>
    </div>

  </div>




  <div class="row justify-content-center">
    <div class="form-group col-6 col-md-6">
      <div class="d-grid gap-2 col-6 mx-auto">
        <?= Html::resetButton('<i class="bi bi-trash3"></i> Limpiar', ['class' => 'btn btn-warning rounded-pill']) ?>
      </div>
    </div>
    <div class="form-group col-6 col-md-6">
      <div class="d-grid gap-2 col-6 mx-auto">
        <?= Html::submitButton('<span class="glyphicon glyphicon-search"></span> Buscar', ['class' => 'btn btn-primary rounded-pill']) ?>
      </div>
    </div>

  </div>
</div>

<?php ActiveForm::end(); ?>
