<?php
/**
 * Plantillas HTML del listado de pacientes (turnos / internados / guardia).
 * El JS clona estos <template> y asigna textos y URLs (página web común; sin SPA shell).
 */

use yii\helpers\Html;
use yii\helpers\Url;

$urlInternacionRonda = Url::to(['internacion/ronda'], true);
?>

<template id="tpl-pacientes-alert-empty">
    <div class="alert alert-secondary">
        <i class="bi bi-info-circle me-2"></i><span data-field="message"></span>
    </div>
</template>

<template id="tpl-pacientes-turnos-wrap">
    <div class="row" data-role="turnos-grid"></div>
</template>

<template id="tpl-paciente-turno">
    <div class="col-md-6 col-lg-4 mb-3">
        <div class="card h-100 shadow-sm position-relative" data-role="turno-card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-person-circle text-primary me-2"></i><span data-field="nombre"></span>
                </h5>
                <div class="mb-2">
                    <strong><i class="bi bi-clock me-2"></i>Turno:</strong> <span data-field="hora"></span>
                </div>
                <div class="mb-2">
                    <strong><i class="bi bi-hospital me-2"></i>Servicio:</strong> <span data-field="servicio"></span>
                </div>
                <div class="mb-2 d-none" data-slot="observaciones">
                    <strong><i class="bi bi-chat-left-text me-2"></i>Observaciones:</strong>
                    <small class="text-muted" data-field="observaciones"></small>
                </div>
                <div class="mt-3"><span class="badge" data-field="estado-badge"></span></div>
                <a href="#" class="stretched-link" data-role="link-historia" data-spa-nav="1" data-spa-title="Historia clínica" aria-label="Ver historia clínica"></a>
            </div>
        </div>
    </div>
</template>

<template id="tpl-pacientes-internados-wrap">
    <div class="card" data-role="internados-wrap">
        <div class="card-header">
            <h4 class="mb-0">Pacientes internados</h4>
        </div>
        <div class="card-body" data-slot="internados-rows"></div>
        <div class="card-footer">
            <a href="<?= Html::encode($urlInternacionRonda) ?>" class="btn btn-primary">Ronda de internación</a>
        </div>
    </div>
</template>

<template id="tpl-paciente-internado-row">
    <div class="d-flex align-items-center p-3 mb-2 bg-soft-gray rounded" data-role="internado-row">
        <div class="ms-3" style="flex:1;">
            <h5 class="card-title mb-0" data-field="nombre"></h5>
            <p class="mb-1">
                <strong>Piso:</strong> <span data-field="piso"></span>
                <strong>Sala:</strong> <span data-field="sala"></span>
                <strong>Cama:</strong> <span data-field="cama"></span>
            </p>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <a href="#" class="p-2 btn btn-success btn-sm" data-role="link-atender" data-spa-nav="1">Atender paciente</a>
                <a href="#" class="p-2 btn btn-outline-primary btn-sm d-none" data-role="link-historia" data-spa-nav="1" data-spa-title="Historia clínica">Historia clínica</a>
            </div>
        </div>
    </div>
</template>

<template id="tpl-pacientes-guardias-wrap">
    <div class="card" data-role="guardias-wrap">
        <div class="card-header bg-light d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h4 class="mb-0">Tablero de guardia</h4>
                <p class="text-muted small mb-0 d-none" data-role="tablero-resumen"></p>
            </div>
            <div class="d-flex gap-1">
                <a href="#" class="btn btn-outline-secondary btn-sm" data-role="tablero-export-csv" download>Exportar CSV</a>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-role="tablero-refresh">
                    <i class="bi bi-arrow-clockwise"></i> Actualizar
                </button>
            </div>
        </div>
        <div class="card-body p-0" data-slot="guardias-rows"></div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="text-muted small" data-role="tablero-updated"></span>
        </div>
    </div>
</template>

<template id="tpl-paciente-guardia-row">
    <div class="d-flex align-items-center justify-content-between p-3 mb-0 border-bottom guardia-tablero-row" data-role="guardia-row">
        <div class="d-flex align-items-start gap-3 flex-grow-1">
            <span class="badge guardia-tablero-badge-nivel" data-field="nivel-badge">—</span>
            <div style="flex:1;">
                <h5 class="mb-1" data-field="nombre"></h5>
                <p class="mb-1 small text-muted"><span data-field="documento-line"></span></p>
                <p class="mb-1 small" data-field="motivo-line"></p>
                <div class="d-flex flex-wrap gap-2 small">
                    <span class="badge bg-secondary" data-field="circuito-badge"></span>
                    <span class="badge bg-danger d-none" data-field="sla-badge">SLA</span>
                    <span class="badge bg-info text-dark d-none" data-field="internacion-badge">Cama pendiente</span>
                    <span class="text-muted" data-field="espera-line"></span>
                    <span class="text-muted d-none" data-field="profesional-line"></span>
                </div>
                <p class="mb-1 small text-muted d-none" data-field="clinical-line"></p>
            </div>
        </div>
        <div class="d-flex flex-column gap-1 ms-2 align-items-stretch" style="min-width: 7.5rem;">
            <a class="btn btn-dark btn-sm" href="#" data-role="cta-atender" data-spa-nav="1">Atender</a>
            <button type="button" class="btn btn-outline-primary btn-sm d-none" data-role="cta-triage">Triage</button>
            <button type="button" class="btn btn-outline-secondary btn-sm d-none" data-role="cta-retriage">Actualizar triage</button>
            <button type="button" class="btn btn-outline-success btn-sm d-none" data-role="cta-tomar">Tomar caso</button>
            <button type="button" class="btn btn-outline-warning btn-sm d-none" data-role="cta-derivar">Derivar</button>
            <button type="button" class="btn btn-outline-danger btn-sm d-none" data-role="cta-finalizar">Egreso</button>
            <button type="button" class="btn btn-outline-dark btn-sm d-none" data-role="cta-clinical">Pedidos / Lab</button>
            <button type="button" class="btn btn-outline-info btn-sm d-none" data-role="cta-internacion">Solicitar cama</button>
        </div>
    </div>
</template>

<template id="tpl-pacientes-cirugias-wrap">
    <div class="row" data-role="cirugias-grid"></div>
</template>

<template id="tpl-paciente-cirugia">
    <div class="col-md-6 col-lg-4 mb-3">
        <div class="card h-100 shadow-sm position-relative" data-role="cirugia-card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-person-circle text-primary me-2"></i><span data-field="nombre"></span>
                </h5>
                <div class="mb-2">
                    <strong><i class="bi bi-hospital me-2"></i>Sala:</strong> <span data-field="sala"></span>
                </div>
                <div class="mb-2">
                    <strong><i class="bi bi-clock me-2"></i>Inicio:</strong> <span data-field="inicio"></span>
                </div>
                <div class="mt-3"><span class="badge" data-field="estado-badge"></span></div>
                <a href="#" class="stretched-link" data-role="link-historia" data-spa-nav="1" data-spa-title="Historia clínica" aria-label="Ver historia clínica"></a>
            </div>
        </div>
    </div>
</template>

<template id="tpl-home-action-cards-wrap">
    <div data-role="action-cards-wrap"></div>
</template>

<template id="tpl-home-action-card-category">
    <div class="mb-4" data-role="action-category">
        <h3 class="h6 text-decoration-underline mb-3" data-field="titulo"></h3>
        <div class="d-grid gap-2" data-slot="actions"></div>
    </div>
</template>

<template id="tpl-home-action-card">
    <a href="#" class="btn btn-outline-secondary text-start" data-role="action-link">
        <div class="fw-semibold" data-field="nombre"></div>
        <div class="small text-muted" data-field="descripcion"></div>
    </a>
</template>

<template id="tpl-patient-home-wrap">
    <div data-role="patient-home-wrap">
        <div class="d-none alert alert-warning mb-3" data-role="patient-en-resolucion-banner">
            <strong>Turno en resolución.</strong>
            <span data-field="en-resolucion-texto"></span>
            <a href="#" class="alert-link ms-1" data-role="en-resolucion-cta" data-spa-nav="1">Elegir nuevo horario</a>
        </div>
        <div data-slot="patient-sections"></div>
        <ul class="nav nav-tabs mb-3 mt-2" data-role="patient-turnos-tabs">
            <li class="nav-item">
                <button type="button" class="nav-link active" data-tab="proximos">Próximos turnos</button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-tab="pasados">Historial</button>
            </li>
        </ul>
        <div data-role="patient-tab-proximos">
            <div class="row" data-slot="proximos-grid"></div>
        </div>
        <div class="d-none" data-role="patient-tab-pasados">
            <div data-slot="pasados-list"></div>
            <div class="text-center mt-3">
                <button type="button" class="btn btn-outline-secondary btn-sm d-none" data-role="pasados-load-more">
                    Cargar más
                </button>
            </div>
        </div>
    </div>
</template>

<template id="tpl-patient-home-section">
    <section class="mb-4" data-role="patient-section">
        <h3 class="h5 mb-3" data-field="titulo"></h3>
        <div class="row" data-slot="items"></div>
    </section>
</template>

<template id="tpl-patient-turno-card">
    <div class="col-md-6 col-lg-4 mb-3">
        <div class="card h-100 shadow-sm" data-role="patient-turno-card">
            <div class="card-body d-flex flex-column">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-1 mb-2">
                    <span class="badge d-none" data-field="proximidad-badge"></span>
                    <span class="badge" data-field="estado-badge"></span>
                </div>
                <h5 class="card-title h6 mb-2" data-field="servicio"></h5>
                <div class="mb-1 small"><strong>Fecha:</strong> <span data-field="fecha"></span></div>
                <div class="mb-1 small"><strong>Hora:</strong> <span data-field="hora"></span></div>
                <div class="mb-2 small d-none" data-slot="profesional">
                    <strong>Profesional:</strong> <span data-field="profesional"></span>
                </div>
                <div class="mt-auto pt-2 d-flex flex-wrap gap-2" data-slot="actions"></div>
            </div>
        </div>
    </div>
</template>

<template id="tpl-patient-care-plan-card">
    <div class="col-md-6 col-lg-4 mb-3">
        <div class="card h-100 shadow-sm">
            <div class="card-body d-flex flex-column">
                <h5 class="card-title h6" data-field="titulo"></h5>
                <p class="small text-muted mb-2" data-field="categoria"></p>
                <span class="badge bg-info text-dark mb-2 align-self-start" data-field="estado"></span>
                <ul class="small mb-3 ps-3 d-none" data-slot="actividades"></ul>
                <a href="#" class="btn btn-sm btn-outline-primary mt-auto align-self-start" data-role="link-detalle" data-spa-nav="1">
                    Ver tratamiento
                </a>
            </div>
        </div>
    </div>
</template>

<template id="tpl-patient-turno-list-item">
    <div class="card mb-2 shadow-sm" data-role="patient-turno-list-item">
        <div class="card-body py-2 px-3">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <div class="fw-semibold" data-field="servicio"></div>
                    <div class="small text-muted">
                        <span data-field="fecha"></span>
                        <span data-field="hora-sep" class="d-none"> · </span>
                        <span data-field="hora"></span>
                    </div>
                </div>
                <span class="badge bg-secondary" data-field="estado-badge"></span>
            </div>
        </div>
    </div>
</template>

<template id="tpl-staff-dashboard-wrap">
    <div data-role="staff-dashboard-wrap"></div>
</template>

<template id="tpl-staff-dashboard-context">
    <div class="alert alert-light border mb-4" data-role="staff-context">
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <div><strong data-field="efector"></strong></div>
            <div class="text-muted" data-field="servicio"></div>
            <div class="text-muted" data-field="encounter"></div>
        </div>
        <div class="mt-2 text-muted small d-none" data-field="hint"></div>
    </div>
</template>

<template id="tpl-staff-kpi-group">
    <div class="card mb-4" data-role="kpi-group">
        <div class="card-header">
            <h5 class="mb-0" data-field="title"></h5>
        </div>
        <div class="card-body">
            <div class="row g-3" data-slot="kpi-items"></div>
        </div>
    </div>
</template>

<template id="tpl-staff-kpi-item">
    <div class="col-md-4 col-lg-3">
        <div class="border rounded p-3 h-100 text-center bg-light">
            <div class="text-muted small mb-1" data-field="label"></div>
            <div class="fs-4 fw-semibold" data-field="value"></div>
        </div>
    </div>
</template>

<template id="tpl-clinical-list-panel-wrap">
    <div data-role="clinical-list-panel">
        <div data-slot="kpi-sections" class="mb-3"></div>
        <div data-slot="list-content"></div>
    </div>
</template>
