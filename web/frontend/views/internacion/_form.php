<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use common\models\RrhhEfector;
use common\models\RrhhServicio;
use common\models\Telefono;
use kartik\select2\Select2;
use kartik\date\DatePicker;
use kartik\time\TimePicker;
use yii\helpers\ArrayHelper;
use common\models\SegNivelInternacion;
/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacion */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="seg-nivel-internacion-form">

    <?php $form = ActiveForm::begin(['enableClientValidation' => false]); ?>
    <?= $form->errorSummary($model); ?>
    <?= $form->errorSummary($model_cama); ?>

    <div class="card mb-3">
        <div class="card-header bg-soft-info">
            <h5 class="mb-0">Carga por texto (asistente)</h5>
        </div>
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg-9">
                    <label class="form-label" for="internacion-intake-text">Describí el ingreso (texto o dictado transcripto)</label>
                    <textarea id="internacion-intake-text" class="form-control" rows="3" placeholder="Ej: ingreso por guardia, deambula, acompañado por familiar, contacto..., situación al ingresar..."></textarea>
                    <div class="form-text">Esto pre-carga campos del formulario; siempre podés editar antes de guardar.</div>
                </div>
                <div class="col-12 col-lg-3">
                    <button type="button" id="internacion-intake-analyze" class="btn btn-outline-primary w-100">
                        Analizar y pre-cargar
                    </button>
                </div>
            </div>
            <div id="internacion-intake-status" class="mt-3" style="display:none;"></div>
        </div>
    </div>

    <div class="col-sm-12 mb-5">
        <div class="card-group">
            <div class="card">
                <div class="row no-gutters">
                    <div class="col-md-4">
                        <div class="card-header bg-soft-primary d-flex justify-content-center align-items-center ">
                            <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" fill="currentColor" class="bi bi-person-lines-fill" viewBox="0 0 16 16">
                                <path d="M6 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-5 6s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H1zM11 3.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5zm.5 2.5a.5.5 0 0 0 0 1h4a.5.5 0 0 0 0-1h-4zm2 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1h-2zm0 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1h-2z" />
                            </svg>

                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card-body">

                            <?= $form->field($model, 'id_persona')->hiddenInput(['value' => $persona->id_persona]) ?>

                            <h5><?= $persona->nombre . ' ' . $persona->otro_nombre . ' ' . $persona->apellido . " " . $persona->otro_apellido ?></h5>
                            <h5><?= $persona->tipoDocumento->nombre . ' ' . $persona->documento ?></h5>

                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="row no-gutters">
                    <div class="col-md-4">
                        <div class="card-header bg-soft-primary d-flex justify-content-center align-items-center ">

                            <svg fill="currentColor" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 489.7 489.7" xml:space="preserve" width="100">
                                <g>
                                    <g>
                                        <g>
                                            <rect x="0" width="58.3" height="489.7" />
                                            <rect x="432.2" y="281.8" width="57.5" height="207.9" />
                                            <path d="M185,287.7h224.6l0,0c0-39.3-31.9-70.7-70.7-70.7H185V287.7z" />
                                            <ellipse cx="119.8" cy="253.8" rx="33.8" ry="33.4" />
                                            <rect x="77.8" y="318.7" width="334.2" height="57.9" />
                                            <path d="M312.5,0H154v158.2h158.2V0H312.5z M266.7,93.7h-19v19c0,7.9-6.4,14.4-14.4,14.4l0,0c-7.9,0-14.4-6.4-14.4-14.4v-19h-19
                                                    c-7.9,0-14.4-6.4-14.4-14.4l0,0c0-7.9,6.4-14.4,14.4-14.4h19V45.5c0-7.9,6.4-14.4,14.4-14.4l0,0c7.9,0,14.4,6.4,14.4,14.4v19h19
                                                    c7.9,0,14.4,6.4,14.4,14.4v0.4C281,87.3,274.6,93.7,266.7,93.7z" />
                                        </g>
                                    </g>
                                </g>
                            </svg>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card-body mt-3">
                            <?= "<h5 class='list-inline-item mb-1'>Piso/Sector: </h5>" . $model_cama->sala->piso->descripcion . "</br><h5 class='list-inline-item mb-1'>Sala: </h5>" . $model_cama->sala->descripcion . "</br><h5 class='list-inline-item'>Cama:  </h5>" . $model_cama->nro_cama;
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="card">
        <div class="card-body">
            <?php
            $rrhh_efector = new RrhhEfector();
            $profesionales = $rrhh_efector->obtenerMedicosPorEfector(yii::$app->user->getIdEfector());

            //EL ID QUE SE GUARDA EN ID_RRHH ES EL ID DE RRHH SERVICIO.
            
            echo $form->field($model, 'id_rrhh')->widget(Select2::classname(), [
                'data' => ArrayHelper::map($profesionales, 'id', 'datos'),
                'theme' => Select2::THEME_DEFAULT,
                'language' => 'en',
                'options' => ['placeholder' => 'Seleccione el Profesional...',
                                'class' => 'form-control'],
                'pluginOptions' => [
                    'allowClear' => true
                ],
            ]);
            ?>

            <div class="row">
                <div class="col-md-6">
                    <?= $form->field($model, 'fecha_inicio')->widget(DatePicker::className(), [
                        'type' => DatePicker::TYPE_COMPONENT_APPEND,
                        'pickerIcon' => '<i class="bi bi-calendar2-week"></i>',
                        'removeIcon' => '<i class="bi bi-trash"></i>',
                        'pluginOptions' => [
                            'autoclose' => true
                        ]
                    ]) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'hora_inicio')->widget(TimePicker::classname(), [
                        'pluginOptions' => [
                            'upArrowStyle' => 'bi bi-chevron-up',
                            'downArrowStyle' => 'bi bi-chevron-down',
                            'showMeridian' => false,
                        ],
                        'addon' => '<i class="bi bi-clock"></i>',
                    ]); ?>
                </div>
            </div>

        </div>
    </div>

    <div class="card">
        <div class="card-header bg-soft-info">
            <h5>Situación al Ingresar</h5>
        </div>
        <div class="card-body">
            <?php

            echo $form->field($model, 'id_tipo_ingreso', [
                'template' => '{input}{error}{hint}'
            ])->dropDownList(SegNivelInternacion::TIPO_INGRESO, [
                'id' => 'id_tipo_ingreso',
                'prompt' => 'Seleccione Tipo de Ingreso',
            ]);
            ?>

            <?= $form->field($model, 'id_efector_origen')->widget(
                        Select2::classname(), 
                        [
                            'data' => ArrayHelper::map($efectores, 'id_efector', 'nombre'),
                            'theme' => Select2::THEME_DEFAULT,
                            'language' => 'en',
                            'options' => ['placeholder' => 'Seleccione Efector origen'],
                            'pluginOptions' => ['allowClear' => true,],
                        ]); ?>

            <div class="row">
                <div class="col-sm-6">
                    <?= $form->field($model, 'ingresa_en')->radioList(SegNivelInternacion::INGRESO_EN); ?>
                </div>
                <div class="col-sm-6">
                    <?= $form->field($model, 'ingresa_con')->radioList(SegNivelInternacion::INGRESO_CON); ?>
                </div>
            </div>


            <div class="row border mt-3 mb-3" id="datos-acompañante" style="display: none">

                <h5 class="mt-3 mb-3 ps-5">Datos del Acompañante</h5>

                <div class="col-sm-6">
                    <?= $form->field($model, 'datos_contacto_nombre')->textInput(['maxlength' => true]) ?>
                </div>
                <div class="col-sm-2">

                    <?php

                    echo $form->field($telefono, 'prefijo')->widget(Select2::classname(), [
                        'data' => Telefono::PREFIJO,
                        'theme' => Select2::THEME_DEFAULT,
                        'language' => 'en',
                        'value' => '+54',
                        'pluginOptions' => [
                            'allowClear' => true
                        ],
                    ]);
                    ?>

                </div>
                <div class="col-sm-1">
                    <?= $form->field($telefono, 'codArea')->textInput(['maxlength' => true]) ?>
                </div>
                <div class="col-sm-3">
                    <?= $form->field($telefono, 'numTelefono')->textInput(['maxlength' => true]) ?>
                </div>
            </div>
            <?= $form->field($model, 'situacion_al_ingresar')->textarea(['rows' => 4]) ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-soft-info">
            <h6>Obra Social</h6>
        </div>
        <div class="card-body">
          <?= $form->field($model, 'obra_social')->widget(Select2::classname(), [
                'data' => $coberturas,
                'theme' => Select2::THEME_DEFAULT,
                'language' => 'en',
                'options' => ['placeholder' => 'Seleccione cobertura social …',
                                'class' => 'form-control'],
                'pluginOptions' => [
                    'allowClear' => true
                ],
            ]);?>
        </div>
    </div>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-success rounded-pill']) ?>
    </div>

    <script>
    (function() {
        const btn = document.getElementById('internacion-intake-analyze');
        const textarea = document.getElementById('internacion-intake-text');
        const status = document.getElementById('internacion-intake-status');
        if (!btn || !textarea) return;

        function showStatus(html, cls) {
            if (!status) return;
            status.style.display = 'block';
            status.className = cls;
            status.innerHTML = html;
        }

        function setField(attr, value) {
            if (value === null || value === undefined) return;

            // Radio lists
            const radio = document.querySelector(`input[name="SegNivelInternacion[${attr}]"][type="radio"][value="${value}"]`);
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }

            // Default input/select/textarea
            const el = document.getElementById(`segnivelinternacion-${attr}`);
            if (!el) return;

            el.value = value;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));

            // Select2 (si está)
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                try {
                    window.jQuery(el).trigger('change.select2');
                } catch (e) {}
            }
        }

        btn.addEventListener('click', async () => {
            const text = (textarea.value || '').trim();
            if (!text) {
                showStatus('Escribí un texto para analizar.', 'alert alert-warning');
                return;
            }

            btn.disabled = true;
            showStatus('Analizando...', 'alert alert-info');

            try {
                const baseUrl = (window.spaConfig && window.spaConfig.baseUrl) ? window.spaConfig.baseUrl : window.location.origin;
                const url = `${baseUrl}/api/v1/entity-intake/analyze`;

                const payload = { entity: 'internacion_ingreso', intent: 'internacion_ingreso', text };
                const resp = window.VitaMindAjax
                    ? await window.VitaMindAjax.fetchPost(url, payload)
                    : await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });

                const json = resp.ok ? await resp.json() : await resp.json().catch(() => null);
                if (!resp.ok || !json || !json.success) {
                    showStatus('No se pudo analizar el texto.', 'alert alert-danger');
                    return;
                }

                const prefill = (json.data && json.data.prefill) ? json.data.prefill : {};
                Object.keys(prefill).forEach((k) => setField(k, prefill[k]));

                const missing = (json.data && json.data.missing_required) ? json.data.missing_required : [];
                if (missing.length) {
                    showStatus(`Pre-cargado. Faltan campos requeridos: <strong>${missing.join(', ')}</strong>.`, 'alert alert-warning');
                } else {
                    showStatus('Pre-cargado correctamente. Revisá y guardá.', 'alert alert-success');
                }
            } catch (e) {
                showStatus('Error al analizar el texto. Intentá nuevamente.', 'alert alert-danger');
            } finally {
                btn.disabled = false;
            }
        });
    })();
    </script>

    <?php ActiveForm::end(); ?>

</div>

<?php
$const_tipo_ingreso_derivacion = SegNivelInternacion::TIPO_INGRESO_DERIVACION;

$this->registerJs("
$( document ).ready(function() {
    var tipo_ingreso_derivacion = $const_tipo_ingreso_derivacion;
    var model_tipo_ingreso = '$model->id_tipo_ingreso';
    if( model_tipo_ingreso != tipo_ingreso_derivacion) {
        $('.field-segnivelinternacion-id_efector_origen').hide();
    }

    $('#id_tipo_ingreso').on('change', function() {
        if($('#id_tipo_ingreso').val() == tipo_ingreso_derivacion) {
            $('.field-segnivelinternacion-id_efector_origen').show();
        }
        else {
            $('#segnivelinternacion-id_efector_origen').empty();
        }
    });

    if('" . $model->ingresa_con . "' == 'familiar' || '" . $model->ingresa_con . "' == 'otro' || '" . $model->ingresa_con . "' == 'policia'){
        $('#datos-acompañante').show();
    }


    $('#i4').on('change', function() {
        if ($(this).is(':checked') ) {
            $('#datos-acompañante').show();
        } 
    });

    $('#i6').on('change', function() {
        if ($(this).is(':checked') ) {
            $('#datos-acompañante').show();
        } 
    });

    $('#i3').on('change', function() {
        if ($(this).is(':checked') ) {
            $('#datos-acompañante').hide();
        }
    });

    $('#i5').on('change', function() {
        if ($(this).is(':checked') ) {
            $('#datos-acompañante').show();
        }
    });

    $('#i7').on('change', function() {
        if ($(this).is(':checked') ) {
            $('#datos-acompañante').hide();
        }
    });

    
    
});
");

?>