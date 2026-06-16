<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use kartik\switchinput\SwitchInput;
use yii\widgets\MaskedInput;
use yii\helpers\Url;
/* @var $this yii\web\View */
/* @var $model common\models\busquedas\PersonaBusqueda */
/* @var $form yii\widgets\ActiveForm */


?>

<div class="iq-loader-box" id="cover-spin">
  <div class="iq-loader-1"></div>
</div>

<?php $form = ActiveForm::begin([
  'action' => ['lista-candidatos'],
  'method' => 'post',
  'id' => 'buscar'
]); ?>
<?= $form->errorSummary($model); ?>
<input type="hidden" name="id" id="id" value="">
<input type="hidden" name="tipo" id="tipo" value="">
<input type="hidden" name="score" id="score" value="">

<div class="card">
  <div class="card-body">
    <div class="row">
      <div class="col-md-2 d-lg-block d-none">
        <label class="control-label" for="lector_qr" class="mt-2">Lector QR</label>
      </div>

      <div class="col-md-10 d-lg-block d-none">
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
          'value' => isset($_POST['lector']) ? $_POST['lector'] : 1,
        ])
        ?>
        <input type="hidden" name="lector" id="lector" value="<?= isset($_POST['lector']) ? $_POST['lector'] : 1 ?>">
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
        <?= $form->field($model, 'id_tipodoc')->dropDownList(common\models\Tipo_documento::getListaTiposDocumento('BUSQUEDA'), ['prompt' => ' -- Elija una opcion --'])->label(false); ?>

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

<?php
$url_buscar_renaper = Url::to(["personas/buscar-renaper"]);
$script = <<<JS
  $('input[type=text]').attr('readonly', true);
  $('input[type=date]').attr('readonly', true);
  $('#persona-id_tipodoc').prop('disabled', true);
  $('#persona-id_tipodoc').val(1);
  $('#hidden_id_tipodoc').val(1);
  $('input[type=radio]').prop('disabled', true);
  $('#persona-motivo_acredita').prop('disabled', true);    
    habilitarQrScan();
function habilitarCampos(){
  $('input[type=text]').attr('readonly', false);
  $('input[type=date]').attr('readonly', false);
  $('#persona-id_tipodoc').prop('disabled', false);
  $('input[type=radio]').prop('disabled', false);
}
function deshabilitarCampos(){
  $('input[type=text]').attr('readonly', true);
  $('input[type=date]').attr('readonly', true);
  $('#persona-id_tipodoc').prop('disabled', true);
  $('input[type=radio]').prop('disabled', true);
}
  if($('#lector').val() == 1){
      $('#persona-id_tipodoc').val(1);
      $('#hidden_id_tipodoc').val(1);       
      $("input[type=hidden][name='Persona[acredita_identidad]']").val($('#lector').val());
      $("#acredita_identidad").bootstrapSwitch('state',true);
      $("#acredita_identidad").bootstrapSwitch('disabled',true);
      deshabilitarCampos();
    } else if($('#lector').val() == 0){
      if($('#persona-motivo_acredita').val() != ""){
        $("#acredita_identidad").bootstrapSwitch('state',false);
      } else {
        $("#acredita_identidad").bootstrapSwitch('state',true);
      }
      $("#acredita_identidad").bootstrapSwitch('disabled',false);
      habilitarCampos();
    }

 $('#lector_qr').on('switchChange.bootstrapSwitch', function(e, s){
  if(s == true){
    habilitarQrScan();    
    $('#persona-id_tipodoc').val(1);
    $('#hidden_id_tipodoc').val(1);  
    $('#lector').val(1);    
    $("input[type=hidden][name='Persona[acredita_identidad]']").val($('#lector').val());    
    $("#acredita_identidad").bootstrapSwitch('state',true);
    $("#acredita_identidad").bootstrapSwitch('disabled',true);   
    deshabilitarCampos();
  }
  if(s == false){
     onScan.detachFrom(document);
    $('#persona-id_tipodoc').val();
    habilitarCampos();
    $("#acredita_identidad").bootstrapSwitch('disabled',false);
    $('#lector').val(0);
  }
 });

 $('#acredita_identidad').on('switchChange.bootstrapSwitch', function(e, s) {
  if(s == true){
    $('#persona-motivo_acredita').prop('disabled', true);
    $('#observaciones').prop('disabled', true);
  } 
  if(s == false){
    $('#persona-motivo_acredita').prop('disabled', false);
    $('#observaciones').prop('disabled', false);      
  }
});


$("#persona-id_tipodoc").change(function(){
    $('#hidden_id_tipodoc').val($("#persona-id_tipodoc").val());
  });

$("input[type=radio][name='Persona[sexo_biologico]']").change(function(){
    $("input[type=hidden][name='Persona[sexo_biologico]']").val($("input[type=radio][name='Persona[sexo_biologico]']:checked").val());
  });

$("input[type=radio][name='Persona[genero]']").change(function(){
    $("input[type=hidden][name='Persona[genero]']").val($("input[type=radio][name='Persona[genero]']:checked").val());
  });

// Enable scan events for the entire document
function habilitarQrScan() {
  onScan.attachTo(document, {
    //suffixKeyCodes: [9, 13], // enter-key or tab-key expected at the end of a scan
    reactToKeyDown: true,
    reactToPaste: true, // Compatibility to built-in scanners in paste-mode (as opposed to keyboard-mode)
    timeBeforeScanTest: 200,
    avgTimeByChar: 30,
    onScan: function(sCode, iQty) { // Alternative to document.addEventListener('scan')      
  
      var expresion_reg_es = new RegExp(/^[0-9]{11}@[A-ZÁÉÍÓÚÑ ]+@[A-ZÁÉÍÓÚÑ ]+@[MF]@([MF]|[0-9])?[0-9]{7}@[A-Z]{1}@[0-9]{2}\/[0-9]{2}\/[0-9]{4}@[0-9]{2}\/[0-9]{2}\/[0-9]{4}(@[0-9]{3})?$/);
      var expresion_reg_en = new RegExp(/^[0-9]{11}"[A-ZÁÉÍÓÚÑ ]+"[A-ZÁÉÍÓÚÑ ]+"[MF]"([MF]|[0-9])?[0-9]{7}"[A-Z]{1}"[0-9]{2}[0-9]{2}[0-9]{4}"[0-9]{2}[0-9]{2}[0-9]{4}("[0-9]{3})?$/);
      var expresion_reg_dniLibreta = new RegExp(/^"[0-9]?[0-9]{7}"[A-Z]{1}"[0-9]{1}"[A-ZÁÉÍÓÚÑ ]+"[A-ZÁÉÍÓÚÑ ]+"[A-ZÁÉÍÓÚÑ ]+"[0-9]{2}[0-9]{2}[0-9]{4}"[MF]"[0-9]{2}[0-9]{2}[0-9]{4}/);
      var estado = expresion_reg_es.test(sCode);
      var estado2 = expresion_reg_en.test(sCode);
      var estado3 = expresion_reg_dniLibreta.test(sCode);


      if(estado || estado2 || estado3){
        var datos = sCode.includes('"')? sCode.split('"') : sCode.split('@');
        

        if (datos[8]=='M' || datos[8]=="F"){
          switch(datos[8]){
            case 'M':
              $("input[type=radio][name='Persona[sexo_biologico]'][value='2']").prop('checked',true);
              $("input[type=hidden][name='Persona[sexo_biologico]']").val(2);
              break;
            case 'F': 
              $("input[type=radio][name='Persona[sexo_biologico]'][value='1']").prop('checked',true);
              $("input[type=hidden][name='Persona[sexo_biologico]']").val(1); 
              break;
            default:
              break;  
          }
          $('#persona-documento').val(datos[1]);
        }
        else {
          switch(datos[3]){
            case 'M':
              $("input[type=radio][name='Persona[sexo_biologico]'][value='2']").prop('checked',true);
              $("input[type=hidden][name='Persona[sexo_biologico]']").val(2);
              break;
            case 'F':
              $("input[type=radio][name='Persona[sexo_biologico]'][value='1']").prop('checked',true);
              $("input[type=hidden][name='Persona[sexo_biologico]']").val(1);
              break;
              default:
                break;
          }
          $('#persona-documento').val(datos[4]);
        }
/*
        if(datos[8]=='M'){
          $("input[type=radio][name='Persona[sexo_biologico]'][value='2']").prop('checked',true);
          $("input[type=hidden][name='Persona[sexo_biologico]']").val(2);
          $('#persona-documento').val(datos[1]);
        }
        if(datos[8]=='F') {
          $("input[type=radio][name='Persona[sexo_biologico]'][value='1']").prop('checked',true);
          $("input[type=hidden][name='Persona[sexo_biologico]']").val(1);
          $('#persona-documento').val(datos[1]);
        } 

        if(datos[3]=='M'){
          $("input[type=radio][name='Persona[sexo_biologico]'][value='2']").prop('checked',true);
          $("input[type=hidden][name='Persona[sexo_biologico]']").val(2);
          $('#persona-documento').val(datos[4]);
        }

        if(datos[3]=='F'){
          $("input[type=radio][name='Persona[sexo_biologico]'][value='1']").prop('checked',true);
          $("input[type=hidden][name='Persona[sexo_biologico]']").val(1);
          $('#persona-documento').val(datos[4]);
        }
        */
      
         $.ajax({
			type: "POST",
			url: '$url_buscar_renaper',
			data:{ dni:$("#persona-documento").val(),sexo:$("input[type=radio][name='Persona[sexo_biologico]']:checked").val()},
			dataType: "json",
			beforeSend: function(){
			// Show image container
			  $("#cover-spin").show();
			},
			success: function(data){
			  if(data.successful != false){
			  if (typeof data.data[0] !== 'undefined' && data.data[0] != "No se encuentra la persona.") {        
				$("#persona-documento").val(data.data[0].numeroDocumento);
				$("#persona-apellido").val(data.data[0].apellido[0]);
				$("#persona-otro_apellido").val(data.data[0].apellido[1]);
				$("#persona-nombre").val(data.data[0].nombres[0]);
				$("input[type=radio][name='Persona[genero]'][value="+$("input[type=radio][name='Persona[sexo_biologico]']:checked").val()+"]").prop('checked',true);
				 $("input[type=hidden][name='Persona[genero]']").val($("input[type=radio][name='Persona[sexo_biologico]']:checked").val());
				$("#persona-otro_nombre").val(data.data[0].nombres[1]);
				$("#persona-fecha_nacimiento").val(data.data[0].fechaNacimiento);
				$("#persona-fecha_nacimiento_1").val(data.data[0].fechaNacimiento);
				$("#foto").attr('src',data.data[0].foto);
				$("#foto_hidden").val(data.data[0].foto);
			  } else {
				$("#cover-spin").hide();
				alert("Por favor, ingrese los campos obligatorios para poder buscar al paciente.");
			  }
			  } else {
				$("#cover-spin").hide(); 
			  }
			},
			error:function(data){
			// Hide image container
			  $("#cover-spin").hide();        
			},
			complete:function(data){
			// Hide image container
			  $("#cover-spin").hide();        
			}
		  });
		  return false; // Evitar ejecutar el submit del formulario.    
		} else {
			alert("Debe ingresar los datos manualmente. No se ha podido leer correctamente el DNI");
		}
	}
  });
  // Register event listener
  document.addEventListener('scan', function(sScancode, iQuatity) {});
}


JS;
$this->registerJs($script);
?>