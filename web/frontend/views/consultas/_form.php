<?php

use yii\helpers\Url;
use yii\bootstrap5\Modal;
use yii\web\JsExpression;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper; 

use kartik\date\DatePicker;
use wbraganca\dynamicform\DynamicFormWidget;
use kartik\select2\Select2;

use common\models\Cie10;
use frontend\assets\FormWizardAsset;

FormWizardAsset::register($this);
?>

    <?php $form = ActiveForm::begin([
        'id' => 'dynamic-form',
      //  'enableClientValidation' => false,
        'options' => ['class' => 'form-wizard', 'enctype' => 'multipart/form-data']
        ]);
    ?>

    <ul id="top-tab-list" class="p-0 row list-inline mb-0">
        <li class="mb-2 col text-start active" id="paso1">
            <a href="#" class="wizard_tab_link">
                <div class="row">
                    <div class="col-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-1-square" viewBox="0 0 16 16">
                            <path d="M9.283 4.002V12H7.971V5.338h-.065L6.072 6.656V5.385l1.899-1.383h1.312Z"/>
                            <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2Zm15 0a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V2Z"/>
                        </svg>
                    </div>
                    <div class="col-10">
                        <span class="dark-wizard">Síntomas</span>
                    </div>
                </div>
            </a>
        </li>
        <li id="paso2" class="mb-2 col ps-0 pe-0 text-start">
            <a href="#" class="wizard_tab_link">
                <div class="row">
                    <div class="col-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-2-square" viewBox="0 0 16 16">
                            <path d="M6.646 6.24v.07H5.375v-.064c0-1.213.879-2.402 2.637-2.402 1.582 0 2.613.949 2.613 2.215 0 1.002-.6 1.667-1.287 2.43l-.096.107-1.974 2.22v.077h3.498V12H5.422v-.832l2.97-3.293c.434-.475.903-1.008.903-1.705 0-.744-.557-1.236-1.313-1.236-.843 0-1.336.615-1.336 1.306Z"/>
                            <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2Zm15 0a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V2Z"/>
                        </svg>
                    </div>
                    <div class="col-10">
                        <span class="dark-wizard">Prácticas</span>
                    </div>
                </div>
            </a>
        </li>
        <li id="paso3" class="mb-2 col pe-0 text-start">
            <a href="#" class="wizard_tab_link">
                <div class="row">
                    <div class="col-1">                
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-3-square" viewBox="0 0 16 16">
                            <path d="M7.918 8.414h-.879V7.342h.838c.78 0 1.348-.522 1.342-1.237 0-.709-.563-1.195-1.348-1.195-.79 0-1.312.498-1.348 1.055H5.275c.036-1.137.95-2.115 2.625-2.121 1.594-.012 2.608.885 2.637 2.062.023 1.137-.885 1.776-1.482 1.875v.07c.703.07 1.71.64 1.734 1.917.024 1.459-1.277 2.396-2.93 2.396-1.705 0-2.707-.967-2.754-2.144H6.33c.059.597.68 1.06 1.541 1.066.973.006 1.6-.563 1.588-1.354-.006-.779-.621-1.318-1.541-1.318Z"/>
                            <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2Zm15 0a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V2Z"/>
                        </svg>
                    </div>
                    <div class="col-10">
                        <span class="dark-wizard">Diagnósticos, Medicamentos</span>
                    </div>
                </div>
            </a>
        </li>
        <li id="paso4" class="mb-2 col text-start">
            <a href="#" class="wizard_tab_link">
                <div class="row">
                    <div class="col-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-4-square" viewBox="0 0 16 16">
                            <path d="M7.519 5.057c.22-.352.439-.703.657-1.055h1.933v5.332h1.008v1.107H10.11V12H8.85v-1.559H4.978V9.322c.77-1.427 1.656-2.847 2.542-4.265ZM6.225 9.281v.053H8.85V5.063h-.065c-.867 1.33-1.787 2.806-2.56 4.218Z"/>
                            <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2Zm15 0a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V2Z"/>
                        </svg>
                    </div>
                    <div class="col-10">
                        <span class="dark-wizard">Antecedentes Personales</span>
                    </div>
                </div>
            </a>
        </li>
        <?php /*?>
        <li id="ultimopaso" class="mb-2 col-lg-3 col-md-6 text-start">
            <a href="javascript:void(0);">
                <div class="iq-icon me-3">
                    <svg xmlns="http://www.w3.org/2000/svg" height="20" width="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <span class="dark-wizard">Guardar Consulta</span>
            </a>
        </li>
        <?php */?>
    </ul>

    <?php /*  ?>
    <?= $form->errorSummary($model); ?>
    <?php echo "model_consulta_sintomas"; ?>
    <?= $form->errorSummary($model_consulta_sintomas); ?>
    <?php echo "modelosConsultaDiagnostico"; ?>
    <?= $form->errorSummary($modelosConsultaDiagnostico); ?>
    <?php echo "model_consulta_practicas"; ?>
    <?= $form->errorSummary($model_consulta_practicas); ?>
    <?php echo "model_medicamentos_consulta"; ?>
    <?= $form->errorSummary($model_medicamentos_consulta); 
     echo "modelConsultaPracticasSolicitadas"; ?>
    <?= $form->errorSummary($modelConsultaPracticasSolicitadas); ?>
    <?php echo "model_personas_antecedente"; ?>
    <?= $form->errorSummary($model_personas_antecedente); ?>
    <?php echo "model_personas_antecedente_2"; ?>
    <?= $form->errorSummary($model_personas_antecedente_2); */?>    
    
    <?php
        // necesitamos saber si el post viene desde el segundo paso
        echo Html::hiddenInput('segundo_paso', true);
        echo Html::hiddenInput('Consulta[id_persona]', $id_persona);// antes $model->id_persona
        echo Html::hiddenInput('Consulta[parent_class]', $model->parent_class);
        echo Html::hiddenInput('Consulta[parent_id]', $model->parent_id);

        foreach($modelMotivosConsulta->select2_codigo as $value){
                $select2_codigoMultiple[$value] = $value;
        }
        echo Html::dropDownList('ConsultaMotivos[select2_codigo]', 
                $select2_codigoMultiple, 
                $select2_codigoMultiple, ['hidden' => 'hidden', 'multiple'=>'multiple']);
        echo Html::hiddenInput('terminos_motivos', $modelMotivosConsulta->terminos_motivos);
    ?>

    <!--SINTOMAS-->
    <fieldset class="formwizard_fieldset">
        <?= $this->render('_sintomas', 
                            [
                                'form' => $form,
                                'model' => $model,
                                'modelConsultaSintomas' => $modelConsultaSintomas,
                                'idConsulta' => $idConsulta,
                            ]); 
                            ?>
           

        <div class="row">        
            <?= $form->field($model, 'observacion')->textarea(['rows' => 4]) ?>        
        </div>                            
        <button type="button" name="next" class="btn btn-primary next action-button float-end" value="Siguiente">Siguiente</button>
    </fieldset>
    <!------ Fin Formulario Dinámico SINTOMAS --> 

    <fieldset class="formwizard_fieldset">
        <!--PRACTICAS PERSONAS-->
        <?= $this->render('_practicas', 
                            [
                                'form' => $form,
                                'model' => $model,
                                'modelConsultaPracticas' => $modelConsultaPracticas,
                                'idConsulta' => $idConsulta,
                            ]); 
                            ?>
        <!------ Fin Formulario Dinámico Practicas Personas -->


<?php        if($servicio=='ODONTOLOGIA')
                { ?>
        <!--ODONTOGRAMA-->    
        <div class="card mb-3 border border-2">
            <?= $this->render('_odontograma', 
                                [
                                    'form' => $form,
                                    'model' => $model,
                                    'modelOdontoConsultaPersona' => $modelOdontoConsultaPersona,
/*                                    'model_odonto_nomenclador' => $model_odonto_nomenclador,
                                    'model_odonto_nomenclador_por_pieza' => $model_odonto_nomenclador_por_pieza,
                                    'model_odonto_nomenclador_completa' => $model_odonto_nomenclador_completa,
                                    'model_odonto_nomenclador_caras' => $model_odonto_nomenclador_caras,*/
                                    'dataNomencladorConsulta' => $dataNomencladorConsulta,
                                    'dataNomencladorPorPieza' => $dataNomencladorPorPieza,
                                    'dataNomencladorTto' => $dataNomencladorTto,
                                    'dataNomencladorCaras' =>  $dataNomencladorCaras,
                                    'odontogramaPaciente' => $odontogramaPaciente,
                                    'odontograma_paciente_caras_pieza_dental' => $odontograma_paciente_caras_pieza_dental,
                                    'indice' => $indice,
                                    'grafico' => $grafico,
                                    'idConsulta' => $idConsulta,
                                    'historial' =>  $historial,
                                ]); 
                                ?>
        </div>
     <?php   } ?>
   
        <button type="button" id="next_odontograma" name="next" class="btn btn-primary next action-button float-end" value="Siguiente" >Siguiente</button>
        <button type="button" id="previous_odontograma"  name="previous" class="btn btn-dark previous action-button-previous float-end me-1" value="Anterior" >Anterior</button>        
        <!------ Fin Formulario Dinámico ODONTOGRAMA -->  
    </fieldset>

    
    <fieldset class="formwizard_fieldset">
        <!--DIAGNOSTICOS-->     
            <?= $this->render('_diagnosticos', 
                                [
                                    'form' => $form,
                                    'model' => $model,
                                    'modelosConsultaDiagnostico' => $modelosConsultaDiagnostico,
                                    'idConsulta' => $idConsulta,
                                ]); 
                                ?>
        <!------ Fin Formulario Dinámico DIAGNOSTICO -->   
    

        <!--MEDICAMENTOS-->
            <?= $this->render('_medicamentos', 
                                    [
                                        'form' => $form,
                                        'model' => $model,
                                        'model_medicamentos_consulta' => $model_medicamentos_consulta,
                                        'idConsulta' => $idConsulta,
                                    ]); 
                                    ?>
        <!------ Fin Formulario Dinámico Medicamentos -->

        <?php /*
        <!--SOLICITUDES-->
            <?= $this->render('_solicitudes', 
                                [
                                    'form' => $form,
                                    'model' => $model,
                                    'modelConsultaPracticasSolicitadas' => $modelConsultaPracticasSolicitadas
                                ]); 
                                ?>
        <!------ Fin Formulario SOLICITUDES -->
        <?php */ ?>
        <button type="button" name="next" class="btn btn-primary next action-button float-end" value="Siguiente">Siguiente</button>
        <button type="button" name="previous" class="btn btn-dark previous action-button-previous float-end me-1" value="Anterior">Anterior</button>         
    </fieldset>

    <fieldset class="formwizard_fieldset">
        <!-- ALERGIAS -->            
        <?= $this->render('_alergias', 
                                [
                                'form' => $form,
                                'model' => $model,
                                'model_alergias' => $model_alergias,
                                'idConsulta' => $idConsulta,
                                ]); 
                                ?>
        <!------ Fin Formulario Dinámico Personas Alergias  -->

        <!-- PERSONAS ANTECEDENTE-->
        <?= $this->render('_antecedentes', 
                                [
                                'form' => $form,
                                'model' => $model,
                                'model_personas_antecedente' => $model_personas_antecedente,
                                'idConsulta' => $idConsulta,
                                ]); 
                                ?>
        <!------ Fin Formulario Dinámico Personas Antecedentes  -->

        <!-- PERSONAS ANTECEDENTE Familiar-->                
        <?= $this->render('_antecedentes_familiares', 
                                [
                                'form' => $form,
                                'model' => $model,
                                'model_personas_antecedente_2' => $model_personas_antecedente_2,
                                'idConsulta' => $idConsulta,
                                ]); 
                                ?>
        <!------ Fin Formulario Dinámico Personas Antecedentes Familiar-->
        <div class="form-group">            
            <?= Html::submitButton($model->isNewRecord ? 'Crear consulta' : 'Actualizar consulta', 
                    [
                        'class' => ($model->isNewRecord ? 'btn btn-success' : 'btn btn-primary').' float-end',
                    ]) ?>
            <button type="button" name="previous" class="btn btn-dark previous action-button-previous float-end me-1" value="Previous">Anterior</button>            
        </div>        
    </fieldset>

    <?php ActiveForm::end(); ?>

<?php
    $this->registerJs('
        function initSelect2DropStyle(a,b,c){
            initS2Loading(a,b,c);
        }
        function initSelect2Loading(a,b){
            initS2Loading(a,b);
        }
        
        const tooltipTriggerList = document.querySelectorAll(\'[data-bs-toggle="tooltip"]\');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

        $("#dynamic-form").on("afterValidate", function(event, messages, errorAttributes) {
            let paso1 = false;
            let paso2 = false;
            let paso3 = false;
            let paso4 = false;
            if (errorAttributes.length > 0) {
                Array.from(errorAttributes, (error) => {
                    if (error.id.includes("sintomas")) {                        
                        paso1 = true;
                    }
                    if (error.id.includes("consultapracticas")) {                        
                        paso2 = true;
                    }
                   /* if (error.id.includes("consultapracticassolicitadas")) {                        
                        paso3 = true;
                    }*/
                    if (error.id.includes("diagnosticoconsulta") || error.id.includes("consultamedicamentos")) {
                        paso3 = true;
                    }
                    if (error.id.includes("alergias") || error.id.includes("personasantecedente") || error.id.includes("personasantecedentefamiliar")) {
                        paso4 = true;
                    }
                });
            }
            paso1 ? $("#paso1 > a").addClass("bg-soft-danger text-danger") : $("#paso1 > a").removeClass("bg-soft-danger text-danger");
            paso2 ? $("#paso2 > a").addClass("bg-soft-danger text-danger") : $("#paso2 > a").removeClass("bg-soft-danger text-danger");
            paso3 ? $("#paso3 > a").addClass("bg-soft-danger text-danger") : $("#paso3 > a").removeClass("bg-soft-danger text-danger");
            paso4 ? $("#paso4 > a").addClass("bg-soft-danger text-danger") : $("#paso4 > a").removeClass("bg-soft-danger text-danger");
        });

        $(function(){
            $("form").on("submit", function(e) {
                e.preventDefault();
                var form = $(this);
                var formData = form.serialize();
                $.ajax({
                    url: form.attr("action"),
                    type: form.attr("method"),
                    data: formData,
                    success: function (data) {
                        console.log(data);
                        console.log(data.error);
                        if(data.error != false) {

                            $("body").append("<div class=\'alert alert-success\' role=\'alert\'>"
                            +"<i class=\'fa fa-exclamation fa-1x\'></i> " + data.message + "</div>"); 
                            window.setTimeout(function() { $(".alert").alert("close"); }, 6000);
                            //console.log("asdasd");
                            //location.reload();

                        } else {

                            $("body").append("<div class=\'alert alert-error\' role=\'alert\'>"
                            +"<i class=\'fa fa-exclamation fa-1x\'></i> " + data.message + "</div>");
                            window.setTimeout(function() { $(".alert").alert("close"); }, 6000); 
                        }
                    },
                    error: function () {
                        $("body").append("<div class=\'alert alert-error\' role=\'alert\'>"
                            +"<i class=\'fa fa-exclamation fa-1x\'></i> Error inesperado</div>"); 
                        window.setTimeout(function() { $(".alert").alert("close"); }, 6000); 
                    }
                });
            });            
        });               
    ',
    yii\web\View::POS_HEAD
)
?>