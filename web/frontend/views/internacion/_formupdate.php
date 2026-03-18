<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use common\models\RrhhEfector;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;
use kartik\date\DatePicker;
use kartik\time\TimePicker;
use common\models\SegNivelInternacion;
use common\models\SegNivelInternacionTipoAlta;
use common\models\Efector;
use yii\web\JsExpression;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacion */
/* @var $form yii\widgets\ActiveForm */
?>
<div class="seg-nivel-internacion-form">
    <?php $form = ActiveForm::begin([
        'enableClientValidation' => true,
        'id' => 'frm_internacion_alta']); ?>
    <?= $form->errorSummary($model); ?>

    <div class="card mb-3">
        <div class="card-header bg-soft-info">
            <h5 class="mb-0">Carga por texto (asistente)</h5>
        </div>
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg-9">
                    <label class="form-label" for="internacion-alta-intake-text">Describí el alta/externación</label>
                    <textarea id="internacion-alta-intake-text" class="form-control" rows="3" placeholder="Ej: alta por mejoría, fecha/hora..., observaciones..., derivación a..., condiciones..."></textarea>
                </div>
                <div class="col-12 col-lg-3">
                    <button type="button" id="internacion-alta-intake-analyze" class="btn btn-outline-primary w-100">Analizar y pre-cargar</button>
                </div>
            </div>
            <div id="internacion-alta-intake-status" class="mt-3" style="display:none;"></div>
        </div>
    </div>

    <?= $form->field($model, 'fecha_fin')->widget(DatePicker::className(), [
        'type' => DatePicker::TYPE_COMPONENT_APPEND,
        'pickerIcon' => '<i class="bi bi-calendar2-week"></i>',
        'removeIcon' => '<i class="bi bi-trash"></i>',
        'pluginOptions' => [
            'autoclose' => true
        ]
    ]) ?>

    <?= $form->field($model, 'hora_fin')->widget(TimePicker::classname(), [
         'pluginOptions' => [
            'upArrowStyle' => 'bi bi-chevron-up', 
            'downArrowStyle' => 'bi bi-chevron-down',
            'showMeridian' => false,
        ],
        'addon' => '<i class="bi bi-clock"></i>',
    ]); ?>

    <?php
    $tipos_alta = SegNivelInternacionTipoAlta::find()->all();

    echo $form->field($model, 'id_tipo_alta')->widget(Select2::classname(), [
        'data' => ArrayHelper::map($tipos_alta, 'id', 'tipo_alta'),
        'theme' => Select2::THEME_DEFAULT,
        'language' => 'en',
        'options' => [
            'placeholder' => 'Seleccione el motivo del alta hospitalaria',
            'onchange' => '
                if ($("#segnivelinternacion-id_tipo_alta").val() == "5"){
                        $("#derivacionBox").show();                        
                    } else {                        
                         $("#derivacionBox").hide();
                }'
        ],
        'pluginOptions' => [
            'allowClear' => true,
            'dropdownParent' => $modal_id
        ],
    ]);
    ?>
    <div id="derivacionBox">
        <?php
        $efectores = Efector::find()->all();

        echo $form->field($model, 'id_efector_derivacion')->widget(Select2::classname(), [
            'data' => ArrayHelper::map($efectores, 'id_efector', 'nombre'),
            'theme' => Select2::THEME_DEFAULT,
            'language' => 'en',
            'options' => ['placeholder' => 'Seleccione el lugar de derivación'],
            'pluginOptions' => [
                'allowClear' => true,
                'dropdownParent' => $modal_id
            ],
        ]);
        ?>
        <?= $form->field($model, 'condiciones_derivacion')->textarea(['rows' => 3]) ?>
    </div>

    <?= $form->field($model, 'observaciones_alta')->textarea(['rows' => 6]) ?>
    <div class="form-group">
        <?= Html::submitButton('Guardar', [
            'class' => 'btn btn-success',
            'id' => 'mdl_alta_btn_submit']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

<script>
(function() {
    const btn = document.getElementById('internacion-alta-intake-analyze');
    const textarea = document.getElementById('internacion-alta-intake-text');
    const status = document.getElementById('internacion-alta-intake-status');
    if (!btn || !textarea) return;

    function showStatus(html, cls) {
        if (!status) return;
        status.style.display = 'block';
        status.className = cls;
        status.innerHTML = html;
    }

    function setField(attr, value) {
        if (value === null || value === undefined) return;
        const el = document.getElementById(`segnivelinternacion-${attr}`);
        if (!el) return;
        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            try { window.jQuery(el).trigger('change.select2'); } catch (e) {}
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
            const payload = { entity: 'internacion_alta', intent: 'internacion_alta', text };
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

<?php
$const_tipo_alta_derivacion = SegNivelInternacion::TIPO_ALTA_DERIVACION_CMC;
$js = <<<EOJS
$( document ).ready(function() {
    var tipo_alta_derivacion = $const_tipo_alta_derivacion;
    var model_tipo_alta = '$model->id_tipo_alta';
    if( model_tipo_alta != tipo_alta_derivacion) {
        $('#derivacionBox').hide();
    }
});
EOJS;
$this->registerJs($js);