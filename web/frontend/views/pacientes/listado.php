<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;
use common\models\Consulta;

$esAmbulatorio = ($encounter_class === Consulta::ENCOUNTER_CLASS_AMB);
$fechaAnterior = date('Y-m-d', strtotime($fecha . ' -1 day'));
$fechaSiguiente = date('Y-m-d', strtotime($fecha . ' +1 day'));
$hoy = date('Y-m-d');

$encounterMeta = [
    Consulta::ENCOUNTER_CLASS_AMB => [
        'label' => 'Consultorio / ambulatorio',
        'descripcion' => 'Pacientes con turno pendiente de atención. La fecha define qué turnos se muestran (por atender en esa jornada).',
    ],
    Consulta::ENCOUNTER_CLASS_IMP => [
        'label' => 'Internación',
        'descripcion' => 'Pacientes actualmente internados en su efector.',
    ],
    Consulta::ENCOUNTER_CLASS_EMER => [
        'label' => 'Guardia',
        'descripcion' => 'Ingresos en guardia pendientes de atención en su efector.',
    ],
];
$metaEc = ($encounter_class && isset($encounterMeta[$encounter_class]))
    ? $encounterMeta[$encounter_class]
    : ['label' => '', 'descripcion' => ''];

$urlAjax = Url::to(['/api/v1/pacientes'], true);
$encounterJson = Json::encode($encounter_class);

$this->title = 'Pacientes';
?>

<div class="mb-4">
    <h2 class="mb-2"><?= Html::encode($this->title) ?></h2>
    <?php if ($metaEc['label']): ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="badge bg-primary"><?= Html::encode($metaEc['label']) ?></span>
        </div>
        <?php if (!empty($metaEc['descripcion'])): ?>
            <p class="text-muted small mb-0"><?= Html::encode($metaEc['descripcion']) ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($esAmbulatorio): ?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div class="text-muted small">
        <strong>Filtrar por fecha del turno:</strong> solo se listan turnos <strong>pendientes y sin atender</strong> en la fecha elegida.
    </div>
    <div class="btn-group" role="group">
        <a href="<?= Url::to(['pacientes/listado', 'fecha' => $fechaAnterior]) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-left"></i> Fecha anterior
        </a>
        <a href="<?= Url::to(['pacientes/listado', 'fecha' => $hoy]) ?>" class="btn btn-outline-secondary btn-sm">
            Fecha de hoy
        </a>
        <a href="<?= Url::to(['pacientes/listado', 'fecha' => $fechaSiguiente]) ?>" class="btn btn-outline-secondary btn-sm">
            Fecha siguiente <i class="bi bi-chevron-right"></i>
        </a>
    </div>
</div>
<?php endif; ?>

<div id="pacientes-listado-container">
    <div id="pacientes-listado-loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
        <p class="mt-2 text-muted">Cargando listado de pacientes…</p>
    </div>
    <div id="pacientes-listado-content" class="d-none"></div>
    <div id="pacientes-listado-error" class="d-none alert alert-warning"></div>
</div>

<?php
$esHoy = ($fecha === $hoy);
if ($esHoy): ?>
<div id="spa-pages-container" class="spa-pages-container"></div>
<?php
$this->registerJsFile('@web/js/spa-navigation.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('@web/js/spa-home.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerCssFile('@web/css/spa.css', ['depends' => [\yii\web\JqueryAsset::class]]);
?>
<?php endif; ?>

<?php
$urlInternacionView = Url::to(['internacion/view'], true);
$urlGuardiaIndex = Url::to(['guardia/index'], true);
$urlInternacionRonda = Url::to(['internacion/ronda'], true);
$this->registerJs(<<<JS
(function() {
    var \$ = window.jQuery;
    var container = document.getElementById('pacientes-listado-content');
    var loading = document.getElementById('pacientes-listado-loading');
    var errorEl = document.getElementById('pacientes-listado-error');
    var fecha = '{$fecha}';
    var encounter = {$encounterJson};

    function escapeHtml(s) {
        if (s == null) return '';
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    function renderTurnos(data) {
        if (!data.length) {
            container.innerHTML = '<div class="alert alert-secondary"><i class="bi bi-info-circle me-2"></i>No hay pacientes con turno pendiente de atención en la fecha seleccionada.</div>';
            return;
        }
        var html = '<div class="row">';
        data.forEach(function(t) {
            var nombre = (t.paciente && t.paciente.nombre_completo) ? t.paciente.nombre_completo : 'Sin paciente';
            var servicio = t.servicio || 'Sin servicio';
            var estadoClass = (t.estado === 'PENDIENTE') ? 'warning' : 'secondary';
            var estadoLabel = t.estado_label || t.estado || '';
            var obs = (t.observaciones) ? '<div class="mb-2"><strong><i class="bi bi-chat-left-text me-2"></i>Observaciones:</strong> <small class="text-muted">' + escapeHtml(t.observaciones) + '</small></div>' : '';
            html += '<div class="col-md-6 col-lg-4 mb-3">' +
                '<div class="card h-100 shadow-sm">' +
                '<div class="card-body">' +
                '<h5 class="card-title"><i class="bi bi-person-circle text-primary me-2"></i>' + escapeHtml(nombre) + '</h5>' +
                '<div class="mb-2"><strong><i class="bi bi-clock me-2"></i>Turno:</strong> ' + escapeHtml(t.hora) + '</div>' +
                '<div class="mb-2"><strong><i class="bi bi-hospital me-2"></i>Servicio:</strong> ' + escapeHtml(servicio) + '</div>' + obs +
                '<div class="mt-3"><span class="badge bg-' + estadoClass + '">' + escapeHtml(estadoLabel) + '</span></div>' +
                '</div></div></div>';
        });
        html += '</div>';
        container.innerHTML = html;
    }

    function renderInternados(data) {
        if (!data.length) {
            container.innerHTML = '<div class="alert alert-secondary"><i class="bi bi-info-circle me-2"></i>No hay pacientes internados para mostrar.</div>';
            return;
        }
        var html = '<div class="card"><div class="card-header"><h4 class="mb-0">Pacientes internados</h4></div><div class="card-body">';
        data.forEach(function(i) {
            var urlView = '{$urlInternacionView}' + '?id=' + i.id;
            html += '<div class="d-flex align-items-center p-3 mb-2 bg-soft-gray rounded">' +
                '<div class="ms-3" style="flex:1;">' +
                '<h5 class="mb-0">' + escapeHtml(i.nombre) + '</h5>' +
                '<p class="mb-1"><strong>Piso:</strong> ' + escapeHtml(i.piso) + ' <strong>Sala:</strong> ' + escapeHtml(i.sala) + ' <strong>Cama:</strong> ' + escapeHtml(i.cama) + '</p>' +
                '<a href="' + urlView + '" class="p-2 btn btn-success btn-sm mt-2">Atender paciente</a>' +
                '</div></div>';
        });
        html += '</div><div class="card-footer"><a href="{$urlInternacionRonda}" class="btn btn-primary">Ronda de internación</a></div></div>';
        container.innerHTML = html;
    }

    function renderGuardias(data) {
        if (!data.length) {
            container.innerHTML = '<div class="alert alert-secondary"><i class="bi bi-info-circle me-2"></i>No hay ingresos en guardia pendientes.</div>';
            return;
        }
        var html = '<div class="card"><div class="card-header bg-light"><h4 class="mb-0">Pacientes en guardia</h4></div><div class="card-body">';
        data.forEach(function(g) {
            var docLabel = (g.tipo_documento) ? escapeHtml(g.tipo_documento) + ': ' : '';
            html += '<div class="d-flex align-items-center p-3 mb-2 bg-soft-gray rounded">' +
                '<div class="ms-3" style="flex:1;">' +
                '<h5 class="mb-0">' + escapeHtml(g.nombre_completo) + '</h5>' +
                '<p class="mb-1">' + docLabel + escapeHtml(g.documento || '') + '</p>' +
                '</div>' +
                '<span class="btn btn-dark btn-sm">Atender</span>' +
                '</div>';
        });
        html += '</div><div class="card-footer"><a href="{$urlGuardiaIndex}" class="btn btn-success float-end">Ver todos los ingresos activos</a></div></div>';
        container.innerHTML = html;
    }

    function showError(msg) {
        errorEl.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>' + escapeHtml(msg);
        errorEl.classList.remove('d-none');
    }

    function finishOk() {
        loading.classList.add('d-none');
        container.classList.remove('d-none');
    }

    if (!\$) {
        loading.classList.add('d-none');
        showError('No se pudo cargar el listado.');
        return;
    }

    var ajaxOpts = { method: 'GET', dataType: 'json', headers: { 'X-Requested-With': 'XMLHttpRequest' } };

    \$.ajax(\$.extend({}, ajaxOpts, { url: '{$urlAjax}', data: { fecha: fecha } }))
    .done(function(res) {
        if (res.success === false) {
            loading.classList.add('d-none');
            showError(res.message || 'Error al obtener el listado.');
            return;
        }
        var kind = res.kind;
        var data = res.data || [];
        if (kind === 'turnos') {
            renderTurnos(data);
        } else if (kind === 'internados') {
            renderInternados(data);
        } else if (kind === 'guardias') {
            renderGuardias(data);
        } else {
            showError('No hay datos configurados para este tipo de atención.');
            loading.classList.add('d-none');
            return;
        }
        finishOk();
    }).fail(function(xhr) {
        loading.classList.add('d-none');
        showError((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error al cargar pacientes.');
    });
})();
JS
, \yii\web\View::POS_READY);
?>
