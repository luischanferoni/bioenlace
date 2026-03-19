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

<?= $this->render('_listado_templates') ?>

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
$urlPacienteHistoria = Url::to(['/paciente/historia'], true);
$msgEmptyTurnos = Json::encode('No hay pacientes con turno pendiente de atención en la fecha seleccionada.');
$msgEmptyInternados = Json::encode('No hay pacientes internados para mostrar.');
$msgEmptyGuardias = Json::encode('No hay ingresos en guardia pendientes.');
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

    function bindSpaCardsIfNeeded() {
        if (pacientesSpaEnabled && typeof window.attachSpaCardListeners === 'function') {
            window.attachSpaCardListeners(container);
        }
    }

    function importTemplate(templateId) {
        var tpl = document.getElementById(templateId);
        if (!tpl || !tpl.content) {
            return null;
        }
        return document.importNode(tpl.content, true);
    }

    function clearListadoContent() {
        while (container.firstChild) {
            container.removeChild(container.firstChild);
        }
    }

    function showListadoEmpty(message) {
        clearListadoContent();
        var frag = importTemplate('tpl-pacientes-alert-empty');
        if (!frag) {
            return;
        }
        var msgEl = frag.querySelector('[data-field="message"]');
        if (msgEl) {
            msgEl.textContent = message;
        }
        container.appendChild(frag);
    }

    function fillTurnoCard(colEl, t, idx) {
        var nombre = (t.paciente && t.paciente.nombre_completo) ? t.paciente.nombre_completo : 'Sin paciente';
        var servicio = t.servicio || 'Sin servicio';
        var estadoClass = (t.estado === 'PENDIENTE') ? 'warning' : 'secondary';
        var estadoLabel = t.estado_label || t.estado || '';
        var idPersona = t.id_persona || (t.paciente ? t.paciente.id : null);
        var urlHistoria = idPersona ? ('{$urlPacienteHistoria}' + '?id=' + encodeURIComponent(idPersona)) : null;
        var cardId = 'pac-turno-' + idx + '-' + (idPersona != null ? String(idPersona) : 'x');

        var card = colEl.querySelector('[data-role="turno-card"]');
        if (!card) {
            return;
        }

        colEl.querySelector('[data-field="nombre"]').textContent = nombre;
        colEl.querySelector('[data-field="hora"]').textContent = t.hora || '';
        colEl.querySelector('[data-field="servicio"]').textContent = servicio;

        var badge = colEl.querySelector('[data-field="estado-badge"]');
        badge.className = 'badge bg-' + estadoClass;
        badge.textContent = estadoLabel;

        var obsSlot = colEl.querySelector('[data-slot="observaciones"]');
        if (t.observaciones && obsSlot) {
            obsSlot.classList.remove('d-none');
            var obsText = obsSlot.querySelector('[data-field="observaciones"]');
            if (obsText) {
                obsText.textContent = t.observaciones;
            }
        }

        if (pacientesSpaEnabled && urlHistoria) {
            card.dataset.cardId = cardId;
            card.dataset.actionUrl = urlHistoria;
        } else {
            var stretched = colEl.querySelector('[data-role="stretched-link"]');
            if (urlHistoria) {
                card.addEventListener('click', function() {
                    window.location.href = urlHistoria;
                });
                if (stretched) {
                    stretched.classList.remove('d-none');
                }
            }
        }
    }

    function renderTurnos(data) {
        if (!data.length) {
            showListadoEmpty({$msgEmptyTurnos});
            return;
        }
        clearListadoContent();
        var wrapFrag = importTemplate('tpl-pacientes-turnos-wrap');
        if (!wrapFrag) {
            return;
        }
        var row = wrapFrag.querySelector('[data-role="turnos-grid"]');
        container.appendChild(wrapFrag);

        var tplId = pacientesSpaEnabled ? 'tpl-paciente-turno-spa' : 'tpl-paciente-turno-legacy';
        data.forEach(function(t, idx) {
            var itemFrag = importTemplate(tplId);
            if (!itemFrag) {
                return;
            }
            var col = itemFrag.firstElementChild;
            if (!col) {
                return;
            }
            fillTurnoCard(col, t, idx);
            row.appendChild(col);
        });
        bindSpaCardsIfNeeded();
    }

    function fillInternadoRow(rowEl, i, idx) {
        var urlView = '{$urlInternacionView}' + '?id=' + i.id;
        var urlHistoria = i.id_persona ? ('{$urlPacienteHistoria}' + '?id=' + encodeURIComponent(i.id_persona)) : null;
        var cardId = 'pac-int-' + idx + '-' + i.id;

        rowEl.querySelector('[data-field="nombre"]').textContent = i.nombre || '';
        rowEl.querySelector('[data-field="piso"]').textContent = i.piso || '';
        rowEl.querySelector('[data-field="sala"]').textContent = i.sala || '';
        rowEl.querySelector('[data-field="cama"]').textContent = i.cama || '';

        if (pacientesSpaEnabled) {
            rowEl.dataset.cardId = cardId;
            rowEl.dataset.actionUrl = urlView;
            var aHist = rowEl.querySelector('[data-role="link-historia"]');
            if (urlHistoria && aHist) {
                aHist.href = urlHistoria;
                aHist.classList.remove('d-none');
                aHist.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (window.pacientesSpaEnabled && window.spaNavigateToUrl) {
                        e.preventDefault();
                        window.spaNavigateToUrl(urlHistoria, 'Historia clínica');
                    }
                });
            }
        } else {
            var aAtender = rowEl.querySelector('[data-role="link-atender"]');
            var aHistLeg = rowEl.querySelector('[data-role="link-historia"]');
            if (aAtender) {
                aAtender.href = urlView;
            }
            if (urlHistoria && aHistLeg) {
                aHistLeg.href = urlHistoria;
                aHistLeg.classList.remove('d-none');
            }
        }
    }

    function renderInternados(data) {
        if (!data.length) {
            showListadoEmpty({$msgEmptyInternados});
            return;
        }
        clearListadoContent();
        var wrapFrag = importTemplate('tpl-pacientes-internados-wrap');
        if (!wrapFrag) {
            return;
        }
        var rowsSlot = wrapFrag.querySelector('[data-slot="internados-rows"]');
        container.appendChild(wrapFrag);

        var tplId = pacientesSpaEnabled ? 'tpl-paciente-internado-row-spa' : 'tpl-paciente-internado-row-legacy';
        data.forEach(function(i, idx) {
            var itemFrag = importTemplate(tplId);
            if (!itemFrag) {
                return;
            }
            var row = itemFrag.firstElementChild;
            if (!row) {
                return;
            }
            fillInternadoRow(row, i, idx);
            rowsSlot.appendChild(row);
        });
        bindSpaCardsIfNeeded();
    }

    function fillGuardiaRow(rowEl, g, idx) {
        var urlHistoria = g.id_persona ? ('{$urlPacienteHistoria}' + '?id=' + encodeURIComponent(g.id_persona)) : null;
        var cardId = 'pac-guardia-' + idx + '-' + (g.id_persona || 'x');
        var docLine = (g.tipo_documento ? (g.tipo_documento + ': ') : '') + (g.documento || '');

        rowEl.querySelector('[data-field="nombre"]').textContent = g.nombre_completo || '';
        rowEl.querySelector('[data-field="documento-line"]').textContent = docLine;

        if (pacientesSpaEnabled && urlHistoria) {
            rowEl.dataset.cardId = cardId;
            rowEl.dataset.actionUrl = urlHistoria;
        } else {
            var aAtender = rowEl.querySelector('[data-role="link-atender"]');
            var spanDis = rowEl.querySelector('[data-role="atender-disabled"]');
            if (urlHistoria && aAtender) {
                aAtender.href = urlHistoria;
                aAtender.classList.remove('d-none');
            } else if (spanDis) {
                spanDis.classList.remove('d-none');
            }
        }
    }

    function renderGuardias(data) {
        if (!data.length) {
            showListadoEmpty({$msgEmptyGuardias});
            return;
        }
        clearListadoContent();
        var wrapFrag = importTemplate('tpl-pacientes-guardias-wrap');
        if (!wrapFrag) {
            return;
        }
        var rowsSlot = wrapFrag.querySelector('[data-slot="guardias-rows"]');
        container.appendChild(wrapFrag);

        data.forEach(function(g, idx) {
            var urlHistoria = g.id_persona ? ('{$urlPacienteHistoria}' + '?id=' + encodeURIComponent(g.id_persona)) : null;
            var useSpaTpl = pacientesSpaEnabled && urlHistoria;
            var itemFrag = importTemplate(useSpaTpl ? 'tpl-paciente-guardia-row-spa' : 'tpl-paciente-guardia-row-legacy');
            if (!itemFrag) {
                return;
            }
            var row = itemFrag.firstElementChild;
            if (!row) {
                return;
            }
            fillGuardiaRow(row, g, idx);
            rowsSlot.appendChild(row);
        });
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
