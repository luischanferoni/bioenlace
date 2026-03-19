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
        'label' => 'Ambulatorio',
    ],
    Consulta::ENCOUNTER_CLASS_IMP => [
        'label' => 'Internación',
    ],
    Consulta::ENCOUNTER_CLASS_EMER => [
        'label' => 'Guardia',
    ],
];
$metaEc = ($encounter_class && isset($encounterMeta[$encounter_class]))
    ? $encounterMeta[$encounter_class]
    : ['label' => ''];

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
    <?php endif; ?>
</div>

<?php if ($esAmbulatorio): ?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div class="text-muted small">
        <strong>Filtrar por fecha del turno:</strong> solo se listan turnos <strong>pendientes y sin atender</strong> en la fecha elegida.
    </div>
    <div class="btn-group" role="group">
        <a href="<?= Url::to(['site/pacientes', 'fecha' => $fechaAnterior]) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-left"></i> Fecha anterior
        </a>
        <a href="<?= Url::to(['site/pacientes', 'fecha' => $hoy]) ?>" class="btn btn-outline-secondary btn-sm">
            Fecha de hoy
        </a>
        <a href="<?= Url::to(['site/pacientes', 'fecha' => $fechaSiguiente]) ?>" class="btn btn-outline-secondary btn-sm">
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
$esHoyJs = $esHoy ? 'true' : 'false';
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
$urlPacienteHistoria = Url::to(['/paciente/historia'], true);
$this->registerJs(<<<JS
(function() {
    var \$ = window.jQuery;
    var container = document.getElementById('pacientes-listado-content');
    var loading = document.getElementById('pacientes-listado-loading');
    var errorEl = document.getElementById('pacientes-listado-error');
    var fecha = '{$fecha}';
    var encounter = {$encounterJson};
    /** SPA solo cuando la vista carga spa-navigation + spa-home (fecha = hoy) */
    window.pacientesSpaEnabled = {$esHoyJs};
    var pacientesSpaEnabled = window.pacientesSpaEnabled;

    function escapeHtml(s) {
        if (s == null) return '';
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    function escapeAttr(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function bindSpaCardsIfNeeded() {
        if (pacientesSpaEnabled && typeof window.attachSpaCardListeners === 'function') {
            window.attachSpaCardListeners(container);
        }
    }

    function renderTurnos(data) {
        if (!data.length) {
            container.innerHTML = '<div class="alert alert-secondary"><i class="bi bi-info-circle me-2"></i>No hay pacientes con turno pendiente de atención en la fecha seleccionada.</div>';
            return;
        }
        var html = '<div class="row">';
        data.forEach(function(t, idx) {
            var nombre = (t.paciente && t.paciente.nombre_completo) ? t.paciente.nombre_completo : 'Sin paciente';
            var servicio = t.servicio || 'Sin servicio';
            var estadoClass = (t.estado === 'PENDIENTE') ? 'warning' : 'secondary';
            var estadoLabel = t.estado_label || t.estado || '';
            var obs = (t.observaciones) ? '<div class="mb-2"><strong><i class="bi bi-chat-left-text me-2"></i>Observaciones:</strong> <small class="text-muted">' + escapeHtml(t.observaciones) + '</small></div>' : '';
            var idPersona = t.id_persona || (t.paciente ? t.paciente.id : null);
            var urlHistoria = idPersona ? ('{$urlPacienteHistoria}' + '?id=' + encodeURIComponent(idPersona)) : null;
            var cardId = 'pac-turno-' + idx + '-' + (idPersona != null ? String(idPersona) : 'x');

            if (pacientesSpaEnabled && urlHistoria) {
                html += '<div class="col-md-6 col-lg-4 mb-3">' +
                    '<div class="card h-100 shadow-sm position-relative spa-card"' +
                    ' data-card-id="' + escapeAttr(cardId) + '"' +
                    ' data-expandable="false" data-full-page="true" data-action-type="default"' +
                    ' data-action-url="' + escapeAttr(urlHistoria) + '">' +
                    '<div class="card-body">' +
                    '<h5 class="card-title"><i class="bi bi-person-circle text-primary me-2"></i>' + escapeHtml(nombre) + '</h5>' +
                    '<div class="mb-2"><strong><i class="bi bi-clock me-2"></i>Turno:</strong> ' + escapeHtml(t.hora) + '</div>' +
                    '<div class="mb-2"><strong><i class="bi bi-hospital me-2"></i>Servicio:</strong> ' + escapeHtml(servicio) + '</div>' + obs +
                    '<div class="mt-3"><span class="badge bg-' + estadoClass + '">' + escapeHtml(estadoLabel) + '</span></div>' +
                    '</div></div></div>';
            } else {
                html += '<div class="col-md-6 col-lg-4 mb-3">' +
                    '<div class="card h-100 shadow-sm position-relative" style="cursor:pointer;"' + (urlHistoria ? (' onclick="window.location.href=\\'' + urlHistoria + '\\'"') : '') + '>' +
                    '<div class="card-body">' +
                    '<h5 class="card-title"><i class="bi bi-person-circle text-primary me-2"></i>' + escapeHtml(nombre) + '</h5>' +
                    '<div class="mb-2"><strong><i class="bi bi-clock me-2"></i>Turno:</strong> ' + escapeHtml(t.hora) + '</div>' +
                    '<div class="mb-2"><strong><i class="bi bi-hospital me-2"></i>Servicio:</strong> ' + escapeHtml(servicio) + '</div>' + obs +
                    '<div class="mt-3"><span class="badge bg-' + estadoClass + '">' + escapeHtml(estadoLabel) + '</span></div>' +
                    (urlHistoria ? '<span class="stretched-link" aria-label="Abrir historia clínica"></span>' : '') +
                    '</div></div></div>';
            }
        });
        html += '</div>';
        container.innerHTML = html;
        bindSpaCardsIfNeeded();
    }

    function renderInternados(data) {
        if (!data.length) {
            container.innerHTML = '<div class="alert alert-secondary"><i class="bi bi-info-circle me-2"></i>No hay pacientes internados para mostrar.</div>';
            return;
        }
        var html = '<div class="card"><div class="card-header"><h4 class="mb-0">Pacientes internados</h4></div><div class="card-body">';
        data.forEach(function(i, idx) {
            var urlView = '{$urlInternacionView}' + '?id=' + i.id;
            var urlHistoria = i.id_persona ? ('{$urlPacienteHistoria}' + '?id=' + encodeURIComponent(i.id_persona)) : null;
            var cardId = 'pac-int-' + idx + '-' + i.id;

            if (pacientesSpaEnabled) {
                html += '<div class="d-flex align-items-center p-3 mb-2 bg-soft-gray rounded spa-card"' +
                    ' data-card-id="' + escapeAttr(cardId) + '"' +
                    ' data-expandable="false" data-full-page="true" data-action-type="default"' +
                    ' data-action-url="' + escapeAttr(urlView) + '">' +
                    '<div class="ms-3" style="flex:1;">' +
                    '<h5 class="card-title mb-0">' + escapeHtml(i.nombre) + '</h5>' +
                    '<p class="mb-1"><strong>Piso:</strong> ' + escapeHtml(i.piso) + ' <strong>Sala:</strong> ' + escapeHtml(i.sala) + ' <strong>Cama:</strong> ' + escapeHtml(i.cama) + '</p>' +
                    '<div class="d-flex flex-wrap gap-2 mt-2">' +
                    '<span class="p-2 btn btn-success btn-sm">Atender paciente</span>' +
                    (urlHistoria ? '<a href="' + escapeAttr(urlHistoria) + '" class="p-2 btn btn-outline-primary btn-sm" data-spa-no-card="1" onclick="event.stopPropagation(); if(window.pacientesSpaEnabled && window.spaNavigateToUrl){event.preventDefault();window.spaNavigateToUrl(this.href,\'Historia clínica\');}">Historia clínica</a>' : '') +
                    '</div>' +
                    '</div></div>';
            } else {
                html += '<div class="d-flex align-items-center p-3 mb-2 bg-soft-gray rounded">' +
                    '<div class="ms-3" style="flex:1;">' +
                    '<h5 class="mb-0">' + escapeHtml(i.nombre) + '</h5>' +
                    '<p class="mb-1"><strong>Piso:</strong> ' + escapeHtml(i.piso) + ' <strong>Sala:</strong> ' + escapeHtml(i.sala) + ' <strong>Cama:</strong> ' + escapeHtml(i.cama) + '</p>' +
                    '<div class="d-flex flex-wrap gap-2 mt-2">' +
                    '<a href="' + escapeAttr(urlView) + '" class="p-2 btn btn-success btn-sm">Atender paciente</a>' +
                    (urlHistoria ? '<a href="' + escapeAttr(urlHistoria) + '" class="p-2 btn btn-outline-primary btn-sm">Historia clínica</a>' : '') +
                    '</div>' +
                    '</div></div>';
            }
        });
        html += '</div><div class="card-footer"><a href="{$urlInternacionRonda}" class="btn btn-primary">Ronda de internación</a></div></div>';
        container.innerHTML = html;
        bindSpaCardsIfNeeded();
    }

    function renderGuardias(data) {
        if (!data.length) {
            container.innerHTML = '<div class="alert alert-secondary"><i class="bi bi-info-circle me-2"></i>No hay ingresos en guardia pendientes.</div>';
            return;
        }
        var html = '<div class="card"><div class="card-header bg-light"><h4 class="mb-0">Pacientes en guardia</h4></div><div class="card-body">';
        data.forEach(function(g, idx) {
            var docLabel = (g.tipo_documento) ? escapeHtml(g.tipo_documento) + ': ' : '';
            var urlHistoria = g.id_persona ? ('{$urlPacienteHistoria}' + '?id=' + encodeURIComponent(g.id_persona)) : null;
            var cardId = 'pac-guardia-' + idx + '-' + (g.id_persona || 'x');

            if (pacientesSpaEnabled && urlHistoria) {
                html += '<div class="d-flex align-items-center justify-content-between p-3 mb-2 bg-soft-gray rounded spa-card"' +
                    ' data-card-id="' + escapeAttr(cardId) + '"' +
                    ' data-expandable="false" data-full-page="true" data-action-type="default"' +
                    ' data-action-url="' + escapeAttr(urlHistoria) + '">' +
                    '<div class="ms-3" style="flex:1;">' +
                    '<h5 class="card-title mb-0">' + escapeHtml(g.nombre_completo) + '</h5>' +
                    '<p class="mb-1">' + docLabel + escapeHtml(g.documento || '') + '</p>' +
                    '</div>' +
                    '<span class="btn btn-dark btn-sm me-2"><i class="bi bi-chevron-right"></i> Atender</span>' +
                    '</div>';
            } else {
                html += '<div class="d-flex align-items-center p-3 mb-2 bg-soft-gray rounded">' +
                    '<div class="ms-3" style="flex:1;">' +
                    '<h5 class="mb-0">' + escapeHtml(g.nombre_completo) + '</h5>' +
                    '<p class="mb-1">' + docLabel + escapeHtml(g.documento || '') + '</p>' +
                    '</div>' +
                    (urlHistoria ? '<a href="' + escapeAttr(urlHistoria) + '" class="btn btn-dark btn-sm">Atender</a>' : '<span class="btn btn-dark btn-sm disabled">Atender</span>') +
                    '</div>';
            }
        });
        html += '</div><div class="card-footer"><a href="{$urlGuardiaIndex}" class="btn btn-success float-end">Ver todos los ingresos activos</a></div></div>';
        container.innerHTML = html;
        bindSpaCardsIfNeeded();
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

    var headers = { 'X-Requested-With': 'XMLHttpRequest' };
    if (typeof window.getBioenlaceApiClientHeaders === 'function') {
        headers = window.getBioenlaceApiClientHeaders(headers);
    } else {
        headers['X-App-Client'] = 'web-frontend';
        headers['X-App-Version'] = (window.spaConfig && window.spaConfig.appVersion) ? String(window.spaConfig.appVersion) : '1.0.0';
    }
    if (window.apiAuthToken) {
        headers['Authorization'] = 'Bearer ' + window.apiAuthToken;
    }
    var ajaxOpts = { method: 'GET', dataType: 'json', headers: headers };

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
