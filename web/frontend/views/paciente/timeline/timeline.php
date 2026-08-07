<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\bootstrap5\Modal;

use common\models\Person\Persona;
use common\helpers\TimelineHelper;
use common\models\User;
use common\models\Clinical\Encounter;
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

?>

<div class="container-fluid py-2 px-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="tl-btn-volver">
                <i class="bi bi-arrow-left"></i> Volver
            </button>
            <a class="btn btn-outline-primary btn-sm" href="<?= Html::encode(Url::to(['/site/index'])) ?>">
                Inicio
            </a>
        </div>
        <div class="fw-bold text-body text-truncate" style="font-size: 1.05rem;" title="<?= Html::encode($this->title) ?>">
            <?= Html::encode($this->title) ?>
        </div>
    </div>

<?php if ($esContextoEpisodio): ?>
<div id="tl_episodio_banner" class="tl-episodio-banner mb-3" hidden data-episodio-tipo="<?= Html::encode($esContextoGuardia ? 'GUARDIA' : 'INTERNACION') ?>">
    <div class="tl-episodio-banner__inner">
        <div class="tl-episodio-banner__triage" id="tl_episodio_triage" hidden>
            <span class="tl-episodio-banner__triage-badge" id="tl_episodio_triage_badge"></span>
            <span class="tl-episodio-banner__triage-meta text-muted small" id="tl_episodio_triage_meta"></span>
        </div>
        <div class="tl-episodio-banner__grid">
            <div class="tl-episodio-banner__cell">
                <span class="tl-episodio-banner__label">Episodio</span>
                <span class="tl-episodio-banner__value" id="tl_episodio_titulo">Cargando…</span>
            </div>
            <div class="tl-episodio-banner__cell">
                <span class="tl-episodio-banner__label">Estado</span>
                <span class="tl-episodio-banner__value" id="tl_episodio_estado">—</span>
            </div>
            <div class="tl-episodio-banner__cell">
                <span class="tl-episodio-banner__label">Ubicación</span>
                <span class="tl-episodio-banner__value" id="tl_episodio_ubicacion">—</span>
            </div>
            <div class="tl-episodio-banner__cell">
                <span class="tl-episodio-banner__label">Médico a cargo</span>
                <span class="tl-episodio-banner__value" id="tl_episodio_medico">—</span>
            </div>
            <div class="tl-episodio-banner__cell tl-episodio-banner__cell--wide">
                <span class="tl-episodio-banner__label">Motivo / ingreso</span>
                <span class="tl-episodio-banner__value" id="tl_episodio_motivo">—</span>
            </div>
        </div>
        <div class="tl-episodio-banner__actions mt-2" id="tl_episodio_acciones" hidden></div>
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
                            <p class="mb-2 small text-muted" id="tl_internacion_resumen">Episodio #<?= (int) $parentIdQuery ?> — documentá la evolución del día.</p>
                        </div>
                        <?php endif; ?>

                        <?php if ($esContextoEpisodio): ?>
                        <div class="mb-3 pb-2 border-bottom border-2" id="tl_episodio_sv_section">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <h6 class="mb-0 text-primary"><b>SIGNOS VITALES DEL EPISODIO</b></h6>
                                <span class="small text-muted" id="tl_episodio_sv_count"></span>
                            </div>
                            <div id="tl_episodio_sv_ultimos" class="tl-episodio-sv-ultimos mb-2"></div>
                            <div id="tl_episodio_sv_chart" class="tl-episodio-sv-chart" style="min-height: 220px;"></div>
                            <p class="text-muted small mb-0 d-none" id="tl_episodio_sv_empty">Sin signos vitales registrados en este episodio (triage o enfermería).</p>
                        </div>
                        <?php endif; ?>

                        <?php if ($esContextoEpisodio): ?>
                        <div class="mb-3 pb-2 border-bottom border-2" id="tl_episodio_timeline_section">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <h6 class="mb-0 text-primary"><b>REGISTRO DEL EPISODIO</b></h6>
                                <span class="small text-muted" id="tl_episodio_timeline_count"></span>
                            </div>
                            <div class="tl-episodio-timeline-filters mb-2" id="tl_episodio_timeline_filters" role="group" aria-label="Filtros del registro">
                                <button type="button" class="btn btn-sm btn-outline-secondary active" data-tl-filter="all">Todos</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-tl-filter="clinico">Clínico</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-tl-filter="enfermeria">Enfermería</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-tl-filter="pedidos">Pedidos / lab</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-tl-filter="farmacos">Fármacos</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-tl-filter="circuito">Circuito</button>
                            </div>
                            <div id="tl_episodio_timeline_list" class="tl-episodio-timeline-list">
                                <p class="text-muted mb-0 small">Cargando registro…</p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="mb-3 pb-2 border-bottom border-2" id="tl_documentacion_medico_section" style="display:none;">
                            <h6 class="mb-2 text-primary"><b>Datos cargados</b></h6>
                            <div id="tl_documentacion_medico" class="text-body"></div>
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
                        <?php if ($edad !== null && (int) $edad < 14) : ?>
                            <div id="curvas-crecimiento-content" class="mb-3" style="display: none;"></div>
                        <?php endif; ?>
                    </div>

                    <!-- Formulario de chat inteligente -->
                    <div class="col-12">
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

<?php if ($esContextoGuardia): ?>
Modal::begin([
    'title' => 'Egreso de guardia',
    'id' => 'modal-egreso-guardia',
    'size' => Modal::SIZE_LARGE,
]);
?>
<form id="tl-egreso-guardia-form" class="p-1">
    <input type="hidden" name="guardia_id" value="<?= (int) $parentIdQuery ?>" />
    <div class="row g-2">
        <div class="col-md-6">
            <label class="form-label">Fecha de egreso</label>
            <input type="date" class="form-control" name="fecha_fin" required />
        </div>
        <div class="col-md-6">
            <label class="form-label">Hora</label>
            <input type="text" class="form-control" name="hora_fin" placeholder="HH:MM" required />
        </div>
        <div class="col-12">
            <label class="form-label">Destino / conducta</label>
            <select class="form-select" name="destino_egreso" id="tl-egreso-destino" required></select>
        </div>
        <div class="col-12" id="tl-egreso-derivacion-wrap" style="display:none;">
            <label class="form-label">ID efector de derivación</label>
            <input type="number" class="form-control" name="id_efector_derivacion" min="1" />
            <label class="form-label mt-2">Condiciones de derivación</label>
            <textarea class="form-control" name="condiciones_derivacion" rows="2"></textarea>
        </div>
        <div class="col-12">
            <label class="form-label">Diagnóstico operativo</label>
            <textarea class="form-control" name="diagnostico_operativo" rows="2" required></textarea>
        </div>
        <div class="col-12">
            <label class="form-label">Epicrisis de guardia</label>
            <textarea class="form-control" name="epicrisis" rows="5" required></textarea>
        </div>
        <div class="col-12">
            <label class="form-label">Pautas de alarma (alta domiciliaria)</label>
            <textarea class="form-control" name="pautas_alarma" rows="3"></textarea>
        </div>
        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="checklist_indicaciones" value="1" id="tl-egreso-chk-ind" required />
                <label class="form-check-label" for="tl-egreso-chk-ind">Indicaciones explicadas al paciente o familiar</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="checklist_epicrisis" value="1" id="tl-egreso-chk-epi" required />
                <label class="form-check-label" for="tl-egreso-chk-epi">Epicrisis y destino revisados</label>
            </div>
        </div>
    </div>
    <div class="alert alert-danger mt-3 d-none" id="tl-egreso-error"></div>
    <div class="d-flex justify-content-end gap-2 mt-3">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-danger" id="tl-egreso-submit">Confirmar egreso</button>
    </div>
</form>
<?php
Modal::end();
endif;
?>

<script>
(function() {
    'use strict';

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
        var ubiEl = document.getElementById('tl_episodio_ubicacion');
        var medEl = document.getElementById('tl_episodio_medico');
        var motEl = document.getElementById('tl_episodio_motivo');
        var triageWrap = document.getElementById('tl_episodio_triage');
        var triageBadge = document.getElementById('tl_episodio_triage_badge');
        var triageMeta = document.getElementById('tl_episodio_triage_meta');

        var labelTipo = tipo === 'GUARDIA' ? 'Guardia' : (tipo === 'INTERNACION' ? 'Internación' : 'Episodio');
        if (tituloEl) {
            var parts = [labelTipo + ' #' + (ctx.episodio_id || '—')];
            if (ctx.ingreso_at) parts.push('Ingreso ' + ctx.ingreso_at);
            tituloEl.textContent = parts.join(' · ');
        }
        if (estadoEl) {
            estadoEl.textContent = ctx.estado_label || ctx.estado || 'En curso';
        }
        if (ubiEl) {
            var ubi = (ctx.ubicacion && ctx.ubicacion.label) ? String(ctx.ubicacion.label) : '';
            ubiEl.textContent = ubi !== '' ? ubi : (tipo === 'GUARDIA' ? 'Sin box asignado' : 'Sin cama');
        }
        if (medEl) {
            var med = ctx.equipo && ctx.equipo.medico ? ctx.equipo.medico : null;
            medEl.textContent = (med && med.nombre) ? String(med.nombre) : 'Sin asignar';
        }
        if (motEl) {
            motEl.textContent = ctx.motivo ? String(ctx.motivo) : 'Sin motivo registrado';
        }

        var triage = ctx.triage;
        if (triageWrap && triageBadge) {
            if (triage && triage.level != null) {
                triageWrap.hidden = false;
                var color = triage.level_color || '#6c757d';
                triageBadge.style.backgroundColor = color;
                triageBadge.style.color = '#fff';
                triageBadge.textContent = (triage.level_label || ('Nivel ' + triage.level));
                if (triageMeta) {
                    var metaParts = [];
                    if (triage.scale) metaParts.push(String(triage.scale));
                    if (triage.triaged_at) metaParts.push(String(triage.triaged_at));
                    triageMeta.textContent = metaParts.join(' · ');
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
            html += '<button type="button" class="btn btn-sm btn-danger me-1 mb-1" data-tl-accion="'
                + escMotivosHtml(a.id) + '">'
                + escMotivosHtml(a.label || a.id)
                + '</button>';
        });
        box.innerHTML = html;
        box.querySelectorAll('[data-tl-accion]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-tl-accion');
                if (id === 'egreso_estructurado') {
                    openEgresoGuardiaModal();
                }
            });
        });
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
            var destinos = (payload && payload.data && payload.data.destinos) ? payload.data.destinos : [];
            var sel = document.getElementById('tl-egreso-destino');
            if (sel) {
                sel.innerHTML = '<option value="">Elegí destino…</option>';
                destinos.forEach(function (d) {
                    var opt = document.createElement('option');
                    opt.value = d.value;
                    opt.textContent = d.label;
                    sel.appendChild(opt);
                });
            }
            if (form) {
                var today = new Date();
                var yyyy = today.getFullYear();
                var mm = String(today.getMonth() + 1).padStart(2, '0');
                var dd = String(today.getDate()).padStart(2, '0');
                var fecha = form.querySelector('[name="fecha_fin"]');
                var hora = form.querySelector('[name="hora_fin"]');
                if (fecha && !fecha.value) fecha.value = yyyy + '-' + mm + '-' + dd;
                if (hora && !hora.value) {
                    hora.value = String(today.getHours()).padStart(2, '0') + ':'
                        + String(today.getMinutes()).padStart(2, '0');
                }
            }
        } catch (e) {
            console.warn('No se pudo precargar destinos de egreso', e);
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
        var destSel = document.getElementById('tl-egreso-destino');
        var derWrap = document.getElementById('tl-egreso-derivacion-wrap');
        if (destSel && derWrap) {
            destSel.addEventListener('change', function () {
                derWrap.style.display = destSel.value === 'DERIVACION' ? '' : 'none';
            });
        }
        form.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            var errEl = document.getElementById('tl-egreso-error');
            var submitBtn = document.getElementById('tl-egreso-submit');
            if (errEl) {
                errEl.classList.add('d-none');
                errEl.textContent = '';
            }
            var params = new URLSearchParams();
            fd.forEach(function (v, k) {
                params.append(k, v == null ? '' : String(v));
            });
            ['checklist_indicaciones', 'checklist_epicrisis'].forEach(function (n) {
                if (!form.querySelector('[name="' + n + '"]:checked')) {
                    params.set(n, '0');
                } else {
                    params.set(n, '1');
                }
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
                    throw new Error((payload && payload.message) ? payload.message : 'No se pudo registrar el egreso.');
                }
                var modalEl = document.getElementById('modal-egreso-guardia');
                if (modalEl && window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
                window.location.href = <?= json_encode(Url::to(['/site/index'])) ?>;
            } catch (e) {
                if (errEl) {
                    errEl.textContent = e && e.message ? String(e.message) : 'Error al egresar.';
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
        if (ctx.internacion_id) parts.push('Episodio #' + ctx.internacion_id);
        if (ctx.cama_label) parts.push(ctx.cama_label);
        if (ctx.fecha_inicio) parts.push('Ingreso ' + ctx.fecha_inicio);
        if (ctx.medico && ctx.medico.nombre) parts.push(ctx.medico.nombre);
        resumenEl.textContent = (parts.length ? parts.join(' · ') : 'Internación en curso')
            + ' — documentá la evolución del día.';
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
        var html = '<ul class="tl-episodio-timeline-ul list-unstyled mb-0">';
        items.forEach(function (it) {
            var typeLabel = TL_TYPE_LABEL[it.type] || it.type || 'Hito';
            var actor = (it.actor && it.actor.nombre) ? String(it.actor.nombre) : '';
            html += '<li class="tl-episodio-timeline-item" data-tl-type="' + escMotivosHtml(it.type || '') + '">';
            html += '<div class="tl-episodio-timeline-item__meta">';
            html += '<span class="badge tl-episodio-timeline-badge">' + escMotivosHtml(typeLabel) + '</span>';
            html += '<span class="text-muted small">' + escMotivosHtml(it.occurred_at || '') + '</span>';
            if (actor) {
                html += '<span class="text-muted small">' + escMotivosHtml(actor) + '</span>';
            }
            html += '</div>';
            html += '<div class="tl-episodio-timeline-item__summary">' + escMotivosHtml(it.summary || '') + '</div>';
            html += '</li>';
        });
        html += '</ul>';
        listEl.innerHTML = html;
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
            if (window.TimelineJS && typeof window.TimelineJS.applySignosVitalesPayload === 'function') {
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