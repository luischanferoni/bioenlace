<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\View;

/**
 * Solo lectura de una consulta ya documentada (staff).
 * No es historia clínica: no muestra estado actual del paciente.
 *
 * @var \common\models\Person\Persona|null $persona
 * @var int|null $turnoId
 * @var int|null $encounterId
 * @var string $apiPath
 */

$this->title = $persona
    ? ('Consulta cargada · ' . $persona->getNombreCompleto(\common\models\Person\Persona::FORMATO_NOMBRE_A_N))
    : 'Consulta cargada';

?>

<div class="container-fluid py-3 px-3" id="ver-consulta-root"
     data-api="<?= Html::encode($apiPath) ?>"
     data-url-inicio="<?= Html::encode(Url::to(['/site/index'])) ?>">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="vc-btn-volver">
            <i class="bi bi-arrow-left"></i> Volver
        </button>
        <div class="text-body-secondary small text-truncate" title="<?= Html::encode($this->title) ?>">
            <?= Html::encode($this->title) ?>
        </div>
    </div>

    <div id="vc-loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando…</span>
        </div>
        <p class="mt-2 text-muted mb-0">Cargando consulta documentada…</p>
    </div>

    <div id="vc-error" class="alert alert-warning d-none" role="alert"></div>

    <div id="vc-content" class="d-none">
        <div class="mb-3">
            <h1 class="h4 mb-1" id="vc-persona-nombre"></h1>
            <p class="text-muted small mb-0" id="vc-turno-meta"></p>
        </div>

        <div class="mb-4" id="vc-motivos-wrap" style="display:none;">
            <h2 class="h6 text-primary text-uppercase">Motivos del paciente</h2>
            <div id="vc-motivos" class="text-body" style="white-space:pre-wrap"></div>
        </div>

        <div class="mb-4" id="vc-care-pack-wrap" style="display:none;">
            <h2 class="h6 text-primary text-uppercase">Asistencia pre-consulta</h2>
            <div id="vc-care-pack" class="text-body"></div>
        </div>

        <div class="mb-4" id="vc-doc-wrap" style="display:none;">
            <h2 class="h6 text-primary">Datos cargados</h2>
            <div id="vc-doc" class="text-body"></div>
        </div>

        <p class="text-muted small mb-0" id="vc-empty" style="display:none;">
            Sin datos registrados en esta consulta.
        </p>
    </div>
</div>

<?php
$js = <<<'JS'
(function () {
    var root = document.getElementById('ver-consulta-root');
    if (!root) return;
    var api = root.getAttribute('data-api') || '';
    var urlInicio = root.getAttribute('data-url-inicio') || '/';
    var loading = document.getElementById('vc-loading');
    var errEl = document.getElementById('vc-error');
    var content = document.getElementById('vc-content');

    function bioHeaders() {
        if (typeof window.getBioenlaceApiClientHeaders === 'function') {
            return window.getBioenlaceApiClientHeaders();
        }
        return {};
    }
    function esc(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
    function showError(msg) {
        if (loading) loading.classList.add('d-none');
        if (content) content.classList.add('d-none');
        if (errEl) {
            errEl.textContent = msg || 'No se pudo cargar la consulta.';
            errEl.classList.remove('d-none');
        }
    }
    var btnVolver = document.getElementById('vc-btn-volver');
    if (btnVolver) {
        btnVolver.addEventListener('click', function () {
            if (window.history.length > 1) {
                window.history.back();
                return;
            }
            window.location.href = urlInicio;
        });
    }
    if (!api) {
        showError('Falta turno_id o encounter_id para ver la consulta.');
        return;
    }
    fetch(api, { headers: bioHeaders(), credentials: 'same-origin' })
        .then(function (r) {
            return r.json().then(function (body) {
                return { ok: r.ok, body: body };
            });
        })
        .then(function (res) {
            if (!res.ok || !res.body || res.body.success !== true || !res.body.data) {
                throw new Error((res.body && res.body.message) ? res.body.message : 'Error al cargar la consulta');
            }
            var d = res.body.data;
            var p = d.persona || {};
            var t = d.turno || {};
            var nombreEl = document.getElementById('vc-persona-nombre');
            if (nombreEl) nombreEl.textContent = p.nombre_completo || 'Paciente';
            var turnoEl = document.getElementById('vc-turno-meta');
            if (turnoEl) {
                var tb = [];
                if (t.fecha) tb.push(t.fecha);
                if (t.hora) tb.push(t.hora);
                if (t.estado_label || t.estado) tb.push(t.estado_label || t.estado);
                turnoEl.textContent = tb.length ? ('Turno: ' + tb.join(' · ')) : '';
            }
            var mp = d.motivos_consulta_paciente || {};
            var resumen = (mp.resumen || mp.resumen_ia || '').toString().trim();
            var motivosWrap = document.getElementById('vc-motivos-wrap');
            var motivosEl = document.getElementById('vc-motivos');
            if (resumen && motivosWrap && motivosEl) {
                motivosEl.textContent = resumen;
                motivosWrap.style.display = '';
            }
            var care = d.care_pack_cohorte;
            var careWrap = document.getElementById('vc-care-pack-wrap');
            var careEl = document.getElementById('vc-care-pack');
            if (care && care.assistance && careWrap && careEl) {
                var a = care.assistance;
                var html = '';
                (a.answers || []).forEach(function (ans) {
                    html += '<div class="mb-2"><strong>' + esc(ans.question || '') + '</strong><br />'
                        + esc(ans.answer || '') + '</div>';
                });
                if (a.notes_for_staff) {
                    html += '<p class="mb-0"><em>' + esc(a.notes_for_staff) + '</em></p>';
                }
                if (html) {
                    careEl.innerHTML = html;
                    careWrap.style.display = '';
                }
            }
            var doc = d.documentacion_medico || {};
            var docWrap = document.getElementById('vc-doc-wrap');
            var docEl = document.getElementById('vc-doc');
            var emptyEl = document.getElementById('vc-empty');
            var hasDoc = !!(doc.tiene_datos && doc.secciones && doc.secciones.length);
            if (hasDoc && docWrap && docEl) {
                var dhtml = '';
                doc.secciones.forEach(function (sec) {
                    dhtml += '<div class="mb-3"><div class="fw-semibold">' + esc(sec.titulo || '') + '</div><ul class="mb-0">';
                    (sec.items || []).forEach(function (item) {
                        dhtml += '<li style="white-space:pre-wrap">' + esc(item) + '</li>';
                    });
                    dhtml += '</ul></div>';
                });
                docEl.innerHTML = dhtml;
                docWrap.style.display = '';
            } else if (emptyEl && !resumen) {
                emptyEl.style.display = '';
            }
            if (loading) loading.classList.add('d-none');
            if (content) content.classList.remove('d-none');
        })
        .catch(function (e) {
            showError(e && e.message ? e.message : 'No se pudo cargar la consulta.');
        });
})();
JS;
$this->registerJs($js, View::POS_READY);
