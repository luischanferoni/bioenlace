<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\bootstrap5\Modal;

use common\models\Person\Persona;
use common\helpers\TimelineHelper;
use common\models\User;
use common\models\Clinical\Encounter;
use frontend\assets\GuardiaTableroAsset;
use frontend\components\Clinical\EpisodioTimelineViewBuilder;
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

$timelineEpisodio = $timelineEpisodio ?? null;
$timelineEpisodioGroups = $esContextoEpisodio
    ? EpisodioTimelineViewBuilder::groupsFromFeed(is_array($timelineEpisodio) ? $timelineEpisodio : null)
    : [];
$timelineEpisodioItemCount = $esContextoEpisodio
    ? EpisodioTimelineViewBuilder::itemCount(is_array($timelineEpisodio) ? $timelineEpisodio : null)
    : 0;

$historiaClinicaQs = [];
$verConsultaStaffPath = null;
$episodioTimelineHtmlPath = null;
if ($parentUpper === Encounter::PARENT_TURNO && $parentIdQuery > 0) {
    $historiaClinicaQs['turno_id'] = $parentIdQuery;
    $verConsultaStaffPath = '/api/v1/clinical/encounter/ver-consulta-como-staff?'
        . http_build_query(['turno_id' => $parentIdQuery]);
} elseif ($esContextoInternacion) {
    $historiaClinicaQs['parent'] = Encounter::PARENT_INTERNACION;
    $historiaClinicaQs['parent_id'] = $parentIdQuery;
    $episodioTimelineHtmlPath = Url::to([
        '/paciente/episodio-timeline-html',
        'id' => (int) $persona->id_persona,
        'parent' => Encounter::PARENT_INTERNACION,
        'parent_id' => $parentIdQuery,
    ]);
} elseif ($esContextoGuardia) {
    $historiaClinicaQs['parent'] = Encounter::PARENT_GUARDIA;
    $historiaClinicaQs['parent_id'] = $parentIdQuery;
    $episodioTimelineHtmlPath = Url::to([
        '/paciente/episodio-timeline-html',
        'id' => (int) $persona->id_persona,
        'parent' => Encounter::PARENT_GUARDIA,
        'parent_id' => $parentIdQuery,
    ]);
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
                            <div id="tl_episodio_sv_chart" class="w-100 border rounded bg-white p-1 d-none" style="min-height: 220px;"></div>
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
                                <?= $this->render('_episodio_timeline_list', [
                                    'groups' => $timelineEpisodioGroups,
                                    'itemCount' => $timelineEpisodioItemCount,
                                ]) ?>
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

<?= $this->render('_timeline_templates') ?>

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
            verConsultaComoStaff: <?= json_encode($verConsultaStaffPath, JSON_UNESCAPED_SLASHES) ?>,
            episodioTimelineHtml: <?= json_encode($episodioTimelineHtmlPath, JSON_UNESCAPED_SLASHES) ?>
        }
    };

    function bioHeaders() {
        if (typeof window.getBioenlaceApiClientHeaders === "function") {
            return window.getBioenlaceApiClientHeaders();
        }
        return {};
    }

    /** Camino A: clonar <template> del partial; no concatenar HTML de estructura. */
    function tlTpl(id) {
        var tpl = document.getElementById(id);
        if (!tpl) return null;
        return document.importNode(tpl.content, true);
    }

    function tlSet(root, sel, text) {
        var el = root && root.querySelector ? root.querySelector(sel) : null;
        if (el) el.textContent = text == null ? '' : String(text);
    }

    function tlShow(el, on) {
        if (!el) return;
        el.classList.toggle('d-none', !on);
    }

    function tlClear(el) {
        if (el) el.replaceChildren();
    }

    function tlAlert(message, variant) {
        var frag = tlTpl('tpl-tl-alert');
        if (!frag) return null;
        var root = frag.firstElementChild;
        root.classList.add('alert-' + (variant || 'warning'));
        tlSet(frag, '[data-field="message"]', message);
        return frag;
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
        tlClear(el);
        if (!items || !items.length) {
            var empty = tlTpl('tpl-tl-empty-inline');
            if (empty) el.appendChild(empty);
            return;
        }
        var any = false;
        items.forEach(function (x) {
            if (!x || !x.termino) return;
            var frag = tlTpl('tpl-tl-badge');
            if (!frag) return;
            var badge = frag.querySelector('[data-field="termino"]');
            if (badge) {
                badge.className = 'badge me-1 ' + badgeClass;
                badge.textContent = String(x.termino).toUpperCase();
            }
            el.appendChild(frag);
            any = true;
        });
        if (!any) {
            var empty2 = tlTpl('tpl-tl-empty-inline');
            if (empty2) el.appendChild(empty2);
        }
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
        tlClear(box);
        if (!acciones.length) {
            box.hidden = true;
            return;
        }
        box.hidden = false;
        acciones.forEach(function (a) {
            if (!a || !a.id) return;
            var frag = tlTpl('tpl-tl-episodio-accion');
            if (!frag) return;
            var btn = frag.querySelector('button');
            if (!btn) return;
            btn.classList.add(a.id === 'editar_triage' ? 'btn-outline-primary' : 'btn-danger');
            btn.setAttribute('data-tl-accion', String(a.id));
            var apiRoute = '';
            if (a.api && a.api.route) apiRoute = String(a.api.route);
            else if (a.api_route) apiRoute = String(a.api_route);
            if (apiRoute) btn.setAttribute('data-tl-api-route', apiRoute);
            btn.textContent = a.label || a.id;
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-tl-accion');
                if (id === 'egreso_estructurado') {
                    openEgresoGuardiaModal();
                } else if (id === 'editar_triage') {
                    openTriageGuardiaModal(btn.getAttribute('data-tl-api-route'));
                }
            });
            box.appendChild(frag);
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
        contentEl.replaceChildren();
        var spin = tlTpl('tpl-tl-spinner');
        if (spin) contentEl.appendChild(spin);
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
            contentEl.replaceChildren();
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
            var formFrag = tlTpl('tpl-tl-triage-form');
            if (!formFrag) throw new Error('Falta template de triage.');
            var guardiaInput = formFrag.querySelector('[data-field="guardia_id"]');
            if (guardiaInput) guardiaInput.value = String(timelineConfig.parentId);
            var reasonEl = formFrag.querySelector('[data-field="reason"]');
            if (reasonEl) reasonEl.value = reason;
            var bpSys = formFrag.querySelector('[data-field="bp_sys"]');
            if (bpSys) bpSys.value = String(data.bp_sys || '');
            var bpDia = formFrag.querySelector('[data-field="bp_dia"]');
            if (bpDia) bpDia.value = String(data.bp_dia || '');
            var hrEl = formFrag.querySelector('[data-field="hr"]');
            if (hrEl) hrEl.value = String(data.hr || '');
            var levelsSlot = formFrag.querySelector('[data-slot="levels"]');
            if (levelsSlot) {
                [1, 2, 3, 4, 5].forEach(function (n) {
                    var lvlFrag = tlTpl('tpl-tl-triage-level');
                    if (!lvlFrag) return;
                    var input = lvlFrag.querySelector('[data-field="input"]');
                    var label = lvlFrag.querySelector('[data-field="label"]');
                    var id = 'tl-triage-level-' + n;
                    if (input) {
                        input.id = id;
                        input.value = String(n);
                        input.checked = String(n) === level;
                    }
                    if (label) {
                        label.setAttribute('for', id);
                        label.classList.add('guardia-triage-level--' + n);
                        label.textContent = String(n);
                    }
                    levelsSlot.appendChild(lvlFrag);
                });
            }
            contentEl.replaceChildren();
            contentEl.appendChild(formFrag);

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
            contentEl.replaceChildren();
            var alertFrag = tlAlert(e && e.message ? String(e.message) : 'Error al abrir triage.', 'danger');
            if (alertFrag) contentEl.appendChild(alertFrag);
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
            renderSignosVitalesEpisodio(payload.data.signos_vitales_episodio || null);
            await refreshEpisodioTimelineHtml();
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

    var TL_FILTER_TYPES = {
        all: null,
        clinico: ['evolucion_medica', 'triage'],
        enfermeria: ['atencion_enfermeria'],
        pedidos: ['pedido', 'resultado_lab', 'interconsulta'],
        farmacos: ['medicacion', 'administracion'],
        circuito: ['circuito', 'triage']
    };

    var _tlEpisodioFilter = 'all';

    function syncEpisodioTimelineCount() {
        var countEl = document.getElementById('tl_episodio_timeline_count');
        var listEl = document.getElementById('tl_episodio_timeline_list');
        if (!countEl || !listEl) return;
        var src = listEl.querySelector('[data-tl-count-source]');
        var ul = listEl.querySelector('[data-tl-timeline-ul]');
        if (src && src.textContent) {
            countEl.textContent = src.textContent;
            return;
        }
        if (ul) {
            var n = parseInt(ul.getAttribute('data-tl-item-count') || '0', 10) || 0;
            countEl.textContent = n ? (n + ' hito' + (n === 1 ? '' : 's')) : '';
            return;
        }
        countEl.textContent = '';
    }

    function applyEpisodioTimelineFilter() {
        var listEl = document.getElementById('tl_episodio_timeline_list');
        if (!listEl) return;
        var allowed = TL_FILTER_TYPES[_tlEpisodioFilter] || null;
        var items = listEl.querySelectorAll('[data-tl-type]');
        var visible = 0;
        items.forEach(function (li) {
            var type = li.getAttribute('data-tl-type') || '';
            var show = !allowed || allowed.indexOf(type) !== -1;
            li.classList.toggle('d-none', !show);
            if (show) visible += 1;
        });
        var emptyAll = listEl.querySelector('[data-tl-empty-all]');
        var emptyFilter = listEl.querySelector('[data-tl-empty-filter]');
        var ul = listEl.querySelector('[data-tl-timeline-ul]');
        if (emptyFilter) {
            var showFilterEmpty = !emptyAll && items.length > 0 && visible === 0;
            emptyFilter.classList.toggle('d-none', !showFilterEmpty);
        }
        if (ul) {
            ul.classList.toggle('d-none', visible === 0 && !!allowed);
        }
        syncEpisodioTimelineCount();
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
            applyEpisodioTimelineFilter();
        });
        applyEpisodioTimelineFilter();
    }

    async function refreshEpisodioTimelineHtml() {
        var endpoints = timelineConfig.endpoints || {};
        var url = endpoints.episodioTimelineHtml;
        var listEl = document.getElementById('tl_episodio_timeline_list');
        if (!url || !listEl) return;
        try {
            var resp = await fetch(url, { headers: bioHeaders(), credentials: 'same-origin' });
            if (!resp.ok) return;
            listEl.innerHTML = await resp.text();
            applyEpisodioTimelineFilter();
        } catch (e) {
            console.warn('No se pudo refrescar el HTML del registro de episodio', e);
        }
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
            tlClear(ultimosEl);
            var order = ['ta', 'fc', 'fr', 'sat_o2', 'temp', 'glucemia', 'glasgow'];
            order.forEach(function (key) {
                var frag = tlTpl('tpl-tl-sv-chip');
                if (!frag) return;
                if (key === 'ta' && ultimos.ta) {
                    var sys = ultimos.ta.sistolica;
                    var dia = ultimos.ta.diastolica;
                    if (sys == null && dia == null) return;
                    tlSet(frag, '[data-field="label"]', 'TA ' + (sys != null ? sys : '—') + '/' + (dia != null ? dia : '—') + ' ');
                    tlSet(frag, '[data-field="at"]', ultimos.ta.at || '');
                    ultimosEl.appendChild(frag);
                    return;
                }
                var u = ultimos[key];
                if (!u || u.value == null) return;
                tlSet(frag, '[data-field="label"]', (u.label || key) + ': ');
                var valEl = frag.querySelector('[data-field="value"]');
                if (valEl) {
                    valEl.classList.remove('d-none');
                    valEl.textContent = String(u.value);
                }
                tlSet(frag, '[data-field="unit"]', u.unit ? (' ' + u.unit + ' ') : ' ');
                tlSet(frag, '[data-field="at"]', u.at || '');
                ultimosEl.appendChild(frag);
            });
        }

        if (!series.length) {
            if (chartEl) {
                if (typeof Plotly !== 'undefined' && chartEl.data) {
                    try { Plotly.purge(chartEl); } catch (ePurge) {}
                }
                tlClear(chartEl);
                chartEl.classList.add('d-none');
            }
            var hasUltimos = !!(ultimosEl && ultimosEl.childElementCount > 0);
            if (emptyEl) emptyEl.classList.toggle('d-none', hasUltimos);
            return;
        }
        if (emptyEl) emptyEl.classList.add('d-none');
        if (chartEl) chartEl.classList.remove('d-none');

        if (!chartEl || typeof Plotly === 'undefined') {
            return;
        }

        // Plotly no acepta clases CSS: se leen tokens Bootstrap (--bs-*) del tema.
        var metricBsToken = {
            ta_sys: 'danger',
            ta_dia: 'warning',
            fc: 'primary',
            fr: 'info',
            sat_o2: 'info',
            temp: 'warning',
            glucemia: 'success',
            glasgow: 'secondary'
        };
        var rootStyles = getComputedStyle(document.documentElement);
        function bsColor(token) {
            if (!token) return undefined;
            var v = rootStyles.getPropertyValue('--bs-' + token).trim();
            return v || undefined;
        }
        var traces = series.map(function (s) {
            var xs = (s.points || []).map(function (p) { return p.at; });
            var ys = (s.points || []).map(function (p) { return p.value; });
            return {
                x: xs,
                y: ys,
                type: 'scatter',
                mode: 'lines+markers',
                name: (s.label || s.metric) + (s.unit ? ' (' + s.unit + ')' : ''),
                line: { color: bsColor(metricBsToken[s.metric]), width: 2 },
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

    function renderMotivos(texto, mp) {
        var el = document.getElementById('tl_motivos_consulta');
        if (!el) return;
        tlClear(el);
        if (timelineConfig.modoCaptura === 'imp' || timelineConfig.modoCaptura === 'emer') {
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

        if (resumen !== '') {
            var wrap = tlTpl('tpl-tl-motivos-resumen-wrap');
            if (!wrap) return;
            var slot = wrap.querySelector('[data-slot="resumen"]');
            var parts = resumen.split(/(\[imagen\d+\])/g);
            parts.forEach(function (part) {
                var m = part.match(/^\[(imagen\d+)\]$/);
                if (m && imgsByRef[m[1]]) {
                    var imgFrag = tlTpl('tpl-tl-motivos-img');
                    if (imgFrag) {
                        var img = imgFrag.querySelector('[data-field="img"]');
                        if (img) {
                            img.setAttribute('data-secure-src', imgsByRef[m[1]]);
                            img.alt = m[1];
                        }
                        slot.appendChild(imgFrag);
                    }
                } else if (part) {
                    slot.appendChild(document.createTextNode(part));
                }
            });
            var sug = mp.sugerencias_clinicas;
            var sugSlot = wrap.querySelector('[data-slot="sugerencias"]');
            if (sugSlot && sug && (sug.diagnosticos_sugeridos || sug.practicas_sugeridas)) {
                var sugFrag = tlTpl('tpl-tl-motivos-sugerencias');
                if (sugFrag) {
                    if (sug.diagnosticos_sugeridos && sug.diagnosticos_sugeridos.length) {
                        var dWrap = sugFrag.querySelector('[data-slot="diagnosticos"]');
                        var dList = sugFrag.querySelector('[data-slot="diagnosticos-list"]');
                        tlShow(dWrap, true);
                        sug.diagnosticos_sugeridos.forEach(function (d) {
                            var li = tlTpl('tpl-tl-li-text');
                            if (li) {
                                tlSet(li, '[data-field="text"]', d.termino || '');
                                dList.appendChild(li);
                            }
                        });
                    }
                    if (sug.practicas_sugeridas && sug.practicas_sugeridas.length) {
                        var pWrap = sugFrag.querySelector('[data-slot="practicas"]');
                        var pList = sugFrag.querySelector('[data-slot="practicas-list"]');
                        tlShow(pWrap, true);
                        sug.practicas_sugeridas.forEach(function (p) {
                            var li = tlTpl('tpl-tl-li-text');
                            if (li) {
                                tlSet(li, '[data-field="text"]', p.termino || '');
                                pList.appendChild(li);
                            }
                        });
                    }
                    sugSlot.appendChild(sugFrag);
                }
            }
            el.appendChild(wrap);
            hydrateSecureTimelineMedia(el);
            return;
        }

        var muted = tlTpl('tpl-tl-motivos-muted');
        if (muted) {
            tlSet(muted, '[data-field="message"]',
                (mp.resumen_pendiente || mp.resumen_ia_pendiente)
                    ? 'Generando resumen…'
                    : 'Sin motivos registrados para esta consulta.');
            el.appendChild(muted);
        }
    }

    function renderMotivosIntake(intake) {
        var section = document.getElementById('tl_motivos_intake_section');
        var el = document.getElementById('tl_motivos_intake');
        if (!section || !el) return;
        tlClear(el);
        if (!intake || typeof intake !== 'object') {
            section.style.display = 'none';
            return;
        }
        var answers = intake.answers || [];
        var notes = intake.notes_for_staff ? String(intake.notes_for_staff).trim() : '';
        var status = intake.status ? String(intake.status) : '';
        if (!notes && (!answers || !answers.length) && status !== 'pending') {
            section.style.display = 'none';
            return;
        }
        section.style.display = '';
        var root = tlTpl('tpl-tl-intake-root');
        if (!root) return;
        if (intake.title) {
            var titleEl = root.querySelector('[data-slot="title"]');
            tlShow(titleEl, true);
            tlSet(root, '[data-field="title"]', intake.title);
        }
        if (notes !== '') {
            tlShow(root.querySelector('[data-slot="notes-wrap"]'), true);
            tlSet(root, '[data-field="notes"]', notes);
        }
        if (!answers || !answers.length) {
            tlShow(root.querySelector('[data-slot="empty-answers"]'), true);
        } else {
            var ansWrap = root.querySelector('[data-slot="answers-wrap"]');
            var ansSlot = root.querySelector('[data-slot="answers"]');
            tlShow(ansWrap, true);
            answers.forEach(function (a) {
                if (!a) return;
                var row = tlTpl('tpl-tl-intake-answer');
                if (!row) return;
                tlSet(row, '[data-field="question"]', a.question || a.id || '');
                tlSet(row, '[data-field="answer"]', a.answer || '');
                // template wraps dt/dd in div — append children into dl
                while (row.firstChild) {
                    ansSlot.appendChild(row.firstChild);
                }
            });
        }
        el.appendChild(root);
    }

    function renderCarePackCohorte(cohorte) {
        var section = document.getElementById('tl_care_pack_section');
        var el = document.getElementById('tl_care_pack_cohorte');
        if (!section || !el) return;
        tlClear(el);
        if (!cohorte || typeof cohorte !== 'object') {
            section.style.display = 'none';
            return;
        }
        var assistance = cohorte.assistance || {};
        var answers = assistance.answers || [];
        var notes = assistance.notes_for_staff ? String(assistance.notes_for_staff).trim() : '';
        if (!notes && (!answers || !answers.length)) {
            section.style.display = 'none';
            return;
        }
        section.style.display = '';
        var root = tlTpl('tpl-tl-care-pack-root');
        if (!root) return;
        if (cohorte.cohort_key_short) {
            var cohortEl = root.querySelector('[data-slot="cohort"]');
            tlShow(cohortEl, true);
            tlSet(root, '[data-field="cohort"]', 'Cohorte ' + cohorte.cohort_key_short);
        }
        var profile = cohorte.cohort_profile || {};
        var profileParts = ['life_stage', 'sexo', 'motive_cluster', 'jurisdiction']
            .map(function (k) { return profile[k] ? String(profile[k]) : ''; })
            .filter(function (v) { return v !== ''; });
        if (profileParts.length) {
            var profileEl = root.querySelector('[data-slot="profile"]');
            tlShow(profileEl, true);
            tlSet(root, '[data-field="profile"]', profileParts.join(' · '));
        }
        if (notes !== '') {
            tlShow(root.querySelector('[data-slot="notes-wrap"]'), true);
            tlSet(root, '[data-field="notes"]', notes);
        }
        if (answers.length) {
            var aWrap = root.querySelector('[data-slot="answers-wrap"]');
            var aSlot = root.querySelector('[data-slot="answers"]');
            tlShow(aWrap, true);
            answers.forEach(function (a) {
                var li = tlTpl('tpl-tl-care-pack-answer');
                if (!li) return;
                tlSet(li, '[data-field="question"]', a.question || a.id || '');
                tlSet(li, '[data-field="answer"]', a.answer || '');
                aSlot.appendChild(li);
            });
        } else if (assistance.status === 'submitted') {
            tlShow(root.querySelector('[data-slot="empty-submitted"]'), true);
        } else {
            tlShow(root.querySelector('[data-slot="empty-pending"]'), true);
        }
        if (assistance.delta_requested) {
            tlShow(root.querySelector('[data-slot="delta"]'), true);
        }
        el.appendChild(root);
    }

    function renderMotivosPacienteApp(mp) {
        var box = document.getElementById('tl_motivos_consulta_mensajes');
        if (!box) return;
        tlClear(box);
        var msgs = (mp && mp.messages) ? mp.messages : [];
        if (!msgs.length) return;
        var wrap = tlTpl('tpl-tl-mensajes-wrap');
        if (!wrap) return;
        var list = wrap.querySelector('[data-slot="list"]');
        msgs.forEach(function (m) {
            var item = tlTpl('tpl-tl-mensaje-item');
            if (!item) return;
            tlSet(item, '[data-field="meta"]', m.created_at || '');
            var bodySlot = item.querySelector('[data-slot="body"]');
            var t = m.message_type || 'texto';
            if (t === 'texto') {
                var tFrag = tlTpl('tpl-tl-mensaje-texto');
                if (tFrag) {
                    tlSet(tFrag, '[data-field="content"]', m.content || '');
                    bodySlot.appendChild(tFrag);
                }
            } else if (t === 'imagen') {
                var iFrag = tlTpl('tpl-tl-mensaje-imagen');
                if (iFrag) {
                    var img = iFrag.querySelector('[data-field="img"]');
                    if (img) img.setAttribute('data-secure-src', m.content || '');
                    bodySlot.appendChild(iFrag);
                }
            } else if (t === 'audio') {
                var aFrag = tlTpl('tpl-tl-mensaje-audio');
                if (aFrag) {
                    var au = aFrag.querySelector('[data-field="audio"]');
                    if (au) au.setAttribute('data-secure-src', m.content || '');
                    bodySlot.appendChild(aFrag);
                }
            } else {
                bodySlot.appendChild(document.createTextNode(m.content || ''));
            }
            list.appendChild(item);
        });
        box.appendChild(wrap);
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
        tlClear(el);
        if (!doc || !doc.tiene_datos || !doc.secciones || !doc.secciones.length) {
            section.style.display = 'none';
            return;
        }
        section.style.display = '';
        doc.secciones.forEach(function (sec) {
            var secFrag = tlTpl('tpl-tl-doc-section');
            if (!secFrag) return;
            tlSet(secFrag, '[data-field="titulo"]', sec.titulo || '');
            var itemsSlot = secFrag.querySelector('[data-slot="items"]');
            (sec.items || []).forEach(function (item) {
                var li = tlTpl('tpl-tl-doc-item');
                if (!li) return;
                tlSet(li, '[data-field="text"]', item);
                itemsSlot.appendChild(li);
            });
            el.appendChild(secFrag);
        });
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
        tlClear(boxMsgs);
        var signos = document.getElementById('signos-vitales-actuales-content');
        if (signos) {
            tlClear(signos);
            var solo = tlTpl('tpl-tl-solo-lectura');
            if (solo) signos.appendChild(solo);
        }
        var formBox = document.getElementById('formulario-container');
        tlClear(formBox);
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
            tlClear(boxMsgs);
            if (!esEpisodio && window.TimelineJS && typeof window.TimelineJS.applySignosVitalesPayload === 'function') {
                window.TimelineJS.applySignosVitalesPayload(payload.data.signos_vitales || null);
            }
            // Captura solo si el turno lo permite; no invocar formulario-consulta en solo lectura.
            var captura = payload.data.captura || {};
            var formBox = document.getElementById('formulario-container');
            if (captura.permitida === false) {
                tlClear(formBox);
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
            tlClear(boxMsgsErr);
            if (window.TimelineJS && typeof window.TimelineJS.applySignosVitalesPayload === 'function') {
                window.TimelineJS.applySignosVitalesPayload(null);
            }
            var formBoxErr = document.getElementById('formulario-container');
            if (formBoxErr && timelineConfig.vistaConsultaCargada) {
                tlClear(formBoxErr);
                var errAlert = tlAlert(e && e.message ? String(e.message) : 'No se pudo cargar la consulta.', 'warning');
                if (errAlert) formBoxErr.appendChild(errAlert);
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
        const episodioSvSection = document.getElementById('tl_episodio_sv_section');
        const formularioContainer = document.getElementById('formulario-container');
        
        if (!signosVitalesContent && !episodioSvSection && !formularioContainer) {
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
    if (timelineConfig.modoCaptura === 'imp' || timelineConfig.modoCaptura === 'emer') {
        bindTimelineEpisodioFilters();
    }
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