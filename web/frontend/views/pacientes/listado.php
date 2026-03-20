<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;
use common\models\Consulta;
use common\models\Servicio;

$idServicioActual = isset($id_servicio_actual) ? (int) $id_servicio_actual : 0;
$esAmbulatorio = ($encounter_class === Consulta::ENCOUNTER_CLASS_AMB);
$esImpQuirurgico = ($encounter_class === Consulta::ENCOUNTER_CLASS_IMP && $idServicioActual && Servicio::esServicioAgendaQuirurgica($idServicioActual));
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

<?php if ($esAmbulatorio || $esImpQuirurgico): ?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div class="text-muted small">
        <?php if ($esAmbulatorio): ?>
        <strong>Filtrar por fecha del turno:</strong> solo se listan turnos <strong>pendientes y sin atender</strong> en la fecha elegida.
        <?php else: ?>
        <strong>Filtrar por fecha:</strong> cirugías agendadas en el efector para el día indicado.
        <?php endif; ?>
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

<div id="spa-pages-container" class="spa-pages-container"></div>
<?php
$this->registerJsFile('@web/js/spa-navigation.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('@web/js/spa-home.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerCssFile('@web/css/spa.css', ['depends' => [\yii\web\JqueryAsset::class]]);
?>

<?php
$urlInternacionView = Url::to(['internacion/view'], true);
$urlPacienteHistoria = Url::to(['/paciente/historia'], true);
$msgEmptyTurnos = Json::encode('No hay pacientes con turno pendiente de atención en la fecha seleccionada.');
$msgEmptyInternados = Json::encode('No hay pacientes internados para mostrar.');
$msgEmptyGuardias = Json::encode('No hay ingresos en guardia pendientes.');
$msgEmptyCirugias = Json::encode('No hay cirugías agendadas para la fecha seleccionada.');
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

    function bindSpaCards() {
        if (typeof window.attachSpaCardListeners === 'function') {
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

    function historiaConContexto(personaId, ctx) {
        if (!personaId) return null;
        var base = '{$urlPacienteHistoria}';
        var q = 'id=' + encodeURIComponent(personaId);
        if (ctx && typeof ctx === 'object') {
            if (ctx.parent) q += '&parent=' + encodeURIComponent(ctx.parent);
            if (ctx.parent_id != null) q += '&parent_id=' + encodeURIComponent(ctx.parent_id);
        }
        return base + (base.indexOf('?') >= 0 ? '&' : '?') + q;
    }

    function fillTurnoCard(colEl, t, idx) {
        var nombre = (t.paciente && t.paciente.nombre_completo) ? t.paciente.nombre_completo : 'Sin paciente';
        var servicio = t.servicio || 'Sin servicio';
        var estadoClass = (t.estado === 'PENDIENTE') ? 'warning' : 'secondary';
        var estadoLabel = t.estado_label || t.estado || '';
        var idPersona = t.id_persona || (t.paciente ? t.paciente.id : null);
        var urlHistoria = historiaConContexto(idPersona, { parent: 'TURNO', parent_id: t.id });
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

        if (urlHistoria) {
            card.classList.add('spa-card');
            card.setAttribute('data-expandable', 'false');
            card.setAttribute('data-full-page', 'true');
            card.setAttribute('data-action-type', 'default');
            card.dataset.cardId = cardId;
            card.dataset.actionUrl = urlHistoria;
        } else {
            card.classList.remove('spa-card');
            card.removeAttribute('data-expandable');
            card.removeAttribute('data-full-page');
            card.removeAttribute('data-action-type');
            card.removeAttribute('data-card-id');
            card.removeAttribute('data-action-url');
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

        data.forEach(function(t, idx) {
            var itemFrag = importTemplate('tpl-paciente-turno');
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
        bindSpaCards();
    }

    function fillInternadoRow(rowEl, i, idx) {
        var urlView = '{$urlInternacionView}' + '?id=' + i.id;
        var urlHistoria = historiaConContexto(i.id_persona, { parent: 'INTERNACION', parent_id: i.id });
        var cardId = 'pac-int-' + idx + '-' + i.id;

        rowEl.querySelector('[data-field="nombre"]').textContent = i.nombre || '';
        rowEl.querySelector('[data-field="piso"]').textContent = i.piso || '';
        rowEl.querySelector('[data-field="sala"]').textContent = i.sala || '';
        rowEl.querySelector('[data-field="cama"]').textContent = i.cama || '';

        rowEl.dataset.cardId = cardId;
        rowEl.dataset.actionUrl = urlView;
        var aHist = rowEl.querySelector('[data-role="link-historia"]');
        if (urlHistoria && aHist) {
            aHist.href = urlHistoria;
            aHist.classList.remove('d-none');
            aHist.addEventListener('click', function(e) {
                e.stopPropagation();
                if (window.spaNavigateToUrl) {
                    e.preventDefault();
                    window.spaNavigateToUrl(urlHistoria, 'Historia clínica');
                }
            });
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

        data.forEach(function(i, idx) {
            var itemFrag = importTemplate('tpl-paciente-internado-row');
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
        bindSpaCards();
    }

    function fillCirugiaCard(colEl, c, idx) {
        var nombre = (c.paciente && c.paciente.nombre_completo) ? c.paciente.nombre_completo : 'Sin paciente';
        var idPersona = c.id_persona || (c.paciente ? c.paciente.id : null);
        var urlHistoria = historiaConContexto(idPersona, { parent: 'CIRUGIA', parent_id: c.id });
        var estadoClass = (c.estado === 'REALIZADA' || c.estado === 'CANCELADA') ? 'secondary' : 'warning';
        var estadoLabel = c.estado_label || c.estado || '';
        var cardId = 'pac-cirugia-' + idx + '-' + (c.id != null ? String(c.id) : 'x');

        var card = colEl.querySelector('[data-role="cirugia-card"]');
        if (!card) {
            return;
        }

        colEl.querySelector('[data-field="nombre"]').textContent = nombre;
        colEl.querySelector('[data-field="sala"]').textContent = c.sala_nombre || '—';
        colEl.querySelector('[data-field="inicio"]').textContent = c.fecha_hora_inicio || '';

        var badge = colEl.querySelector('[data-field="estado-badge"]');
        badge.className = 'badge bg-' + estadoClass;
        badge.textContent = estadoLabel;

        if (urlHistoria) {
            card.classList.add('spa-card');
            card.setAttribute('data-expandable', 'false');
            card.setAttribute('data-full-page', 'true');
            card.setAttribute('data-action-type', 'default');
            card.dataset.cardId = cardId;
            card.dataset.actionUrl = urlHistoria;
        } else {
            card.classList.remove('spa-card');
            card.removeAttribute('data-expandable');
            card.removeAttribute('data-full-page');
            card.removeAttribute('data-action-type');
            card.removeAttribute('data-card-id');
            card.removeAttribute('data-action-url');
        }
    }

    function renderCirugias(data) {
        if (!data.length) {
            showListadoEmpty({$msgEmptyCirugias});
            return;
        }
        clearListadoContent();
        var wrapFrag = importTemplate('tpl-pacientes-cirugias-wrap');
        if (!wrapFrag) {
            return;
        }
        var row = wrapFrag.querySelector('[data-role="cirugias-grid"]');
        container.appendChild(wrapFrag);

        data.forEach(function(c, idx) {
            var itemFrag = importTemplate('tpl-paciente-cirugia');
            if (!itemFrag) {
                return;
            }
            var col = itemFrag.firstElementChild;
            if (!col) {
                return;
            }
            fillCirugiaCard(col, c, idx);
            row.appendChild(col);
        });
        bindSpaCards();
    }

    function fillGuardiaRow(rowEl, g, idx) {
        var urlHistoria = historiaConContexto(g.id_persona, { parent: 'GUARDIA', parent_id: g.id });
        var cardId = 'pac-guardia-' + idx + '-' + (g.id_persona || 'x');
        var docLine = (g.tipo_documento ? (g.tipo_documento + ': ') : '') + (g.documento || '');

        rowEl.querySelector('[data-field="nombre"]').textContent = g.nombre_completo || '';
        rowEl.querySelector('[data-field="documento-line"]').textContent = docLine;

        var cta = rowEl.querySelector('[data-role="cta-atender"]');
        if (urlHistoria) {
            rowEl.classList.add('spa-card');
            rowEl.setAttribute('data-expandable', 'false');
            rowEl.setAttribute('data-full-page', 'true');
            rowEl.setAttribute('data-action-type', 'default');
            rowEl.dataset.cardId = cardId;
            rowEl.dataset.actionUrl = urlHistoria;
            if (cta) {
                cta.classList.remove('disabled');
                cta.innerHTML = '<i class="bi bi-chevron-right"></i> Atender';
            }
        } else {
            rowEl.classList.remove('spa-card');
            rowEl.removeAttribute('data-expandable');
            rowEl.removeAttribute('data-full-page');
            rowEl.removeAttribute('data-action-type');
            rowEl.removeAttribute('data-card-id');
            rowEl.removeAttribute('data-action-url');
            if (cta) {
                cta.classList.add('disabled');
                cta.innerHTML = 'Atender';
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
            var itemFrag = importTemplate('tpl-paciente-guardia-row');
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
        bindSpaCards();
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
        } else if (kind === 'cirugias') {
            renderCirugias(data);
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
