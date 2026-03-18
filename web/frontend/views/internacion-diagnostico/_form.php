<?php

use common\models\SegNivelInternacionDiagnostico;
use kartik\select2\Select2;
use wbraganca\dynamicform\DynamicFormWidget;
use yii\helpers\Url;
use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use yii\web\JsExpression;


/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionDiagnostico */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="seg-nivel-internacion-diagnostico-form">
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
                                    <label class="form-label" for="internacion-diagnostico-intake-text">Describí el/los diagnósticos</label>
                                    <textarea id="internacion-diagnostico-intake-text" class="form-control" rows="3" placeholder="Ej: neumonía adquirida en la comunidad, insuficiencia respiratoria..."></textarea>
                                    <div class="form-text">El asistente detecta el texto del diagnóstico; la selección del concepto SNOMED se confirma en el selector.</div>
                                </div>
                                <div class="col-12 col-lg-3">
                                    <button type="button" id="internacion-diagnostico-intake-analyze" class="btn btn-outline-primary w-100">Analizar</button>
                                </div>
                            </div>
                            <div id="internacion-diagnostico-intake-status" class="mt-3" style="display:none;"></div>
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
                        ],
                    ]);
                    ?>
                    <div class="d-flex justify-content-end bd-highlight mb-3">
                        <button type="button" class="add-item btn btn-soft-success btn-sm pull-right">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-plus-square" viewBox="0 0 16 16">
                                <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z" />
                                <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z" />
                            </svg>
                        </button>
                    </div>

                    <table class="table table-bordered table-striped margin-b-none">
                        <thead>
                            <tr>
                                <th class="required">Concepto</th>
                                <th style="width: 90px; text-align: center"></th>
                            </tr>
                        </thead>
                        <tbody class="container-items">
                            <?php foreach ($models as $i => $model) : ?>
                                <tr class="item">
                                    <td>

                                        <?php $data = !$model->diagnosticoSnomed ? [] : [$model->conceptId => $model->diagnosticoSnomed->term]; ?>
                                        <?=
                                        $form->field($model, "[{$i}]conceptId")->widget(Select2::classname(), [
                                            'data' => $data,
                                            'theme' => 'bootstrap',
                                            'language' => 'es',
                                            'options' => ['placeholder' => '-Seleccione-', 'class' => 'diagnostico_select'],
                                            'pluginOptions' => [
                                                //                                                'allowClear' => true
                                                'minimumInputLength' => 3,
                                                'ajax' => [
                                                    'url' => Url::to(['consultas/snomed-diagnosticos']),
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
                        <!--tfoot>
                        <tr>
                            <td colspan="2"></td>
                            <td><button type="button" class="add-item btn btn-success btn-sm"><span class="glyphicon glyphicon-plus"></span>Nuevo Diagnóstico</button></td>
                        </tr>
                    </tfoot-->
                    </table>

                    <?php DynamicFormWidget::end(); ?>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <?= Html::submitButton($models[0]->isNewRecord ? 'Guardar' : 'Guardar cambios', ['class' => $models[0]->isNewRecord ? 'btn btn-success rounded-pill' : 'btn btn-primary rounded-pill']) ?>
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
    const btn = document.getElementById('internacion-diagnostico-intake-analyze');
    const textarea = document.getElementById('internacion-diagnostico-intake-text');
    const status = document.getElementById('internacion-diagnostico-intake-status');
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
            const payload = { entity: 'internacion_diagnostico', intent: 'internacion_agregar_diagnostico', text };
            const resp = window.VitaMindAjax
                ? await window.VitaMindAjax.fetchPost(url, payload)
                : await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });

            const json = resp.ok ? await resp.json() : await resp.json().catch(() => null);
            if (!resp.ok || !json || !json.success) {
                showStatus('No se pudo analizar el texto.', 'alert alert-danger');
                return;
            }

            const prefill = (json.data && json.data.prefill) ? json.data.prefill : {};
            const diag = prefill.diagnostico || null;
            const missing = (json.data && json.data.missing_required) ? json.data.missing_required : [];

            if (diag) {
                showStatus(`Diagnóstico detectado: <strong>${diag}</strong>. Seleccioná el concepto en el selector.`, missing.length ? 'alert alert-warning' : 'alert alert-success');
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