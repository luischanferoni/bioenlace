<?php

use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Url;

use kartik\select2\Select2;
use kartik\date\DatePicker;
use stitchua\dynamicform\DynamicFormWidget;

use common\models\Servicio;
use common\models\Condiciones_laborales;
use common\models\Agenda_rrhh;

/* @var $this yii\web\View */
/* @var $model common\models\RrhhEfector */
/* @var $form yii\widgets\ActiveForm */
?>
<?php
    $serviciosQuery = Servicio::find()
    ->innerJoin('servicios_efector', 
    'servicios_efector.id_servicio = servicios.id_servicio AND servicios_efector.id_efector = '.Yii::$app->user->getIdEfector());

    if (!$conServiciosParaSalud) {
        $serviciosQuery->andWhere(['servicios.verificacion_sisa' => 'NO']);
    }

    $servicios = $serviciosQuery->asArray()->all();
    $data_servicios = ArrayHelper::map($servicios, 'id_servicio', 'nombre');
    
    // Los servicios que no necesitan agenda
    $map_con_acepta_turnos = ArrayHelper::map($servicios, 'id_servicio', 'nombre', 'acepta_turnos');
    if (!isset($map_con_acepta_turnos["SI"])) {$map_con_acepta_turnos["SI"] = [];}
    if (!isset($map_con_acepta_turnos["NO"])) {$map_con_acepta_turnos["NO"] = [];}
    //var_dump(array_keys($map_con_acepta_turnos["SI"]));
    // condiciones laborales
    $c_laborales = ArrayHelper::map(Condiciones_laborales::find()->asArray()->all(), 'id_condicion_laboral', 'nombre');        
?>

<?php
    $form = ActiveForm::begin(
        [
            'options' => ['id' => 'form-rrhh', 'class' => 'form-horizontal'],
            'enableClientValidation' => false
        ]);
?>

    <?= $form->errorSummary($modelosRrhhServicios); ?>
    <?= $form->errorSummary($modelosRrhhCondicionesLaborales); ?>
    <?= $form->errorSummary($modelosAgendas); ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <div class="header-title">
                <h4 class="card-title">Servicios</h4>
            </div>
        </div>
        <div class="card-body" id="dynamic-servicio-form">
            <?php
                DynamicFormWidget::begin([
                    'widgetContainer' => 'dynamicform_wrapper_model_servicios', // required: only alphanumeric characters plus "_" [A-Za-z0-9_]
                    'widgetBody' => '.container-items1', // required: css class selector
                    'widgetItem' => '.item1', // required: css class
                    'limit' => 10, // the maximum times, an element can be cloned (default 999)
                    'min' => 1, // 0 or 1 (default 1)
                    'insertButton' => '.add-item1', // css class
                    'deleteButton' => '.remove-item1', // css class
                    'model' => $modelosRrhhServicios[0],
                    'formId' => 'form-rrhh',
                    'formFields' => [
                        'id_servicio',
                        'nombre',
                        'cupo_pacientes',
                        'regla',
                    ],
                ]);
            ?>
            <div class="container-items1">                
                <?php foreach ($modelosRrhhServicios as $i => $modeloRrhhServicio): ?>                   
                    <div class="item1 row mb-3">
                        <?php 
                        $model_agenda = $modelosAgendas[$i];
                        // para el update
                        if (!$modeloRrhhServicio->isNewRecord) {

                            echo Html::activeHiddenInput(
                                    $modeloRrhhServicio, 
                                    "[{$i}]id",
                                    ['class'=>'pk-id-field']);
                            echo Html::hiddenInput(
                                    "Agenda_rrhh[{$i}][id_agenda_rrhh]",
                                    $model_agenda->id_agenda_rrhh,
                                    ['class'=>'pk-id-field']);
                        }
                        ?>
                        <div class="col col-md-6">
                            <?= 
                                $form->field($modeloRrhhServicio, "[{$i}]id_servicio", 
                                        ['options' => ['class' => '']],
                                        ['labelOptions' =>  ['class' => 'col-sm-4 control-label align-self-center mb-0']]
                                    )->widget(Select2::classname(), [
                                        'data' => $data_servicios,
                                        'theme' => Select2::THEME_DEFAULT,
                                        'language' => 'es',
                                        'options' => ['placeholder' => 'Servicio', 'class' => 'rrhhservicio_id_servicio',],
                                        'pluginOptions' => [
                                            'allowClear' => true,
                                            'width' => '100%'
                                        ],
                                ])->label(false);
                            ?>
                            <?= $form->errorSummary($model_agenda); ?>
                        </div>
                        <div class="col col-md-2">                            
                            <?= $form->field($model_agenda, "[{$i}]cupo_pacientes")
                                                    ->DropDownList(Agenda_rrhh::CUPOS, 
                                                    [
                                                    //'prompt' => 'Sin cupo',
                                                    'data-bs-toggle' => 'tooltip',
                                                    'data-bs-placement' => 'top',
                                                    'data-bs-original-title' => 'Cupo de pacientes',
                                                    'disabled' => in_array($modeloRrhhServicio->id_servicio, array_keys($map_con_acepta_turnos["SI"]))?false:true])->label(false) ?>
                        </div>
                        <div class="col col-md-3">                            
                            <?= $form->field($model_agenda, "[{$i}]formas_atencion")->DropDownList( 
                                                        Agenda_rrhh::FORMAS_ATENCION, 
                                                        [
                                                        'prompt' => 'Seleccione la forma de atención',
                                                        'data-bs-toggle' => 'tooltip',
                                                        'data-bs-placement' => 'top',
                                                        'data-bs-original-title' => 'Regla de asignación de los turnos',
                                                        'disabled' => in_array($modeloRrhhServicio->id_servicio, array_keys($map_con_acepta_turnos["SI"]))?false:true])->label(false);
                            ?>
                        </div>                        
                        <div class="col col-md-1">
                            <button type="button" class="btn btn-outline-link rounded-pill float-xl-end remove-item1" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="Quitar esta fila">
                                <svg class="icon-32" width="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M14.3955 9.59497L9.60352 14.387" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                    <path d="M14.3971 14.3898L9.60107 9.59277" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M16.3345 2.75024H7.66549C4.64449 2.75024 2.75049 4.88924 2.75049 7.91624V16.0842C2.75049 19.1112 4.63549 21.2502 7.66549 21.2502H16.3335C19.3645 21.2502 21.2505 19.1112 21.2505 16.0842V7.91624C21.2505 4.88924 19.3645 2.75024 16.3345 2.75024Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="col-lg-12 mt-1" id="agenda-<?=$i?>-div">
                            <h6><?=$modeloRrhhServicio->id_servicio?"Agenda laboral para ".$modeloRrhhServicio->servicio->nombre:''?></h6>
                            <table id="agenda-<?=$i?>-table"></table>
                            <?php 
                                if ($modeloRrhhServicio->id_servicio) {
                                    $this->registerJs('
                                        // cambiamos el titulo de la agenda                                        
                                        $("#agenda-'.$i.'-table").scheduler({
                                            data:{
                                                1:['.$model_agenda->lunes_2.'],2:['.$model_agenda->martes_2.'],
                                                3:['.$model_agenda->miercoles_2.'],4:['.$model_agenda->jueves_2.'],
                                                5:['.$model_agenda->viernes_2.'],6:['.$model_agenda->sabado_2.'],
                                                7:['.$model_agenda->domingo_2.'],
                                            },
                                            onSelect: function() {
                                                var valores = $(this).scheduler("val");                                
                                                for (var i=1; i<=7; i++) {
                                                    $("#agenda-'.$i.'-div").find("." + i).first().val(valores[i]);
                                                }
                                            }                                                
                                        });', yii\web\View::POS_END );
                                }
                            ?>
                            <?= Html::hiddenInput("Agenda_rrhh[{$i}][lunes_2]", $model_agenda->lunes_2, ['class' => 1]) ?>
                            <?= Html::hiddenInput("Agenda_rrhh[{$i}][martes_2]", $model_agenda->martes_2, ['class' => 2]) ?>
                            <?= Html::hiddenInput("Agenda_rrhh[{$i}][miercoles_2]", $model_agenda->miercoles_2, ['class' => 3]) ?>
                            <?= Html::hiddenInput("Agenda_rrhh[{$i}][jueves_2]", $model_agenda->jueves_2, ['class' => 4]) ?>
                            <?= Html::hiddenInput("Agenda_rrhh[{$i}][viernes_2]", $model_agenda->viernes_2, ['class' => 5]) ?>
                            <?= Html::hiddenInput("Agenda_rrhh[{$i}][sabado_2]", $model_agenda->sabado_2, ['class' => 6]) ?>
                            <?= Html::hiddenInput("Agenda_rrhh[{$i}][domingo_2]", $model_agenda->domingo_2, ['class' => 7]) ?>
                        </div>                            
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="row">
                <div class="col-xs-12">
                    <button type="button" class="add-item1 mt-2 btn btn-info rounded-pill float-xl-end text-white">
                        <i class="bi bi-plus-circle text-white"></i> Agregar Servicio
                    </button>
                </div>
            </div>
            <?php DynamicFormWidget::end(); ?>
        </div>
        <?php 
        $rrhhServiciosEliminados = $modeloRrhhEfector->rrhhServiciosEliminados;
        if (count($rrhhServiciosEliminados) > 0) {
        ?>
            <div class="card-body">
                <div class="row mb-5">                
                    <div class="col-12">
                        <label class="fw-bold">Servicios eliminados: </label>
                        <?php foreach ($rrhhServiciosEliminados as $rrhhServicio) { ?>
                            <?= Html::button('Reactivar '.$rrhhServicio->servicio->nombre,
                                        [
                                            'class' => 'btn btn-sm btn-outline-warning me-2 ajax-sweet-pjax',
                                            'data-url' => Url::to(['rrhh-efector/reactivar-rrhhservicio', 'id_rr_hh_servicio' => $rrhhServicio->id]),                                            
                                            'data-sweet_title' => 'Reactivar a '.$rrhhServicio->servicio->nombre.' como RRHH?',
                                        ]
                                    );
                            ?>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>
        <div class="card-header d-flex justify-content-between">
            <div class="header-title">
                <h4 class="card-title">Condiciones Laborales</h4>
            </div>
        </div>
        <div class="card-body" id="condicion-laboral-form">
            <?php
                DynamicFormWidget::begin([
                    'widgetContainer' => 'dynamicform_wrapper_model_rrhh_condiciones_laborales', // required: only alphanumeric characters plus "_" [A-Za-z0-9_]
                    'widgetBody' => '.container-items2', // required: css class selector
                    'widgetItem' => '.item2', // required: css class
                    'limit' => 10, // the maximum times, an element can be cloned (default 999)
                    'min' => 1, // 0 or 1 (default 1)
                    'insertButton' => '.add-item2', // css class
                    'deleteButton' => '.remove-item2', // css class
                    'model' => $modelosRrhhCondicionesLaborales[0],
                    'formId' => 'form-rrhh',
                    'formFields' => [
                        'id_condicion_laboral',
                        'nombre',                       
                    ],
                ]);
            ?>

            <div class="container-items2">
                <?php foreach ($modelosRrhhCondicionesLaborales as $i => $modelRrhhCondicion): ?>
                    <div class="item2 row mb-3">
                    <?php
                      // para el update
                      if (!$modelRrhhCondicion->isNewRecord) {                            
                        echo Html::activeHiddenInput(
                                $modelRrhhCondicion, 
                                "[{$i}]id",
                                ['class'=>'pk-id-field']);
                      }
                    ?>                        
                        <div class="col col-md-6">   
                            <?= $form->field($modelRrhhCondicion, "[{$i}]id_condicion_laboral", 
                                        ['options' => ['class' => '']]
                                    )->widget(Select2::classname(), [
                                        'data'=> $c_laborales,
                                        'theme' => Select2::THEME_DEFAULT,
                                        'language' => 'es',
                                        'options' => ['placeholder' => 'Condición laboral'],
                                        'pluginOptions' => [
                                            'allowClear' => true,
                                            'width' => '100%'
                                        ],                            
                                ])->label(false)
                            ?>
                        </div>
                        <div class="col">
                            <?= $form->field($modelRrhhCondicion, "[{$i}]fecha_inicio")
                                    ->widget(DatePicker::className(), [
                                        'type' => DatePicker::TYPE_INPUT,
                                        'options' => ['placeholder' => 'Fecha Inicio'],
                                        ])->label(false) ?>
                        </div>
                        <div class="col">
                            <?= $form->field($modelRrhhCondicion, "[{$i}]fecha_fin")
                                    ->widget(DatePicker::className(), [
                                        'type' => DatePicker::TYPE_INPUT,
                                        'options' => ['placeholder' => 'Fecha Fin'],
                                        ])->label(false) ?>
                        </div>
                        <div class="col col-md-1">
                            <button type="button" class="btn btn-outline-link rounded-pill remove-item2" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="Quitar esta fila">
                                <svg class="icon-32" width="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M14.3955 9.59497L9.60352 14.387" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                    <path d="M14.3971 14.3898L9.60107 9.59277" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M16.3345 2.75024H7.66549C4.64449 2.75024 2.75049 4.88924 2.75049 7.91624V16.0842C2.75049 19.1112 4.63549 21.2502 7.66549 21.2502H16.3335C19.3645 21.2502 21.2505 19.1112 21.2505 16.0842V7.91624C21.2505 4.88924 19.3645 2.75024 16.3345 2.75024Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </button>
                        </div>
                    </div>                    
                <?php endforeach; ?>
            </div>
            <div class="row">
                <div class="col-xs-12">
                    <button type="button" class="add-item2 mt-2 btn btn-info rounded-pill float-xl-end text-white">
                        <i class="bi bi-plus-circle text-white"></i> Agregar Condición
                    </button>
                </div>
            </div>

            <?php DynamicFormWidget::end(); ?>
        </div>
        <div class="card-body">
            <div class="form-group float-end">
                <?= Html::submitButton('Guardar', ['class' => 'btn btn-success']) ?>
            </div>
        </div>            
    </div>

<?php ActiveForm::end(); ?>

<?php
    $this->registerCssFile('@web/css/scheduler.css');
    $this->registerJsFile('@web/js/scheduler.js', ['depends' => [\yii\web\JqueryAsset::class]]);
    $this->registerJs('
            var servicios_con_agenda = ["'.implode('","', $map_con_acepta_turnos["SI"]).'"];
            $(document).ready(function() {
                // cada vez que selecciona un servicio debemos saber si acepta agenda
                $(document).on("select2:select select2:unselect", ".rrhhservicio_id_servicio", function (e) {
                    // Esto nos va a servir para buscar los elementos de la fila por id
                    // los id tienen el format model-index-attributo, id_array[1] es el indice de la fila
                    const id_array = e.target.id.split("-");
                    var seleccionado =  e.params.data;
                    $("#agenda-" + id_array[1] + "-div").find("h6").first().html("Agenda laboral para " + seleccionado.text);
                    // Necesitamos saber si el servicio seleccionado requiere de una agenda
                    
                    var acepta_agenda = servicios_con_agenda.includes(seleccionado.text);                    
                    
                    if (acepta_agenda) {
                        // Esto por si estaban deshabilitados
                        $("#agenda_rrhh-" + id_array[1] + "-cupo_pacientes").prop("disabled", false);
                        $("#agenda_rrhh-" + id_array[1] + "-formas_atencion").prop("disabled", false);

                    } else {
                        $("#agenda_rrhh-" + id_array[1] + "-cupo_pacientes").prop("disabled", "disabled");
                        $("#agenda_rrhh-" + id_array[1] + "-formas_atencion").prop("disabled", "disabled");
                    }
                    // Asociamos el id de servicio seleccionado con la agenda
                    //$("#agenda_rrhh-" + id_array[1] + "-id_rrhh_servicio_asignado").val(seleccionado.id);
                    // mostramos la agenda
                    $("#agenda-" + id_array[1] + "-div").show();
                    $("#agenda-" + id_array[1] + "-table").scheduler({
                        onSelect: function() {
                            var valores = $(this).scheduler("val");
                            for (var i=1; i<=7; i++) {
                                $("#agenda-" + id_array[1] + "-div").find("." + i).first().val(valores[i]);
                            }
                        }
                    });                    
                });
            });
        ');

    $this->registerJs('
            function initSelect2DropStyle(a,b,c){
                initS2ToggleAll(a,b,c);
            }
            function initSelect2Loading(a,b){
                initS2Loading(a,b);
            }
        ',
        yii\web\View::POS_HEAD
    );

    $this->registerJs(' 
        $(function () {
            $(".dynamicform_wrapper_model_rrhh_condiciones_laborales").on("afterInsert", function(e, item) {
                var datePickers = $(this).find(\'[data-krajee-kvdatepicker]\');
                datePickers.each(function() {
                    $( this ).kvDatepicker();
                });
                const tooltipTriggerList = document.querySelectorAll(\'[data-bs-toggle="tooltip"]\')
                const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))                
            });
        });
        $(function () {
            $(".dynamicform_wrapper_model_rrhh_condiciones_laborales").on("afterDelete", function(e, item) {
                var datePickers = $(this).find(\'[data-krajee-kvdatepicker]\');
                datePickers.each(function() {
                    $( this ).removeClass("hasDatepicker").kvDatepicker();
                });
            });
        });
    ');    
?>
