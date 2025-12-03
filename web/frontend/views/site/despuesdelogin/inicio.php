<?php

use yii\helpers\Url;
use yii\bootstrap5\Modal;
use yii\web\JsExpression;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper; 

use frontend\assets\FormWizardAsset;

FormWizardAsset::register($this);
?>

    <?php $form = ActiveForm::begin([
        'id' => 'dynamic-form',
        'options' => ['class' => 'form-wizard']
        ]);
    ?>

    <ul id="top-tab-list" class="p-0 row list-inline mb-0">
        <li id="paso1" class="mb-2 col text-start active">
            <a href="#" class="wizard_tab_link text-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-1-square" viewBox="0 0 16 16">
                    <path d="M9.283 4.002V12H7.971V5.338h-.065L6.072 6.656V5.385l1.899-1.383h1.312Z"/>
                    <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2Zm15 0a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V2Z"/>
                </svg>
            </a>
        </li>
        <li id="paso2" class="mb-2 col ps-0 pe-0 text-start">
            <a href="#" class="wizard_tab_link text-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-2-square" viewBox="0 0 16 16">
                    <path d="M6.646 6.24v.07H5.375v-.064c0-1.213.879-2.402 2.637-2.402 1.582 0 2.613.949 2.613 2.215 0 1.002-.6 1.667-1.287 2.43l-.096.107-1.974 2.22v.077h3.498V12H5.422v-.832l2.97-3.293c.434-.475.903-1.008.903-1.705 0-.744-.557-1.236-1.313-1.236-.843 0-1.336.615-1.336 1.306Z"/>
                    <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2Zm15 0a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V2Z"/>
                </svg>
            </a>
        </li>
        <li id="paso3" class="mb-2 col pe-0 text-start">
            <a href="#" class="wizard_tab_link text-center">                
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-3-square" viewBox="0 0 16 16">
                    <path d="M7.918 8.414h-.879V7.342h.838c.78 0 1.348-.522 1.342-1.237 0-.709-.563-1.195-1.348-1.195-.79 0-1.312.498-1.348 1.055H5.275c.036-1.137.95-2.115 2.625-2.121 1.594-.012 2.608.885 2.637 2.062.023 1.137-.885 1.776-1.482 1.875v.07c.703.07 1.71.64 1.734 1.917.024 1.459-1.277 2.396-2.93 2.396-1.705 0-2.707-.967-2.754-2.144H6.33c.059.597.68 1.06 1.541 1.066.973.006 1.6-.563 1.588-1.354-.006-.779-.621-1.318-1.541-1.318Z"/>
                    <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2Zm15 0a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V2Z"/>
                </svg>
            </a>
        </li>
    </ul>

    <fieldset class="formwizard_fieldset" id="formwizard_efectores">
        
        <?= $this->render('indexuserefector', 
                                [
                                    'searchEfectores' => $searchEfectores,
                                    'dataProviderEfectores' => $dataProviderEfectores,
                                ]); 
                                ?>
                           
        <button type="button" name="next" class="btn btn-primary next action-button float-end" value="Siguiente" disabled>Siguiente</button>
    </fieldset>
    
    <fieldset class="formwizard_fieldset" id="formwizard_encounter">

        <?= $this->render('inicio_encounter_class'); ?>

        <button type="button" name="next" class="btn btn-primary next a-servicio action-button float-end" value="Siguiente" disabled>Siguiente</button>
        <button type="button" name="previous" class="btn btn-dark previous action-button-previous float-end me-1" value="Anterior">Anterior</button>         
    </fieldset>

    <fieldset class="formwizard_fieldset" id="formwizard_servicios">

        <?= $this->render('inicio_servicio'); ?>

        <div class="form-group">            
            <button type="button" name="next" class="btn btn-primary next action-button float-end" value="Siguiente" disabled>Finalizar</button>
            <button type="button" name="previous" class="btn btn-dark previous action-button-previous float-end me-1" value="Previous">Anterior</button>            
        </div>        
    </fieldset>

    <?php ActiveForm::end(); ?>

<?php
    $this->registerJs('
        $(".a-servicio").on("click", function(e) {
            $.ajax({
                url: "'.Url::to(['rrhh-efector/servicios-por-rrhh']).'",
                type: "POST",
                data: {
                    idEfector: $("input[name=nombre_efector]:checked", "#grid_efectores").val(), 
                    //encounterClass: $("input[name=encounter_class]:checked").val()
                },
                success: function (data) {
                    console.log(data);

                    $("#div_servicios").html(data);
                },
                error: function () {
                    $("body").append("<div class=\'alert alert-error\' role=\'alert\'>"
                        +"<i class=\'fa fa-exclamation fa-1x\'></i> Error inesperado</div>"); 
                    window.setTimeout(function() { $(".alert").alert("close"); }, 6000); 
                }
            });
        });

        $("input[name=nombre_efector]").on("click", function(e){
            $("#formwizard_efectores .next").prop("disabled", false);
        });

        $("input[name=encounter_class]").on("click", function(e){
            $("#formwizard_encounter .next").prop("disabled", false);
        });

        $(document).on("click", "input[name=servicio]", function(e) {
            $("#formwizard_servicios .next").prop("disabled", false);
        });

        $("#formwizard_servicios .next").on("click", function(e){
            e.preventDefault();

            $.ajax({
                url: "'.Url::to(['site/establecer-session-final']).'",
                type: "POST",
                data: {
                    idEfector: $("input[name=nombre_efector]:checked", "#grid_efectores").val(), 
                    encounterClass: $("input[name=encounter_class]:checked").val(),
                    servicio: $("input[name=servicio]:checked").val()
                },
                success: function (data) {

                    window.location.replace(data);
                },
                error: function () {
                    $("body").append("<div class=\'alert alert-error\' role=\'alert\'>"
                        +"<i class=\'fa fa-exclamation fa-1x\'></i> Error inesperado</div>"); 
                    window.setTimeout(function() { $(".alert").alert("close"); }, 6000); 
                }
            });       
        });               
    ',
    yii\web\View::POS_END
)
?>