<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\bootstrap5\Modal;

use common\models\Person\Persona;
use common\helpers\TimelineHelper;
use common\models\User;
use common\models\Clinical\Encounter;
use frontend\assets\GuardiaTableroAsset;
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
$edad = is_object($persona) ? $persona->edad : null;
$edadTexto = $edad !== null && $edad !== '' ? ((int) $edad) . ' años' : 'edad s/d';
$generoLabels = [1 => 'Femenino', 2 => 'Masculino', 3 => 'Otro', 4 => 'Indefinido'];
$generoTexto = $generoLabels[(int) ($persona->genero ?? 0)] ?? 'Sin datos';
$vistaConsultaCargada = strtolower((string) Yii::$app->request->get('vista', '')) === 'consulta';
$this->title = $vistaConsultaCargada
    ? ('Consulta cargada · ' . $persona->apellido . ', ' . $persona->nombre . ' | ' . $edadTexto)
    : ($persona->nombre . ' ' . $persona->otro_nombre . ', ' . $persona->apellido . ' | ' . $edadTexto . ' · ' . $generoTexto . ' · Barrio: ' . $barrioTexto);

$parentQuery = Yii::$app->request->get('parent');
$parentIdQuery = (int) Yii::$app->request->get('parent_id', 0);
$parentUpper = strtoupper(trim((string) $parentQuery));
$esContextoInternacion = $parentUpper === Encounter::PARENT_INTERNACION && $parentIdQuery > 0;
$esContextoGuardia = $parentUpper === Encounter::PARENT_GUARDIA && $parentIdQuery > 0;
$esContextoEpisodio = $esContextoInternacion || $esContextoGuardia;
$mostrarMotivosAmbulatorios = !$esContextoEpisodio
    && !in_array($parentUpper, [Encounter::PARENT_CIRUGIA, Encounter::PARENT_GENERICO_EMER], true);

$modoCaptura = $esContextoInternacion ? 'imp' : ($esContextoGuardia ? 'emer' : 'amb');

$historiaClinicaQs = [];
$verConsultaStaffPath = null;
if ($parentUpper === Encounter::PARENT_TURNO && $parentIdQuery > 0) {
    $historiaClinicaQs['turno_id'] = $parentIdQuery;
    $verConsultaStaffPath = '/api/v1/clinical/encounter/ver-consulta-como-staff?'
        . http_build_query(['turno_id' => $parentIdQuery]);
} elseif ($esContextoInternacion) {
    $historiaClinicaQs['parent'] = Encounter::PARENT_INTERNACION;
    $historiaClinicaQs['parent_id'] = $parentIdQuery;
} elseif ($esContextoGuardia) {
    $historiaClinicaQs['parent'] = Encounter::PARENT_GUARDIA;
    $historiaClinicaQs['parent_id'] = $parentIdQuery;
}
$historiaClinicaPath = '/api/v1/personas/' . (int) $persona->id_persona . '/historia-clinica';
if ($historiaClinicaQs !== []) {
    $historiaClinicaPath .= '?' . http_build_query($historiaClinicaQs);
}

// Los archivos JS (turnos.js, chat-inteligente.js, timeline.js) se cargan automáticamente desde AppAsset
// Solo registrar Plotly si es necesario para gráficos
$this->registerJsFile(
    "https://cdn.plot.ly/plotly-2.27.1.min.js",
    [
        'position' => View::POS_HEAD,
        'charset' => 'utf-8'
    ]
);
$this->registerCssFile(Url::to('@web/css/episodio-historia-banner.css'));
if (!empty($esContextoGuardia)) {
    GuardiaTableroAsset::register($this);
}

?>

<div class="container-fluid py-2 px-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="tl-btn-volver">
                <i class="bi bi-arrow-left"></i> Volver
            </button>
        </div>
        <div class="fw-bold text-body text-truncate" style="font-size: 1.05rem;" title="<?= Html::encode($this->title) ?>">
            <?= Html::encode($this->title) ?>
        </div>
    </div>

<?php if ($esContextoEpisodio):
    $episodioBannerAccent = $esContextoGuardia
        ? 'border-danger bg-danger-subtle'
        : 'border-primary bg-primary-subtle';
?>
<div id="tl_episodio_banner" class="mb-3" hidden data-episodio-tipo="<?= Html::encode($esContextoGuardia ? 'GUARDIA' : 'INTERNACION') ?>">
    <div class="sticky-top rounded shadow-sm p-3 border-start border-4 <?= Html::encode($episodioBannerAccent) ?>">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2" id="tl_episodio_triage" hidden>
            <span class="badge" id="tl_episodio_triage_badge"></span>
            <span class="text-muted small" id="tl_episodio_triage_meta"></span>
        </div>
        <div class="row g-2">
            <div class="col-sm-6">
                <span class="small text-uppercase text-muted fw-semibold d-block">Episodio</span>
                <span class="fw-semibold d-block" id="tl_episodio_titulo">Cargando…</span>
            </div>
            <div class="col-sm-6">
                <span class="small text-uppercase text-muted fw-semibold d-block">Estado</span>
                <span class="fw-semibold d-block" id="tl_episodio_estado">—</span>
            </div>
            <div class="col-12">
                <span class="small text-uppercase text-muted fw-semibold d-block">Motivo / ingreso</span>
                <span class="fw-semibold d-block" id="tl_episodio_motivo">—</span>
            </div>
        </div>
        <div class="mt-2" id="tl_episodio_acciones" hidden></div>
    </div>
</div>
<?php endif; ?>

<!-- Primera fila: Datos del paciente (compacta) -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card border-2 border-paper-300 bg-paper-50 mb-1">
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

                        <!-- Signos vitales: misma ubicación y estilo en AMB / GUARDIA / INTERNACION -->
                        <?php if ($esContextoEpisodio): ?>
                        <div class="mb-2" id="tl_episodio_sv_section">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <h6 class="mb-0" id="signos-vitales-titulo">SIGNOS VITALES ACTUALES</h6>
                                <span class="small text-muted" id="tl_episodio_sv_count"></span>
                            </div>                            
                            <div id="tl_episodio_sv_ultimos" class="mb-2"></div>
                            <div id="tl_episodio_sv_chart" class="w-100 border rounded bg-white p-1" style="min-height: 220px;"></div>
                            <p class="text-muted small mb-0 d-none" id="tl_episodio_sv_empty">Sin signos vitales. Se cargan en la captura de la consulta (texto/audio) o en el triage de admisión.</p>
                        </div>
                        <?php else: ?>
                        <div class="mb-2" id="tl_sv_longitudinal_wrap">
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
                        <?php endif; ?>

                        <div class="mb-3 pb-2 border-bottom border-2" id="tl_motivos_intake_section" style="display:none;">
                            <h6 class="mb-2 text-primary"><b>PREGUNTAS PREVIAS AL CHAT DE MOTIVOS</b></h6>
                            <div id="tl_motivos_intake" class="text-body"></div>
                        </div>

                        <?php if ($mostrarMotivosAmbulatorios): ?>
                        <div class="mb-3 pb-2 border-bottom border-2" id="tl_motivos_ambulatorio_wrap">
                            <h6 class="mb-2 text-primary"><b>MOTIVOS DE ESTA CONSULTA</b></h6>
                            <p class="mb-0 text-muted" id="tl_motivos_consulta">Cargando...</p>
                            <div id="tl_motivos_consulta_mensajes" class="mt-2"></div>
                        </div>

                        <div class="mb-3 pb-2 border-bottom border-2" id="tl_care_pack_section" style="display:none;">
                            <h6 class="mb-2 text-primary"><b>ASISTENCIA PRE-CONSULTA (COHORTE)</b></h6>
                            <div id="tl_care_pack_cohorte" class="text-body"></div>
                        </div>
                        <?php else: ?>
                        <div id="tl_motivos_consulta" class="d-none" aria-hidden="true"></div>
                        <div id="tl_motivos_consulta_mensajes" class="d-none" aria-hidden="true"></div>
                        <div id="tl_care_pack_section" class="d-none" aria-hidden="true">
                            <div id="tl_care_pack_cohorte"></div>
                        </div>
                        <?php endif; ?>

                        <?php if ($esContextoInternacion): ?>
                        <div class="mb-3 pb-2 border-bottom border-2" id="tl_internacion_contexto_section">
                            <h6 class="mb-2 text-primary"><b>INTERNACIÓN EN CURSO</b></h6>
                            <p class="mb-2 small text-muted" id="tl_internacion_resumen">Documentá la evolución del día.</p>
                        </div>
                        <?php endif; ?>

                        <?php if ($esContextoEpisodio): ?>
                        <div class="mb-3 pb-2" id="tl_episodio_timeline_section">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <h6 class="mb-0 text-primary"><b>REGISTRO DEL EPISODIO</b></h6>
                                <span class="small text-muted" id="tl_episodio_timeline_count"></span>
                            </div>
                            <div class="d-flex flex-wrap gap-1 mb-2" id="tl_episodio_timeline_filters" role="group" aria-label="Filtros del registro">
                                <button type="button" class="btn btn-sm btn-outline-secondary active" data-tl-filter="all">Todos</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-tl-filter="clinico">Clínico</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-tl-filter="enfermeria">Enfermería</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-tl-filter="pedidos">Pedidos / lab</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-tl-filter="farmacos">Fármacos</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-tl-filter="circuito">Circuito</button>
                            </div>
                            <div id="tl_episodio_timeline_list">
                                <p class="text-muted mb-0 small">Cargando registro…</p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="mb-3 pb-2 border-bottom border-2" id="tl_documentacion_medico_section" style="display:none;">
                            <h6 class="mb-2 text-primary"><b>Datos cargados</b></h6>
                            <div id="tl_documentacion_medico" class="text-body"></div>
                        </div>
                    </div>

                    <!-- Contenido automático con loading -->
                    <div class="col-12 ms-3">
                        <!-- Loading inicial -->
                        <div id="loading-container" class="d-flex justify-content-center align-items-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <span class="ms-2">Cargando información...</span>
                        </div>
                        
                        <!-- Contenedores para el contenido -->
                        <?php if ($edad !== null && (int) $edad < 14) : ?>
                            <div id="curvas-crecimiento-content" class="mb-3" style="display: none;"></div>
                        <?php endif; ?>
                    </div>

                    <!-- Formulario de chat inteligente -->
                    <div class="col-12 border-top border-2">
                        <div class="card border-0 bg-paper-50">
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

if ($esContextoGuardia):
    Modal::begin([
        'title' => 'Triage',
        'id' => 'modal-triage-guardia',
        'size' => Modal::SIZE_LARGE,
    ]);
    ?>
<div id="tl-triage-modal-body" class="p-1">
    <div class="text-center py-4 text-muted">Cargando…</div>
</div>
    <?php
    Modal::end();

    Modal::begin([
        'title' => 'Paciente se retiró',
        'id' => 'modal-egreso-guardia',
        'size' => Modal::SIZE_LARGE,
    ]);
    ?>
<form id="tl-egreso-guardia-form" class="p-1">
    <input type="hidden" name="modo_egreso" id="tl-egreso-modo" value="administrativo" />
    <p class="fw-semibold mb-3" id="tl-egreso-paciente-nombre"></p>
    <div class="row g-2">
        <div class="col-md-6">
            <label class="form-label">Fecha</label>
            <input type="date" class="form-control" name="fecha_fin" required />
        </div>
        <div class="col-md-6">
            <label class="form-label">Hora</label>
            <input type="text" class="form-control" name="hora_fin" placeholder="HH:MM" required />
        </div>
        <div class="col-12">
            <label class="form-label">Nota (opcional)</label>
            <textarea class="form-control" name="nota_administrativa" id="tl-egreso-nota-admin" rows="3"></textarea>
        </div>
    </div>
    <div class="alert alert-danger mt-3 d-none" id="tl-egreso-error"></div>
    <div class="d-flex justify-content-end gap-2 mt-3">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-danger" id="tl-egreso-submit">Confirmar retiro</button>
    </div>
</form>
    <?php
    Modal::end();
endif;
?>

<script>
(function() {
    'use strict';

    var triageModalEl = document.getElementById('modal-triage-guardia');
    if (triageModalEl) {
        triageModalEl.addEventListener('hidden.bs.modal', function () {
            var body = document.getElementById('tl-triage-modal-body');
            if (body) body.innerHTML = '';
        });
    }

    var btnVolver = document.getElementById('tl-btn-volver');
    if (btnVolver) {
        btnVolver.addEventListener('click', function () {
            if (window.history.length > 1) {
                window.history.back();
                return;
            }
            window.location.href = <?= json_encode(Url::to(['/site/index'])) ?>;
        });
    }
    
    // Configuración para el timeline (usar var para permitir redeclaración en SPA)
    var timelineConfig = {
        pacienteId: <?= $persona->id_persona ?>,
        vistaConsultaCargada: <?= $vistaConsultaCargada ? 'true' : 'false' ?>,
        modoCaptura: <?= json_encode($modoCaptura) ?>,
        parent: <?= json_encode($esContextoEpisodio ? $parentUpper : null) ?>,
        parentId: <?= $esContextoEpisodio ? (int) $parentIdQuery : 'null' ?>,
        endpoints: {
            curvasCrecimiento: <?= ($edad !== null && (int) $edad < 14) ? "'" . \yii\helpers\Url::to(['personas/curvas-crecimiento', 'id' => $persona->id_persona]) . "'" : 'null' ?>,
            //vacunas: '<?= \yii\helpers\Url::to(['personas/vacunas', 'dni' => $persona->documento, 'sexo' => $persona->sexo_biologico]) ?>',
            formularioConsulta: '<?= Url::to(['paciente/formulario-consulta', 'id' => $persona->id_persona]) ?>',
            historiaClinica: <?= json_encode($vistaConsultaCargada ? null : $historiaClinicaPath, JSON_UNESCAPED_SLASHES) ?>,
            verConsultaComoStaff: <?= json_encode($verConsultaStaffPath, JSON_UNESCAPED_SLASHES) ?>
        }
    };

    function bioHeaders() {
        if (typeof window.getBioenlaceApiClientHeaders === "function") {
            return window.getBioenlaceApiClientHeaders();
        }
        return {};
    }

    /** dd/MM/yyyy o dd/MM/yyyy HH:mm — alineado a API EpisodioDateTimeFormatter */
    function formatEpisodioFecha(raw) {
        var s = String(raw || '').trim();
        if (!s) return '';
        if (/^\d{2}\/\d{2}\/\d{4}( \d{2}:\d{2})?$/.test(s)) return s;
        var m = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{1,2}):(\d{2})(?::\d{2})?)?/);
        if (m) {
            var out = m[3] + '/' + m[2] + '/' + m[1];
            if (m[4] != null) {
                out += ' ' + String(m[4]).padStart(2, '0') + ':' + m[5];
            }
            return out;
        }
        return s;
    }

    function renderBadges(containerId, items, badgeClass) {
        var el = document.getElementById(containerId);
        if (!el) return;
        if (!items || !items.length) {
            el.innerHTML = '<span class="ms-2">Sin datos</span>';
            return;
        }
        var badges = items
            .filter(function (x) { return x && x.termino; })
            .map(function (x) { return '<span class="badge ' + badgeClass + ' me-1">' + String(x.termino).toUpperCase() + '</span>'; })
            .join('');
        el.innerHTML = badges || '<span class="ms-2">Sin datos</span>';
    }

    function renderEpisodioBanner(ctx) {
        var root = document.getElementById('tl_episodio_banner');
        if (!root) return;
        ctx = ctx || {};
        root.hidden = false;

        var tipo = String(ctx.tipo || root.getAttribute('data-episodio-tipo') || '');
        var tituloEl = document.getElementById('tl_episodio_titulo');
        var estadoEl = document.getElementById('tl_episodio_estado');
        var motEl = document.getElementById('tl_episodio_motivo');
        var triageWrap = document.getElementById('tl_episodio_triage');
        var triageBadge = document.getElementById('tl_episodio_triage_badge');
        var triageMeta = document.getElementById('tl_episodio_triage_meta');

        var labelTipo = tipo === 'GUARDIA' ? 'Guardia' : (tipo === 'INTERNACION' ? 'Internación' : 'Episodio');
        if (tituloEl) {
            var parts = [labelTipo];
            if (ctx.ingreso_at) parts.push('Ingreso ' + formatEpisodioFecha(ctx.ingreso_at));
            tituloEl.textContent = parts.join(' · ');
        }
        if (estadoEl) {
            estadoEl.textContent = ctx.estado_label || ctx.estado || 'En curso';
        }
        if (motEl) {
            motEl.textContent = ctx.motivo ? String(ctx.motivo) : 'Sin motivo registrado';
        }

        var triage = ctx.triage;
        if (triageWrap && triageBadge) {
            if (triage && triage.level != null) {
                triageWrap.hidden = false;
                var level = parseInt(triage.level, 10);
                var levelClass = (level >= 1 && level <= 5)
                    ? ('guardia-triage-badge--' + level)
                    : 'guardia-triage-badge--none';
                triageBadge.className = 'badge ' + levelClass;
                triageBadge.style.backgroundColor = '';
                triageBadge.style.color = '';
                triageBadge.textContent = (triage.level_label || ('Nivel ' + triage.level));
                if (triageMeta) {
                    triageMeta.textContent = triage.scale ? String(triage.scale) : '';
                    triageMeta.hidden = !triageMeta.textContent;
                }
            } else {
                triageWrap.hidden = true;
            }
        }

        renderEpisodioAcciones(ctx.acciones || []);
    }

    function renderEpisodioAcciones(acciones) {
        var box = document.getElementById('tl_episodio_acciones');
        if (!box) return;
        acciones = Array.isArray(acciones) ? acciones : [];
        if (!acciones.length) {
            box.hidden = true;
            box.innerHTML = '';
            return;
        }
        box.hidden = false;
        var html = '';
        acciones.forEach(function (a) {
            if (!a || !a.id) return;
            var btnClass = a.id === 'editar_triage' ? 'btn-outline-primary' : 'btn-danger';
            var apiRoute = '';
            if (a.api && a.api.route) {
                apiRoute = String(a.api.route);
            } else if (a.api_route) {
                apiRoute = String(a.api_route);
            }
            html += '<button type="button" class="btn btn-sm ' + btnClass + ' me-1 mb-1" data-tl-accion="'
                + escMotivosHtml(a.id) + '"'
                + (apiRoute ? ' data-tl-api-route="' + escMotivosHtml(apiRoute) + '"' : '')
                + '>'
                + escMotivosHtml(a.label || a.id)
                + '</button>';
        });
        box.innerHTML = html;
        box.querySelectorAll('[data-tl-accion]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-tl-accion');
                if (id === 'egreso_estructurado') {
                    openEgresoGuardiaModal();
                } else if (id === 'editar_triage') {
                    openTriageGuardiaModal(btn.getAttribute('data-tl-api-route'));
                }
            });
        });
    }

    async function openTriageGuardiaModal(apiRoute) {
        if (!timelineConfig.parentId) return;
        var route = apiRoute || ('/clinical/emergency-guardia/registrar-triage-formulario?guardia_id='
            + encodeURIComponent(String(timelineConfig.parentId)));
        var url = route.indexOf('/api/') === 0 || route.indexOf('http') === 0
            ? route
            : ('/api/v1' + (route.charAt(0) === '/' ? route : '/' + route));

        var modalEl = document.getElementById('modal-triage-guardia');
        var contentEl = document.getElementById('tl-triage-modal-body');
        var titleEl = modalEl
            ? modalEl.querySelector('.modal-title')
            : null;
        if (!modalEl || !contentEl || !window.bootstrap) {
            window.location.href = url;
            return;
        }
        contentEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
        if (titleEl) titleEl.textContent = 'Triage';
        var modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        function fieldValuesFromUi(root) {
            var values = {};
            if (root && root.values && typeof root.values === 'object') {
                Object.keys(root.values).forEach(function (k) {
                    values[k] = root.values[k];
                });
            }
            var blocks = (root && root.blocks) || [];
            blocks.forEach(function (b) {
                if (!b || !Array.isArray(b.fields)) return;
                b.fields.forEach(function (f) {
                    if (!f || !f.name) return;
                    if (f.value !== undefined && f.value !== null && String(f.value) !== '') {
                        values[f.name] = f.value;
                    }
                });
            });
            return values;
        }

        function closeTriageModal() {
            try {
                modal.hide();
            } catch (eHide) {}
            contentEl.innerHTML = '';
            // Evitar backdrop huérfano si quedó algún modal abierto.
            document.querySelectorAll('.modal-backdrop').forEach(function (bd) {
                if (!document.querySelector('.modal.show')) {
                    bd.remove();
                }
            });
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        }

        try {
            var headers = (window.BioenlaceApiClient && typeof window.BioenlaceApiClient.mergeHeaders === 'function')
                ? window.BioenlaceApiClient.mergeHeaders({ 'X-Requested-With': 'XMLHttpRequest' })
                : { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
            var res = await fetch(url, { method: 'GET', credentials: 'same-origin', headers: headers });
            var json = await res.json();
            var root = json;
            if (json && json.data && json.data.kind === 'ui_definition') {
                root = json.data;
            }
            if (!root || root.kind !== 'ui_definition') {
                throw new Error((json && json.message) || 'No se pudo cargar el triage.');
            }
            if (titleEl) titleEl.textContent = root.title || 'Triage';

            var data = fieldValuesFromUi(root);
            var level = String(data.level || '3');
            var reason = String(data.reason_text || '');
            var levelBtns = [1, 2, 3, 4, 5].map(function (n) {
                var checked = String(n) === level ? ' checked' : '';
                return '<input type="radio" class="btn-check" name="level" id="tl-triage-level-' + n
                    + '" value="' + n + '" autocomplete="off"' + checked + ' required>'
                    + '<label class="btn btn-sm btn-outline-secondary fw-bold guardia-triage-level guardia-triage-level--' + n
                    + '" for="tl-triage-level-' + n + '">' + n + '</label>';
            }).join('');
            contentEl.innerHTML = ''
                + '<form id="tl-triage-form" class="p-1">'
                + '<input type="hidden" name="guardia_id" value="' + escMotivosHtml(String(timelineConfig.parentId)) + '" />'
                + '<label class="form-label">Prioridad (Manchester)</label>'
                + '<div class="d-flex flex-wrap gap-2 mb-3" role="group">' + levelBtns + '</div>'
                + '<label class="form-label">Motivo de consulta</label>'
                + '<textarea class="form-control mb-2" name="reason_text" rows="3" required>' + escMotivosHtml(reason) + '</textarea>'
                + '<div class="row g-2 mb-2">'
                + '<div class="col-4"><label class="form-label">TA sys</label>'
                + '<input class="form-control" name="bp_sys" data-triage-vital="bp_sys" inputmode="numeric" maxlength="3" value="'
                + escMotivosHtml(String(data.bp_sys || '')) + '" /></div>'
                + '<div class="col-4"><label class="form-label">TA dia</label>'
                + '<input class="form-control" name="bp_dia" data-triage-vital="bp_dia" inputmode="numeric" maxlength="3" value="'
                + escMotivosHtml(String(data.bp_dia || '')) + '" /></div>'
                + '<div class="col-4"><label class="form-label">FC</label>'
                + '<input class="form-control" name="hr" data-triage-vital="hr" inputmode="numeric" maxlength="3" value="'
                + escMotivosHtml(String(data.hr || '')) + '" /></div>'
                + '</div>'
                + '<p class="small text-muted mb-2">TA y FC: enteros de 2–3 dígitos (opcionales).</p>'
                + '<div class="alert alert-danger d-none" id="tl-triage-error"></div>'
                + '<div class="d-flex justify-content-end gap-2">'
                + '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>'
                + '<button type="submit" class="btn btn-primary" id="tl-triage-submit">Guardar</button>'
                + '</div></form>';

            if (window.BioenlaceTriageVitals) {
                window.BioenlaceTriageVitals.bindVitalInputs(contentEl);
            }

            var form = document.getElementById('tl-triage-form');
            form.addEventListener('submit', async function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                var errEl = document.getElementById('tl-triage-error');
                var submitBtn = document.getElementById('tl-triage-submit');
                if (errEl) errEl.classList.add('d-none');
                var reasonEl = form.querySelector('[name="reason_text"]');
                var reasonVal = reasonEl ? String(reasonEl.value || '').trim() : '';
                if (!reasonVal) {
                    if (errEl) {
                        errEl.textContent = 'Indicá el motivo de consulta.';
                        errEl.classList.remove('d-none');
                    }
                    return;
                }
                var levelChecked = form.querySelector('input[name="level"]:checked');
                var vitalsCheck = window.BioenlaceTriageVitals
                    ? window.BioenlaceTriageVitals.validateVitals(
                        window.BioenlaceTriageVitals.readVitalsFromForm(form)
                    )
                    : { ok: true, vitals: undefined };
                if (!vitalsCheck.ok) {
                    if (errEl) {
                        errEl.textContent = vitalsCheck.message;
                        errEl.classList.remove('d-none');
                    }
                    return;
                }
                var body = {
                    guardia_id: String(timelineConfig.parentId),
                    level: levelChecked ? levelChecked.value : '3',
                    reason_text: reasonVal,
                };
                if (vitalsCheck.vitals) {
                    Object.keys(vitalsCheck.vitals).forEach(function (k) {
                        body[k] = String(vitalsCheck.vitals[k]);
                    });
                }
                if (submitBtn) submitBtn.disabled = true;
                try {
                    var postRes = await fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: Object.assign({}, headers, { 'Content-Type': 'application/json' }),
                        body: JSON.stringify(body),
                    });
                    var postJson = await postRes.json();
                    if (postJson && postJson.success === false) {
                        throw new Error(postJson.message || 'No se pudo guardar el triage.');
                    }
                    closeTriageModal();
                    await refreshEpisodioAfterTriage();
                } catch (eSave) {
                    if (errEl) {
                        errEl.textContent = eSave && eSave.message ? String(eSave.message) : 'Error al guardar.';
                        errEl.classList.remove('d-none');
                    }
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        } catch (e) {
            contentEl.innerHTML = '<div class="alert alert-danger mb-0">'
                + escMotivosHtml(e && e.message ? String(e.message) : 'Error al abrir triage.')
                + '</div>';
        }
    }

    /** Solo banner + SV + registro del episodio (sin recargar captura ni meter HTML en el modal). */
    async function refreshEpisodioAfterTriage() {
        var endpoints = timelineConfig.endpoints || {};
        if (!endpoints.historiaClinica) return;
        try {
            var resp = await fetch(endpoints.historiaClinica, { headers: bioHeaders() });
            var payload = await resp.json();
            if (!payload || payload.success !== true || !payload.data) {
                return;
            }
            renderEpisodioBanner(payload.data.contexto_episodio || null);
            renderTimelineEpisodio(payload.data.timeline_episodio || null);
            renderSignosVitalesEpisodio(payload.data.signos_vitales_episodio || null);
        } catch (e) {
            console.warn('No se pudo refrescar el episodio tras triage', e);
        }
    }

    function applyEgresoModoUi() {
        var titleEl = document.querySelector('#modal-egreso-guardia .modal-title');
        if (titleEl) titleEl.textContent = 'Paciente se retiró';
    }

    async function openEgresoGuardiaModal() {
        var modalEl = document.getElementById('modal-egreso-guardia');
        if (!modalEl || !timelineConfig.parentId) return;
        var form = document.getElementById('tl-egreso-guardia-form');
        var errEl = document.getElementById('tl-egreso-error');
        if (errEl) {
            errEl.classList.add('d-none');
            errEl.textContent = '';
        }
        var url = '/api/v1/clinical/emergency-guardia/' + timelineConfig.parentId + '/egreso-formulario';
        try {
            var resp = await fetch(url, { headers: bioHeaders() });
            var payload = await resp.json();
            var data = (payload && payload.data) ? payload.data : {};
            var modoEl = document.getElementById('tl-egreso-modo');
            if (modoEl) modoEl.value = 'administrativo';
            applyEgresoModoUi();
            var nombreEl = document.getElementById('tl-egreso-paciente-nombre');
            if (nombreEl) nombreEl.textContent = data.paciente_nombre || data.resumen_texto || '';
            if (form) {
                var today = new Date();
                var yyyy = today.getFullYear();
                var mm = String(today.getMonth() + 1).padStart(2, '0');
                var dd = String(today.getDate()).padStart(2, '0');
                var fecha = form.querySelector('[name="fecha_fin"]');
                var hora = form.querySelector('[name="hora_fin"]');
                var nota = form.querySelector('[name="nota_administrativa"]');
                if (fecha) fecha.value = yyyy + '-' + mm + '-' + dd;
                if (hora) {
                    hora.value = String(today.getHours()).padStart(2, '0') + ':'
                        + String(today.getMinutes()).padStart(2, '0');
                }
                if (nota) nota.value = '';
            }
        } catch (e) {
            console.warn('No se pudo precargar egreso', e);
        }
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else {
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
        }
    }

    function bindEgresoGuardiaForm() {
        var form = document.getElementById('tl-egreso-guardia-form');
        if (!form || form.getAttribute('data-bound') === '1') return;
        form.setAttribute('data-bound', '1');
        form.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            var errEl = document.getElementById('tl-egreso-error');
            var submitBtn = document.getElementById('tl-egreso-submit');
            if (errEl) {
                errEl.classList.add('d-none');
                errEl.textContent = '';
            }
            var fd = new FormData(form);
            var params = new URLSearchParams();
            fd.forEach(function (v, k) {
                params.append(k, v == null ? '' : String(v));
            });
            var fechaIso = params.get('fecha_fin');
            if (fechaIso && /^\d{4}-\d{2}-\d{2}$/.test(String(fechaIso))) {
                var p = String(fechaIso).split('-');
                params.set('fecha_fin', p[2] + '/' + p[1] + '/' + p[0]);
            }
            if (submitBtn) submitBtn.disabled = true;
            try {
                var url = '/api/v1/clinical/emergency-guardia/' + timelineConfig.parentId + '/egreso-formulario';
                var resp = await fetch(url, {
                    method: 'POST',
                    headers: Object.assign({}, bioHeaders(), {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    }),
                    body: params.toString()
                });
                var payload = await resp.json();
                if (!resp.ok || (payload && payload.success === false)) {
                    throw new Error((payload && payload.message) ? payload.message : 'No se pudo registrar el retiro.');
                }
                var modalEl = document.getElementById('modal-egreso-guardia');
                if (modalEl && window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
                window.location.href = <?= json_encode(Url::to(['/site/index'])) ?>;
            } catch (e) {
                if (errEl) {
                    errEl.textContent = e && e.message ? String(e.message) : 'Error al registrar retiro.';
                    errEl.classList.remove('d-none');
                }
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    }

    function renderContextoInternacion(ctx) {
        var resumenEl = document.getElementById('tl_internacion_resumen');
        if (!resumenEl) return;
        ctx = ctx || {};
        var parts = [];
        if (ctx.fecha_inicio) parts.push('Ingreso ' + formatEpisodioFecha(ctx.fecha_inicio));
        resumenEl.textContent = (parts.length ? parts.join(' · ') + ' — ' : '')
            + 'documentá la evolución del día.';
    }

    var _tlEpisodioItems = [];
    var _tlEpisodioFilter = 'all';

    var TL_FILTER_TYPES = {
        all: null,
        clinico: ['evolucion_medica', 'triage'],
        enfermeria: ['atencion_enfermeria'],
        pedidos: ['pedido', 'resultado_lab', 'interconsulta'],
        farmacos: ['medicacion', 'administracion'],
        circuito: ['circuito', 'triage']
    };

    var TL_TYPE_LABEL = {
        circuito: 'Circuito',
        triage: 'Triage',
        evolucion_medica: 'Evolución',
        atencion_enfermeria: 'Enfermería',
        pedido: 'Pedido',
        resultado_lab: 'Lab',
        medicacion: 'Medicación',
        administracion: 'Admin.',
        interconsulta: 'Interconsulta'
    };

    var TL_TYPE_BADGE = {
        circuito: 'text-bg-danger',
        triage: 'text-bg-danger',
        evolucion_medica: 'text-bg-secondary',
        atencion_enfermeria: 'text-bg-success',
        pedido: 'text-bg-info',
        resultado_lab: 'text-bg-info',
        interconsulta: 'text-bg-info',
        medicacion: 'text-bg-warning',
        administracion: 'text-bg-warning'
    };

    function renderTimelineEpisodio(feed) {
        var listEl = document.getElementById('tl_episodio_timeline_list');
        var countEl = document.getElementById('tl_episodio_timeline_count');
        if (!listEl) return;
        feed = feed || {};
        _tlEpisodioItems = Array.isArray(feed.items) ? feed.items : [];
        if (countEl) {
            countEl.textContent = _tlEpisodioItems.length
                ? (_tlEpisodioItems.length + ' hito' + (_tlEpisodioItems.length === 1 ? '' : 's'))
                : '';
        }
        paintTimelineEpisodio();
    }

    function renderSignosVitalesEpisodio(sv) {
        var chartEl = document.getElementById('tl_episodio_sv_chart');
        var ultimosEl = document.getElementById('tl_episodio_sv_ultimos');
        var emptyEl = document.getElementById('tl_episodio_sv_empty');
        var countEl = document.getElementById('tl_episodio_sv_count');
        if (!chartEl && !ultimosEl) return;
        sv = sv || {};
        var series = Array.isArray(sv.series) ? sv.series : [];
        var ultimos = sv.ultimos || {};
        var total = parseInt(sv.total_points || 0, 10) || 0;

        if (countEl) {
            countEl.textContent = total > 0 ? (total + ' medición' + (total === 1 ? '' : 'es')) : '';
        }

        if (ultimosEl) {
            var chips = [];
            var order = ['ta', 'fc', 'fr', 'sat_o2', 'temp', 'glucemia', 'glasgow'];
            order.forEach(function (key) {
                if (key === 'ta' && ultimos.ta) {
                    var sys = ultimos.ta.sistolica;
                    var dia = ultimos.ta.diastolica;
                    if (sys != null || dia != null) {
                        chips.push('<span class="badge text-bg-light border me-1 mb-1">TA '
                            + (sys != null ? sys : '—') + '/' + (dia != null ? dia : '—')
                            + ' <span class="text-muted">' + escMotivosHtml(ultimos.ta.at || '') + '</span></span>');
                    }
                    return;
                }
                var u = ultimos[key];
                if (!u || u.value == null) return;
                chips.push('<span class="badge text-bg-light border me-1 mb-1">'
                    + escMotivosHtml(u.label || key) + ': <strong>' + escMotivosHtml(String(u.value))
                    + '</strong> ' + escMotivosHtml(u.unit || '')
                    + ' <span class="text-muted">' + escMotivosHtml(u.at || '') + '</span></span>');
            });
            ultimosEl.innerHTML = chips.join('') || '';
        }

        if (!series.length) {
            if (chartEl) chartEl.innerHTML = '';
            if (emptyEl) emptyEl.classList.remove('d-none');
            return;
        }
        if (emptyEl) emptyEl.classList.add('d-none');

        if (!chartEl || typeof Plotly === 'undefined') {
            return;
        }

        var colors = {
            ta_sys: '#dc3545',
            ta_dia: '#fd7e14',
            fc: '#0d6efd',
            fr: '#20c997',
            sat_o2: '#6f42c1',
            temp: '#d63384',
            glucemia: '#198754',
            glasgow: '#6c757d'
        };
        var traces = series.map(function (s) {
            var xs = (s.points || []).map(function (p) { return p.at; });
            var ys = (s.points || []).map(function (p) { return p.value; });
            return {
                x: xs,
                y: ys,
                type: 'scatter',
                mode: 'lines+markers',
                name: (s.label || s.metric) + (s.unit ? ' (' + s.unit + ')' : ''),
                line: { color: colors[s.metric] || undefined, width: 2 },
                marker: { size: 7 }
            };
        });
        Plotly.newPlot(chartEl, traces, {
            margin: { t: 24, r: 16, b: 40, l: 48 },
            legend: { orientation: 'h', y: 1.15 },
            xaxis: { title: '', automargin: true },
            yaxis: { title: '', automargin: true, zeroline: false },
            paper_bgcolor: 'rgba(0,0,0,0)',
            plot_bgcolor: 'rgba(0,0,0,0)',
            height: 260
        }, { responsive: true, displayModeBar: false });
    }

    function paintTimelineEpisodio() {
        var listEl = document.getElementById('tl_episodio_timeline_list');
        if (!listEl) return;
        var allowed = TL_FILTER_TYPES[_tlEpisodioFilter] || null;
        var items = _tlEpisodioItems.filter(function (it) {
            if (!allowed) return true;
            return allowed.indexOf(it.type) !== -1;
        });
        if (!items.length) {
            listEl.innerHTML = '<p class="text-muted mb-0 small">'
                + (_tlEpisodioItems.length ? 'Sin hitos para este filtro.' : 'Sin hitos registrados en este episodio.')
                + '</p>';
            return;
        }
        var groups = groupTimelineEpisodioItems(items);
        var html = '<ul class="tl-episodio-timeline list-unstyled mb-0">';
        groups.forEach(function (group) {
            var type = group.type || '';
            var typeLabel = TL_TYPE_LABEL[type] || type || 'Hito';
            var actor = group.actorKey || '';
            var count = group.items.length;
            var badgeClass = TL_TYPE_BADGE[type] || 'text-bg-light border text-dark';
            html += '<li class="tl-episodio-timeline__item" data-tl-type="' + escMotivosHtml(type) + '">';
            html += '<div class="d-flex flex-wrap align-items-center gap-2 mb-1">';
            html += '<span class="badge ' + badgeClass + '">' + escMotivosHtml(typeLabel);
            if (count > 1) {
                html += ' · ' + count;
            }
            html += '</span>';
            html += '<span class="text-muted small">' + escMotivosHtml(formatEpisodioFecha(group.occurred_at || '')) + '</span>';
            if (actor) {
                html += '<span class="text-muted small">' + escMotivosHtml(actor) + '</span>';
            }
            html += '</div>';
            if (count === 1) {
                var one = formatTimelineSummaryParts(group.items[0]);
                html += '<div class="small text-break">' + escMotivosHtml(one.text);
                if (one.status) {
                    html += ' <span class="text-muted">(' + escMotivosHtml(one.status) + ')</span>';
                }
                html += '</div>';
            } else {
                html += '<ul class="mb-0 ps-3 small">';
                group.items.forEach(function (it) {
                    var part = formatTimelineSummaryParts(it);
                    html += '<li class="mb-1">' + escMotivosHtml(part.text);
                    if (part.status) {
                        html += ' <span class="text-muted">(' + escMotivosHtml(part.status) + ')</span>';
                    }
                    html += '</li>';
                });
                html += '</ul>';
            }
            html += '</li>';
        });
        html += '</ul>';
        listEl.innerHTML = html;
    }

    /** Tipos que se agrupan si son consecutivos (mismo minuto + actor). */
    var TL_GROUPABLE_TYPES = {
        pedido: true,
        medicacion: true,
        administracion: true,
        resultado_lab: true,
        interconsulta: true,
        atencion_enfermeria: true
    };

    var TL_STATUS_LABEL = {
        active: 'activo',
        completed: 'completado',
        draft: 'borrador',
        'on-hold': 'en espera',
        revoked: 'anulado',
        cancelled: 'cancelado',
        stopped: 'detenido',
        finished: 'finalizado'
    };

    function timelineMinuteKey(occurredAt) {
        var s = String(occurredAt || '').trim();
        // dd/MM/yyyy HH:mm (API ya formateada) o ISO → misma granularidad de minuto
        var mAr = s.match(/^(\d{2}\/\d{2}\/\d{4})(?:\s+(\d{2}:\d{2}))?/);
        if (mAr) {
            return mAr[1] + ' ' + (mAr[2] || '00:00');
        }
        var mIso = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{1,2}):(\d{2}))?/);
        if (mIso) {
            var hh = mIso[4] != null ? String(mIso[4]).padStart(2, '0') : '00';
            var mm = mIso[5] != null ? mIso[5] : '00';
            return mIso[3] + '/' + mIso[2] + '/' + mIso[1] + ' ' + hh + ':' + mm;
        }
        return s.slice(0, 16);
    }

    function groupTimelineEpisodioItems(items) {
        var groups = [];
        items.forEach(function (it) {
            var type = it.type || '';
            var actorKey = (it.actor && it.actor.nombre) ? String(it.actor.nombre) : '';
            var timeKey = timelineMinuteKey(it.occurred_at);
            var last = groups.length ? groups[groups.length - 1] : null;
            var canGroup = !!TL_GROUPABLE_TYPES[type];
            if (
                canGroup
                && last
                && last.type === type
                && last.timeKey === timeKey
                && last.actorKey === actorKey
            ) {
                last.items.push(it);
                return;
            }
            groups.push({
                type: type,
                timeKey: timeKey,
                actorKey: actorKey,
                occurred_at: it.occurred_at || '',
                items: [it]
            });
        });
        return groups;
    }

    function formatTimelineSummaryParts(it) {
        var text = String((it && it.summary) || '').trim();
        var status = '';
        var payload = (it && it.payload) || {};
        if (payload.status) {
            status = String(payload.status);
        }
        var m = text.match(/\s·\s*(active|completed|draft|on-hold|revoked|cancelled|stopped|finished)\s*$/i);
        if (m) {
            if (!status) status = m[1];
            text = text.slice(0, m.index).trim();
        }
        if (status) {
            var key = String(status).toLowerCase();
            status = TL_STATUS_LABEL[key] || status;
        }
        return { text: text, status: status };
    }

    function bindTimelineEpisodioFilters() {
        var root = document.getElementById('tl_episodio_timeline_filters');
        if (!root || root.getAttribute('data-bound') === '1') return;
        root.setAttribute('data-bound', '1');
        root.addEventListener('click', function (ev) {
            var btn = ev.target.closest('[data-tl-filter]');
            if (!btn) return;
            _tlEpisodioFilter = btn.getAttribute('data-tl-filter') || 'all';
            root.querySelectorAll('[data-tl-filter]').forEach(function (b) {
                b.classList.toggle('active', b === btn);
            });
            paintTimelineEpisodio();
        });
    }

    function renderMotivos(texto, mp) {
        var el = document.getElementById('tl_motivos_consulta');
        if (!el) return;
        if (timelineConfig.modoCaptura === 'imp' || timelineConfig.modoCaptura === 'emer') {
            el.innerHTML = '';
            return;
        }
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

    function renderMotivosIntake(intake) {
        var section = document.getElementById('tl_motivos_intake_section');
        var el = document.getElementById('tl_motivos_intake');
        if (!section || !el) return;
        if (!intake || typeof intake !== 'object') {
            section.style.display = 'none';
            el.innerHTML = '';
            return;
        }
        var answers = intake.answers || [];
        var notes = intake.notes_for_staff ? String(intake.notes_for_staff).trim() : '';
        var status = intake.status ? String(intake.status) : '';
        if (!notes && (!answers || !answers.length) && status !== 'pending') {
            section.style.display = 'none';
            el.innerHTML = '';
            return;
        }
        section.style.display = '';
        var html = '';
        if (intake.title) {
            html += '<div class="small text-muted mb-2">' + escMotivosHtml(intake.title) + '</div>';
        }
        if (notes !== '') {
            html += '<div class="small text-uppercase text-muted">Orientación</div>';
            html += '<p class="mb-2" style="white-space:pre-wrap">' + escMotivosHtml(notes) + '</p>';
        }
        if (!answers || !answers.length) {
            html += '<p class="text-muted mb-0">El paciente aún no completó las preguntas previas.</p>';
        } else {
            html += '<div class="small text-uppercase text-muted">Respuestas del paciente</div>';
            html += '<dl class="mb-0 mt-2">';
            answers.forEach(function (a) {
                if (!a) return;
                html += '<dt class="fw-semibold">' + escMotivosHtml(a.question || a.id || '') + '</dt>';
                html += '<dd class="mb-2" style="white-space:pre-wrap">' + escMotivosHtml(a.answer || '') + '</dd>';
            });
            html += '</dl>';
        }
        el.innerHTML = html;
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

    function renderDocumentacionMedico(doc) {
        var section = document.getElementById('tl_documentacion_medico_section');
        var el = document.getElementById('tl_documentacion_medico');
        if (!section || !el) return;
        if (!doc || !doc.tiene_datos || !doc.secciones || !doc.secciones.length) {
            section.style.display = 'none';
            el.innerHTML = '';
            return;
        }
        section.style.display = '';
        var html = '';
        doc.secciones.forEach(function (sec) {
            html += '<div class="mb-3"><div class="fw-semibold">' + escMotivosHtml(sec.titulo || '') + '</div><ul class="mb-0">';
            (sec.items || []).forEach(function (item) {
                html += '<li style="white-space:pre-wrap">' + escMotivosHtml(item) + '</li>';
            });
            html += '</ul></div>';
        });
        el.innerHTML = html;
    }

    function applyStaffConsultaSoloLectura(data) {
        renderBadges('tl_condiciones_activas', [], 'border border-info text-info');
        renderBadges('tl_condiciones_cronicas', [], 'border border-warning text-warning');
        renderBadges('tl_hallazgos', [], 'border border-warning text-warning');
        renderBadges('tl_antecedentes', [], 'border border-gray text-gray');
        var mp = data.motivos_consulta_paciente || {};
        renderMotivos(null, mp);
        renderMotivosIntake(mp.motivos_intake || null);
        renderCarePackCohorte(data.care_pack_cohorte || null);
        renderDocumentacionMedico(data.documentacion_medico || null);
        var boxMsgs = document.getElementById('tl_motivos_consulta_mensajes');
        if (boxMsgs) boxMsgs.innerHTML = '';
        var signos = document.getElementById('signos-vitales-actuales-content');
        if (signos) {
            signos.innerHTML = '<span class="text-muted">Consulta en solo lectura</span>';
        }
        var formBox = document.getElementById('formulario-container');
        if (formBox) formBox.innerHTML = '';
        var loadingEl = document.getElementById('loading-container');
        if (loadingEl) loadingEl.style.display = 'none';
    }

    async function loadTimelineSummary() {
        try {
            var endpoints = timelineConfig.endpoints || {};
            var staffEp = endpoints.verConsultaComoStaff;
            var soloConsultaCargada = !!timelineConfig.vistaConsultaCargada;

            if (staffEp) {
                try {
                    var staffResp = await fetch(staffEp, { headers: bioHeaders() });
                    var staffPayload = await staffResp.json();
                    if (
                        staffResp.ok
                        && staffPayload
                        && staffPayload.success === true
                        && staffPayload.data
                    ) {
                        var turnoStaff = staffPayload.data.turno || {};
                        var esAtendido = String(turnoStaff.estado || '').toUpperCase() === 'ATENDIDO';
                        if (soloConsultaCargada || esAtendido) {
                            applyStaffConsultaSoloLectura(staffPayload.data);
                            return;
                        }
                    } else if (soloConsultaCargada) {
                        throw new Error(
                            (staffPayload && staffPayload.message)
                                ? staffPayload.message
                                : 'No se pudo cargar la consulta documentada.'
                        );
                    }
                } catch (staffErr) {
                    if (soloConsultaCargada) {
                        throw staffErr;
                    }
                    console.warn('ver-consulta-como-staff no disponible, se usa historia-clinica', staffErr);
                }
            } else if (soloConsultaCargada) {
                throw new Error('Falta contexto de turno para ver la consulta.');
            }

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
            var esEpisodio = timelineConfig.modoCaptura === 'imp' || timelineConfig.modoCaptura === 'emer';
            if (esEpisodio) {
                renderMotivos(null, null);
                renderMotivosIntake(null);
                renderCarePackCohorte(null);
                renderEpisodioBanner(payload.data.contexto_episodio || null);
                if (timelineConfig.modoCaptura === 'imp') {
                    renderContextoInternacion(payload.data.contexto_internacion || null);
                }
                bindTimelineEpisodioFilters();
                bindEgresoGuardiaForm();
                renderTimelineEpisodio(payload.data.timeline_episodio || null);
                renderSignosVitalesEpisodio(payload.data.signos_vitales_episodio || null);
                try {
                    var qs = new URLSearchParams(window.location.search || '');
                    if (qs.get('egreso') === '1' && timelineConfig.modoCaptura === 'emer') {
                        openEgresoGuardiaModal();
                    }
                } catch (eEg) {}
            } else {
                renderMotivos(info.motivos_consulta || null, mp);
                renderMotivosIntake(mp.motivos_intake || null);
                renderCarePackCohorte(payload.data.care_pack_cohorte || null);
            }
            renderDocumentacionMedico(null);
            var boxMsgs = document.getElementById('tl_motivos_consulta_mensajes');
            if (boxMsgs) boxMsgs.innerHTML = '';
            if (!esEpisodio && window.TimelineJS && typeof window.TimelineJS.applySignosVitalesPayload === 'function') {
                window.TimelineJS.applySignosVitalesPayload(payload.data.signos_vitales || null);
            }
            // Captura solo si el turno lo permite; no invocar formulario-consulta en solo lectura.
            var captura = payload.data.captura || {};
            var formBox = document.getElementById('formulario-container');
            if (captura.permitida === false) {
                if (formBox) {
                    formBox.innerHTML = '';
                }
                var loadingEl = document.getElementById('loading-container');
                if (loadingEl) {
                    loadingEl.style.display = 'none';
                }
            } else if (window.TimelineJS && typeof window.TimelineJS.cargarFormularioConsulta === 'function') {
                window.TimelineJS.cargarFormularioConsulta();
            }
        } catch (e) {
            renderBadges('tl_condiciones_activas', [], 'border border-info text-info');
            renderBadges('tl_condiciones_cronicas', [], 'border border-warning text-warning');
            renderBadges('tl_hallazgos', [], 'border border-warning text-warning');
            renderBadges('tl_antecedentes', [], 'border border-gray text-gray');
            renderMotivos(null, null);
            renderMotivosIntake(null);
            renderCarePackCohorte(null);
            renderDocumentacionMedico(null);
            var boxMsgsErr = document.getElementById('tl_motivos_consulta_mensajes');
            if (boxMsgsErr) boxMsgsErr.innerHTML = '';
            if (window.TimelineJS && typeof window.TimelineJS.applySignosVitalesPayload === 'function') {
                window.TimelineJS.applySignosVitalesPayload(null);
            }
            var formBoxErr = document.getElementById('formulario-container');
            if (formBoxErr && timelineConfig.vistaConsultaCargada) {
                formBoxErr.innerHTML = '<div class="alert alert-warning mb-0">' +
                    (e && e.message ? String(e.message) : 'No se pudo cargar la consulta.') +
                    '</div>';
            }
            var loadingErr = document.getElementById('loading-container');
            if (loadingErr) loadingErr.style.display = 'none';
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