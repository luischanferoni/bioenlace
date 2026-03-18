<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use kartik\select2\Select2;
use yii\web\JsExpression;
use common\models\RrhhEfector;
use yii\helpers\ArrayHelper;
use wbraganca\dynamicform\DynamicFormWidget;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionPractica */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="seg-nivel-internacion-practica-form">
    <div class="row">
        <div class="col-sm-2"></div>
        <div class="col-sm">
            <div class="card">
                <div class="card-body">

                    <div class="card mb-3">
                        <div class="card-header bg-soft-info">
                            <h5 class="mb-0">Carga por texto (asistente)</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-lg-9">
                                    <label class="form-label" for="internacion-practica-intake-text">Describí la práctica/estudio</label>
                                    <textarea id="internacion-practica-intake-text" class="form-control" rows="3" placeholder="Ej: solicitar radiografía de tórax, laboratorio completo..."></textarea>
                                    <div class="form-text">El asistente detecta el texto de la práctica; la selección del concepto se confirma en el selector.</div>
                                </div>
                                <div class="col-12 col-lg-3">
                                    <button type="button" id="internacion-practica-intake-analyze" class="btn btn-outline-primary w-100">Analizar</button>
                                </div>
                            </div>
                            <div id="internacion-practica-intake-status" class="mt-3" style="display:none;"></div>
                        </div>
                    </div>

                    <?php $form = ActiveForm::begin(['id' => 'dynamic-form']); ?>
                    <?php
                    DynamicFormWidget::begin([
                        'widgetContainer' => 'dynamicform_wrapper',
                        'widgetBody' => '.container-items', // required: css class selector
                        'widgetItem' => '.item', // required: css class
                        'limit' => 10, // the maximum times, an element can be cloned (default 999)
                        'min' => 1, // 0 or 1 (default 1)
                        'insertButton' => '.add-item', // css class
                        'deleteButton' => '.remove-item', // css class
                        'model' => $models[0],
                        'formId' => 'dynamic-form',
                        'formFields' => [
                            'id_internacion',
                            'conceptId',
                            'resultado',
                            'informe',

                        ],
                    ]);
                    ?>

                    <div class="row " style="margin-bottom: 10px;">
                        <div class="col-md-3">
                            <label for="id_rrhh_solicita">Profesional</label>
                        </div>

                        <div class="col-md-9">
                            <?php
                            $rrhh_Efector = new RrhhEfector();
                            $profesionales = $rrhh_Efector->obtenerProfesionalesPorEfector(yii::$app->user->getIdEfector());

                            echo Select2::widget([
                                'name' => "id_rrhh_solicita",
                                'data' => ArrayHelper::map($profesionales, 'id_rr_hh', 'datos'),
                                'theme' => 'bootstrap',
                                'language' => 'en',
                                'options' => ['placeholder' => 'Seleccione el Profesional que solicita'],
                                'pluginOptions' => [
                                    'allowClear' => true
                                ],
                            ]);
                            ?>
                        </div>

                    </div>


                    <table class="table table-bordered table-striped margin-b-none">
                        <thead>
                            <tr>
                                <th class="required">Practicas Solicitadas</th>
                                <th style="width: 90px; text-align: center">
                                    <button type="button" class="add-item btn btn-soft-success btn-sm pull-right">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-plus-square" viewBox="0 0 16 16">
                                            <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z" />
                                            <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z" />
                                        </svg>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="container-items">
                            <?php foreach ($models as $i => $model) : ?>
                                <tr class="item">
                                    <td>
                                        <?=
                                        $form->field($model, "[{$i}]conceptId")->widget(Select2::classname(), [
                                            'theme' => 'bootstrap',
                                            'language' => 'es',
                                            'options' => ['placeholder' => '-Seleccione la Práctica-'],
                                            'pluginOptions' => [
                                                'minimumInputLength' => 3,
                                                'ajax' => [
                                                    'url' => Url::to(['consultas/snomed-practicas']),
                                                    'dataType' => 'json',
                                                    'delay'=> 500,
                                                    'data' => new JsExpression('function(params) { return {q:params.term}; }')
                                                ],
                                            ],
                                        ])->label(false)
                                        ?>
                                    </td>

                                    <?php //if ($model->isNewRecord){
                                    ?>
                                    <td class="text-center vcenter">
                
                                        <button type="button" class="remove-item btn btn-soft-danger btn-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-dash-square" viewBox="0 0 16 16">
                                                <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z" />
                                                <path d="M4 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 4 8z" />
                                            </svg>
                                        </button>
                                    </td>
                                    <?php // }
                                    ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php DynamicFormWidget::end(); ?>


                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <?= Html::submitButton($model->isNewRecord ? 'Guardar' : 'Guardar cambios', ['class' => $model->isNewRecord ? 'btn btn-success rounded-pill' : 'btn btn-primary rounded-pill']) ?>
                        <?= Html::a('Cancelar', ['internacion/view', 'id' => $id_internacion], ['class' => 'btn btn-danger rounded-pill']) ?>
                    </div>

                </div>
            </div>
        </div>
        <div class="col-sm-2"></div>
    </div>

    <?php ActiveForm::end(); ?>

</div>

<script>
(function() {
    const btn = document.getElementById('internacion-practica-intake-analyze');
    const textarea = document.getElementById('internacion-practica-intake-text');
    const status = document.getElementById('internacion-practica-intake-status');
    if (!btn || !textarea) return;

    function showStatus(html, cls) {
        if (!status) return;
        status.style.display = 'block';
        status.className = cls;
        status.innerHTML = html;
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
            const payload = { entity: 'internacion_practica', intent: 'internacion_agregar_practica', text };
            const resp = window.VitaMindAjax
                ? await window.VitaMindAjax.fetchPost(url, payload)
                : await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });

            const json = resp.ok ? await resp.json() : await resp.json().catch(() => null);
            if (!resp.ok || !json || !json.success) {
                showStatus('No se pudo analizar el texto.', 'alert alert-danger');
                return;
            }

            const prefill = (json.data && json.data.prefill) ? json.data.prefill : {};
            const practica = prefill.practica || null;
            const missing = (json.data && json.data.missing_required) ? json.data.missing_required : [];

            if (practica) {
                showStatus(`Práctica detectada: <strong>${practica}</strong>. Seleccioná el concepto en el selector.`, missing.length ? 'alert alert-warning' : 'alert alert-success');
            } else if (missing.length) {
                showStatus(`Faltan campos requeridos: <strong>${missing.join(', ')}</strong>.`, 'alert alert-warning');
            } else {
                showStatus('Listo. Seleccioná el concepto en el selector.', 'alert alert-success');
            }
        } catch (e) {
            showStatus('Error al analizar el texto. Intentá nuevamente.', 'alert alert-danger');
        } finally {
            btn.disabled = false;
        }
    });
})();
</script>