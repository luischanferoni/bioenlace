<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\bootstrap5\Modal;

use common\models\Person\Persona;
use common\helpers\TimelineHelper;
use common\models\User;
use yii\web\View;



$tieneRolEnfermeria = User::hasRole(['enfermeria']) ? true : false;
// Barrio del paciente (domicilio activo)
$barrioNombre = null;
if (is_object($persona) && is_object($persona->domicilioActivo)) {
    if (is_object($persona->domicilioActivo->modelBarrio)) {
        $barrioNombre = $persona->domicilioActivo->modelBarrio->nombre;
    } elseif (!empty($persona->domicilioActivo->barrio)) {
        // Fallback: el campo guarda el id del barrio; si no hay relación, mostramos el valor crudo.
        $barrioNombre = $persona->domicilioActivo->barrio;
    }
}

$barrioTexto = !empty($barrioNombre) ? $barrioNombre : 'Sin datos';
$this->title = $persona->nombre . ' ' . $persona->otro_nombre . ', ' . $persona->apellido . ' | ' . $persona->edad . ' años - Barrio: ' . $barrioTexto;

// Los archivos JS (turnos.js, chat-inteligente.js, timeline.js) se cargan automáticamente desde AppAsset
// Solo registrar Plotly si es necesario para gráficos
$this->registerJsFile(
    "https://cdn.plot.ly/plotly-2.27.1.min.js",
    [
        'position' => View::POS_HEAD,
        'charset' => 'utf-8'
    ]
);

?>

<!-- Primera fila: Datos del paciente (compacta) -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card border-2 mb-1">
            <div class="card-body p-4 pb-1">
                <div class="row">
                    
                    <!-- Columna derecha: Información médica -->
                    <div class="col-12 ms-3">
                        <h6 class="mb-2 text-primary"><b>ESTADO ACTUAL DEL PACIENTE</b></h6>
                        
                        <!-- Última Vacuna -->
                        <!--<div class="mb-2">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <h6 class="mb-0">ÚLTIMA VACUNA</h6>
                                <span id="vacunas-link" style="display: none;">
                                    <a href="#" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-vacunas">
                                        <i class="bi bi-eye"></i> Ver todas
                                    </a>
                                </span>
                            </div>
                            <div class="border-bottom border-2"></div>
                            <div id="ultima-vacuna-content">
                                <div class="text-center py-2">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                    <span class="ms-2 text-muted">Cargando vacunas...</span>
                                </div>
                            </div>
                        </div>-->
                        
                        <div class="row">
                            <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
                                <h6 class="mb-1 text-decoration-underline">DIAGNÓSTICOS RECIENTES</h6>
                                <p class="mb-2" id="tl_condiciones_activas"><span class="text-muted">Cargando...</span></p>
                            </div>
                            <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
                                <h6 class="mb-1 text-decoration-underline">DIAGNÓSTICOS CRÓNICOS</h6>
                                <p class="mb-2" id="tl_condiciones_cronicas"><span class="text-muted">Cargando...</span></p>
                            </div>
                            <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
                                <h6 class="mb-1 text-decoration-underline">ALERGIAS</h6>
                                <p class="mb-2" id="tl_hallazgos"><span class="text-muted">Cargando...</span></p>
                            </div>
                            <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
                                <h6 class="mb-1 text-decoration-underline">ANTECEDENTES</h6>
                                <p class="mb-2" id="tl_antecedentes"><span class="text-muted">Cargando...</span></p>
                            </div>
                        </div>

                        <div class="mb-3 pb-2 border-bottom border-2">
                            <h6 class="mb-2 text-primary"><b>MOTIVOS DE ESTA CONSULTA</b></h6>
                            <p class="mb-0 text-muted" id="tl_motivos_consulta">Cargando...</p>
                            <div id="tl_motivos_consulta_mensajes" class="mt-2"></div>
                        </div>

                        <div class="mb-3 pb-2 border-bottom border-2" id="tl_care_pack_section" style="display:none;">
                            <h6 class="mb-2 text-primary"><b>ASISTENCIA PRE-CONSULTA (COHORTE)</b></h6>
                            <div id="tl_care_pack_cohorte" class="text-body"></div>
                        </div>

                        <!-- Signos Vitales Actuales -->
                        <div class="mb-2">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <h6 class="mb-0" id="signos-vitales-titulo">SIGNOS VITALES ACTUALES</h6>
                                <span id="signos-vitales-link" style="display: none;">
                                    <a href="#" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-signos-vitales">
                                        <i class="bi bi-eye"></i> Ver todos
                                    </a>
                                </span>
                            </div>
                            <div class="border-bottom border-2 mb-1"></div>
                            <div id="signos-vitales-actuales-content">
                                <div class="text-center py-2">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                    <span class="ms-2 text-muted">Cargando signos vitales...</span>
                                </div>
                            </div>
                        </div>                        
                    </div>

                    <!-- Contenido automático con loading -->
                    <div class="col-12 ms-3 border-bottom border-2">
                        <!-- Loading inicial -->
                        <div id="loading-container" class="d-flex justify-content-center align-items-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <span class="ms-2">Cargando información...</span>
                        </div>
                        
                        <!-- Contenedores para el contenido -->
                        <?php if ($persona->edad < 14) : ?>
                            <div id="curvas-crecimiento-content" class="mb-3" style="display: none;"></div>
                        <?php endif; ?>
                    </div>

                    <!-- Formulario de chat inteligente -->
                    <div class="col-12">
                        <div class="card border-0">
                            <div class="card-body p-3">

                                <!-- Contenedor para mensajes y formulario (se carga dinámicamente) -->
                                <div id="formulario-container">
                                    <!-- Los mensajes y formulario se cargarán aquí via AJAX -->
                                </div>
                            </div>
                        </div>
                    </div>                    
                </div>
            </div>
        </div>
    </div>
</div>

<?php

Modal::begin([
    'title' => '<h4 id="modal-title"></h4>',
    'id' => 'modal-general',
    'size' => 'modal-xl',
]);
echo "<div id='modal-content'></div>";
Modal::end();
/*$modal = '';

if($referencia['tipo_solicitud'] == 'INTERCONSULTA'):
    Modal::begin([
        'title' => '<h4 id="modal-title">Referencia</h4>',
        'id' => 'modal-referencia',
        'size' => 'modal-lg'
        #'centerVertical' => true
    ]);
    echo "<div id='modal-content-referencia' style='border: 2px solid #1c8b37; background-color:aliceblue;padding:25px'>".$referencia['dato']."</div>";
    echo "<div class='modal-footer'>
            <button type='button' class='btn btn-warning' data-bs-dismiss='modal'>Cerrar</button>
          </div>";
    Modal::end();
    $modal = '$("#modal-referencia").modal("show"); $("#modla-referencia  .modal-dialog .modal-content").css({ "--bs-modal-border-color": "blue" });';
endif;*/
?>



<template id="loader_template">
    <div class="iq-loader-box">
        <div class="iq-loader-8"></div>
    </div>
</template>

<?php
Modal::begin([
    'title' => 'Historial de Vacunas',
    'id' => 'modal-vacunas',
    'size' => Modal::SIZE_EXTRA_LARGE,
]);
echo "<div id='modal-vacunas-content'></div>";
Modal::end();

Modal::begin([
    'title' => 'Historial de Signos Vitales',
    'id' => 'modal-signos-vitales',
    'size' => Modal::SIZE_EXTRA_LARGE,
]);
echo "<div id='modal-signos-vitales-content'></div>";
Modal::end();
?>

<script>
(function() {
    'use strict';
    
    // Configuración para el timeline (usar var para permitir redeclaración en SPA)
    var timelineConfig = {
        pacienteId: <?= $persona->id_persona ?>,
        endpoints: {
            curvasCrecimiento: <?= $persona->edad < 14 ? "'" . \yii\helpers\Url::to(['personas/curvas-crecimiento', 'id' => $persona->id_persona]) . "'" : 'null' ?>,
            //vacunas: '<?= \yii\helpers\Url::to(['personas/vacunas', 'dni' => $persona->documento, 'sexo' => $persona->sexo_biologico]) ?>',
            formularioConsulta: '<?= Url::to(['paciente/formulario-consulta', 'id' => $persona->id_persona]) ?>',
            historiaClinica: '/api/v1/personas/<?= (int) $persona->id_persona ?>/historia-clinica'
        }
    };

    function bioHeaders() {
        if (typeof window.getBioenlaceApiClientHeaders === "function") {
            return window.getBioenlaceApiClientHeaders();
        }
        return {};
    }

    function renderBadges(containerId, items, badgeClass) {
        var el = document.getElementById(containerId);
        if (!el) return;
        if (!items || !items.length) {
            el.innerHTML = '<span class="ms-2">Sin datos</span>';
            return;
        }
        el.innerHTML = items
            .filter(function (x) { return x && x.termino; })
            .map(function (x) { return '<span class="badge ' + badgeClass + ' me-1">' + String(x.termino).toUpperCase() + '</span>'; })
            .join('');
    }

    function renderMotivos(texto, mp) {
        var el = document.getElementById('tl_motivos_consulta');
        if (!el) return;
        mp = mp || {};
        var resumen = (mp.resumen && String(mp.resumen).trim() !== '')
            ? String(mp.resumen).trim()
            : ((mp.resumen_ia && String(mp.resumen_ia).trim() !== '')
                ? String(mp.resumen_ia).trim()
                : (texto && String(texto).trim() !== '' ? String(texto).trim() : ''));
        var imgsByRef = {};
        (mp.imagenes_adjuntas || []).forEach(function (img) {
            if (img && img.ref) imgsByRef[img.ref] = img.url || '';
        });
        var html = '';
        if (resumen !== '') {
            html += '<div class="mb-2"><span class="small text-uppercase text-muted">Resumen</span></div>';
            html += '<div class="text-body tl-motivos-resumen" style="white-space:pre-wrap">';
            var parts = resumen.split(/(\[imagen\d+\])/g);
            parts.forEach(function (part) {
                var m = part.match(/^\[(imagen\d+)\]$/);
                if (m && imgsByRef[m[1]]) {
                    html += '<div class="my-2"><img class="tl-motivos-secure-media" data-secure-src="' + escMotivosHtml(imgsByRef[m[1]]) + '" alt="' + escMotivosHtml(m[1]) + '" style="max-width:100%;max-height:220px;border-radius:6px" /></div>';
                } else if (part) {
                    html += escMotivosHtml(part);
                }
            });
            html += '</div>';
        } else if (mp.resumen_pendiente || mp.resumen_ia_pendiente) {
            html += '<p class="text-muted mb-0">Generando resumen…</p>';
        } else {
            html += '<p class="text-muted mb-0">Sin motivos registrados para esta consulta.</p>';
        }
        var sug = mp.sugerencias_clinicas;
        if (sug && (sug.diagnosticos_sugeridos || sug.practicas_sugeridas)) {
            html += '<div class="mt-3 small text-uppercase text-muted">Orientación preliminar</div>';
            if (sug.diagnosticos_sugeridos && sug.diagnosticos_sugeridos.length) {
                html += '<div class="fw-semibold mt-2">Diagnósticos a considerar</div><ul class="mb-1">';
                sug.diagnosticos_sugeridos.forEach(function (d) {
                    html += '<li>' + escMotivosHtml(d.termino || '') + '</li>';
                });
                html += '</ul>';
            }
            if (sug.practicas_sugeridas && sug.practicas_sugeridas.length) {
                html += '<div class="fw-semibold mt-2">Prácticas / estudios</div><ul class="mb-0">';
                sug.practicas_sugeridas.forEach(function (p) {
                    html += '<li>' + escMotivosHtml(p.termino || '') + '</li>';
                });
                html += '</ul>';
            }
        }
        el.innerHTML = html;
        hydrateSecureTimelineMedia(el);
    }

    function escMotivosHtml(s) {
        if (s == null) return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function renderCarePackCohorte(cohorte) {
        var section = document.getElementById('tl_care_pack_section');
        var el = document.getElementById('tl_care_pack_cohorte');
        if (!section || !el) return;
        if (!cohorte || typeof cohorte !== 'object') {
            section.style.display = 'none';
            el.innerHTML = '';
            return;
        }
        var assistance = cohorte.assistance || {};
        var answers = assistance.answers || [];
        var notes = assistance.notes_for_staff ? String(assistance.notes_for_staff).trim() : '';
        if (!notes && (!answers || !answers.length)) {
            section.style.display = 'none';
            el.innerHTML = '';
            return;
        }
        section.style.display = '';
        var html = '';
        if (cohorte.cohort_key_short) {
            html += '<div class="small text-muted mb-2">Cohorte ' + escMotivosHtml(cohorte.cohort_key_short) + '</div>';
        }
        var profile = cohorte.cohort_profile || {};
        var profileParts = ['life_stage', 'sexo', 'motive_cluster', 'jurisdiction']
            .map(function (k) { return profile[k] ? String(profile[k]) : ''; })
            .filter(function (v) { return v !== ''; });
        if (profileParts.length) {
            html += '<div class="mb-2">' + escMotivosHtml(profileParts.join(' · ')) + '</div>';
        }
        if (notes !== '') {
            html += '<div class="small text-uppercase text-muted">Orientación</div>';
            html += '<p class="mb-2" style="white-space:pre-wrap">' + escMotivosHtml(notes) + '</p>';
        }
        if (answers.length) {
            html += '<div class="small text-uppercase text-muted">Respuestas del paciente</div><ul class="mb-0">';
            answers.forEach(function (a) {
                html += '<li class="mb-2"><strong>' + escMotivosHtml(a.question || a.id || '') + '</strong><br />'
                    + escMotivosHtml(a.answer || '') + '</li>';
            });
            html += '</ul>';
        } else if (assistance.status === 'submitted') {
            html += '<p class="text-muted mb-0">Respuestas registradas.</p>';
        } else {
            html += '<p class="text-muted mb-0">El paciente aún no completó el cuestionario.</p>';
        }
        if (assistance.delta_requested) {
            html += '<p class="mt-2 mb-0"><span class="badge bg-warning text-dark">Requiere adaptación del pack</span></p>';
        }
        el.innerHTML = html;
    }

    function renderMotivosPacienteApp(mp) {
        var box = document.getElementById('tl_motivos_consulta_mensajes');
        if (!box) return;
        var msgs = (mp && mp.messages) ? mp.messages : [];
        if (!msgs.length) {
            box.innerHTML = '';
            return;
        }
        var html = '<div class="border rounded p-2 bg-light"><div class="small text-uppercase text-primary fw-bold mb-2">Mensajes del paciente (app)</div><ul class="list-unstyled mb-0">';
        for (var i = 0; i < msgs.length; i++) {
            var m = msgs[i];
            var meta = escMotivosHtml(m.created_at || '');
            var body = '';
            var t = m.message_type || 'texto';
            if (t === 'texto') {
                body = '<span style="white-space:pre-wrap">' + escMotivosHtml(m.content || '') + '</span>';
            } else if (t === 'imagen') {
                var u = m.content || '';
                body = '<img class="tl-motivos-secure-media" data-secure-src="' + escMotivosHtml(u) + '" alt="Imagen adjunta" style="max-width:100%;max-height:220px;border-radius:6px" />';
            } else if (t === 'audio') {
                var au = m.content || '';
                body = '<audio class="tl-motivos-secure-media" controls preload="none" data-secure-src="' + escMotivosHtml(au) + '" style="max-width:100%"></audio>';
            } else {
                body = escMotivosHtml(m.content || '');
            }
            html += '<li class="mb-2 pb-2 border-bottom border-light"><div class="small text-muted">' + meta + '</div>' + body + '</li>';
        }
        html += '</ul></div>';
        box.innerHTML = html;
        hydrateSecureTimelineMedia(box);
    }

    function hydrateSecureTimelineMedia(root) {
        if (!root || typeof fetch !== 'function') return;
        var nodes = root.querySelectorAll('.tl-motivos-secure-media[data-secure-src]');
        for (var i = 0; i < nodes.length; i++) {
            (function (el) {
                var url = el.getAttribute('data-secure-src');
                if (!url) return;
                fetch(url, { headers: bioHeaders(), credentials: 'same-origin' })
                    .then(function (r) {
                        if (!r.ok) throw new Error('media');
                        return r.blob();
                    })
                    .then(function (blob) {
                        el.src = URL.createObjectURL(blob);
                        if (el.tagName === 'IMG') {
                            el.style.cursor = 'pointer';
                            el.addEventListener('click', function () {
                                window.open(el.src, '_blank', 'noopener');
                            });
                        }
                    })
                    .catch(function () {
                        if (el.tagName === 'IMG') {
                            el.alt = 'No se pudo cargar la imagen';
                        }
                    });
            })(nodes[i]);
        }
    }

    async function loadTimelineSummary() {
        try {
            var endpoints = timelineConfig.endpoints || {};
            if (!endpoints.historiaClinica) return;
            var resp = await fetch(endpoints.historiaClinica, { headers: bioHeaders() });
            var payload = await resp.json();
            if (resp.status === 403 && payload && payload.errors && payload.errors.codigo === 'HC_ANTES_DE_VENTANA') {
                throw new Error(payload.message || 'Historia clínica no disponible aún.');
            }
            if (!payload || payload.success !== true || !payload.data) {
                throw new Error((payload && payload.message) ? payload.message : 'Error al cargar historia clínica');
            }
            var info = payload.data.informacion_medica || {};
            renderBadges('tl_condiciones_activas', info.condiciones_activas || [], 'border border-info text-info');
            renderBadges('tl_condiciones_cronicas', info.condiciones_cronicas || [], 'border border-warning text-warning');
            renderBadges('tl_hallazgos', info.hallazgos || [], 'border border-warning text-warning');
            renderBadges('tl_antecedentes', [].concat(info.antecedentes_personales || [], info.antecedentes_familiares || []), 'border border-gray text-gray');
            var mp = payload.data.motivos_consulta_paciente || {};
            var msgPac = (mp.messages && mp.messages.length) ? mp.messages.length : 0;
            renderMotivos(info.motivos_consulta || null, mp);
            renderCarePackCohorte(payload.data.care_pack_cohorte || null);
            var boxMsgs = document.getElementById('tl_motivos_consulta_mensajes');
            if (boxMsgs) boxMsgs.innerHTML = '';
            if (window.TimelineJS && typeof window.TimelineJS.applySignosVitalesPayload === 'function') {
                window.TimelineJS.applySignosVitalesPayload(payload.data.signos_vitales || null);
            }
        } catch (e) {
            renderBadges('tl_condiciones_activas', [], 'border border-info text-info');
            renderBadges('tl_condiciones_cronicas', [], 'border border-warning text-warning');
            renderBadges('tl_hallazgos', [], 'border border-warning text-warning');
            renderBadges('tl_antecedentes', [], 'border border-gray text-gray');
            renderMotivos(null, null);
            renderCarePackCohorte(null);
            var boxMsgsErr = document.getElementById('tl_motivos_consulta_mensajes');
            if (boxMsgsErr) boxMsgsErr.innerHTML = '';
            if (window.TimelineJS && typeof window.TimelineJS.applySignosVitalesPayload === 'function') {
                window.TimelineJS.applySignosVitalesPayload(null);
            }
        }
    }

    // Función para inicializar el timeline
    function inicializarTimeline() {
        console.log('Intentando inicializar timeline...');
        console.log('TimelineJS disponible:', !!window.TimelineJS);
        console.log('TimelineJS.init disponible:', !!(window.TimelineJS && window.TimelineJS.init));
        console.log('Config:', timelineConfig);
        
        // Verificar que los elementos del DOM estén presentes
        const signosVitalesContent = document.getElementById('signos-vitales-actuales-content');
        const formularioContainer = document.getElementById('formulario-container');
        
        if (!signosVitalesContent && !formularioContainer) {
            console.warn('Elementos del timeline no encontrados en el DOM');
            return false;
        }
        
        if (window.TimelineJS && window.TimelineJS.init) {
            console.log('Inicializando timeline con config:', timelineConfig);
            try {
                // Siempre inicializar, incluso si ya se inicializó antes (para SPA)
                window.TimelineJS.init(timelineConfig);
                loadTimelineSummary();
                console.log('Timeline inicializado correctamente');
                return true;
            } catch (error) {
                console.error('Error al inicializar timeline:', error);
                return false;
            }
        } else {
            console.warn('TimelineJS no está disponible aún');
            return false;
        }
    }

    // Función para intentar inicializar con múltiples reintentos
    function intentarInicializarConReintentos(intentosMaximos, delay) {
        var intentos = 0;
        var intervalo = setInterval(function() {
            intentos++;
            console.log('Intento de inicialización:', intentos, 'de', intentosMaximos);
            
            if (inicializarTimeline()) {
                clearInterval(intervalo);
                return;
            }
            
            if (intentos >= intentosMaximos) {
                console.error('No se pudo inicializar el timeline después de', intentosMaximos, 'intentos');
                clearInterval(intervalo);
            }
        }, delay);
    }

    // Intentar inicializar inmediatamente y luego con reintentos
    if (!inicializarTimeline()) {
        // Si el DOM está cargando, esperar a que esté listo
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                console.log('DOM cargado, intentando inicializar timeline...');
                if (!inicializarTimeline()) {
                    intentarInicializarConReintentos(5, 300);
                }
            });
        } else {
            // DOM ya está listo, intentar con reintentos
            console.log('DOM ya está listo, intentando inicializar timeline...');
            intentarInicializarConReintentos(5, 300);
        }
    }
})();
</script>