<?php

use yii\helpers\Html;
use yii\helpers\Json;
use yii\bootstrap5\Modal;

/**
 * @var yii\web\View $this
 * @var array $page view model {@see \frontend\components\Clinical\PacienteHistoriaTimelinePageBuilder}
 */

$esContextoInternacion = (bool) $page['esContextoInternacion'];
$esContextoGuardia = (bool) $page['esContextoGuardia'];
$esContextoEpisodio = (bool) $page['esContextoEpisodio'];
$mostrarMotivosAmbulatorios = (bool) $page['mostrarMotivosAmbulatorios'];
$mostrarCurvasCrecimiento = (bool) $page['mostrarCurvasCrecimiento'];
$timelineEpisodioGroups = $page['timelineEpisodioGroups'];
$timelineEpisodioItemCount = $page['timelineEpisodioItemCount'];
$episodioBannerAccent = (string) $page['episodioBannerAccent'];
$episodioTipoData = (string) $page['episodioTipoData'];
$tlConfigAttr = Json::htmlEncode($page['jsConfig']);

?>

<div class="container-fluid py-2 px-3" id="tl-page-root" data-tl-config="<?= $tlConfigAttr ?>">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="tl-btn-volver">
                <i class="bi bi-arrow-left"></i> Volver
            </button>
        </div>
        <div class="fw-bold text-body text-truncate" style="font-size: 1.05rem;" title="<?= Html::encode($page['pageTitle']) ?>">
            <?= Html::encode($page['pageTitle']) ?>
        </div>
    </div>

<?php if ($esContextoEpisodio): ?>
<div id="tl_episodio_banner" class="mb-3" hidden data-episodio-tipo="<?= Html::encode($episodioTipoData) ?>">
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
                        <?php if ($mostrarCurvasCrecimiento) : ?>
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

