<?php
/**
 * Plantillas HTML del listado de pacientes (turnos / internados / guardia).
 * El JS clona estos <template> y asigna textos y URLs (página web común; sin SPA shell).
 */
?>

<template id="tpl-pacientes-alert-empty">
    <div class="alert alert-secondary">
        <i class="bi bi-info-circle me-2"></i><span data-field="message"></span>
    </div>
</template>

<template id="tpl-pacientes-turnos-wrap">
    <div data-role="turnos-wrap">
        <div data-slot="turnos-groups"></div>
    </div>
</template>

<template id="tpl-pacientes-turnos-group">
    <section class="mb-4" data-role="turnos-group">
        <h3 class="h5 mb-3" data-field="titulo"></h3>
        <div class="row" data-slot="turnos-grid"></div>
        <div class="d-none" data-slot="empty"></div>
    </section>
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
                <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge" data-field="estado-badge"></span>
                    <span class="badge d-none" data-field="tipo-atencion-badge"></span>
                </div>
                <div class="alert alert-sm mt-3 mb-0 py-2 px-2 small d-none" data-slot="modalidad-insight" role="note">
                    <div class="d-flex gap-2">
                        <i class="bi bi-lightbulb flex-shrink-0" data-field="insight-icon"></i>
                        <div class="flex-grow-1">
                            <p class="mb-1 fw-semibold" data-field="insight-summary"></p>
                            <ul class="mb-1 ps-3" data-slot="insight-modalidades"></ul>
                            <p class="mb-0 text-muted" data-field="insight-footer"></p>
                        </div>
                    </div>
                </div>
                <a href="#" class="stretched-link" data-role="link-historia" data-spa-nav="1" data-spa-title="Historia clínica" aria-label="Ver historia clínica"></a>
            </div>
        </div>
    </div>
</template>

<template id="tpl-pacientes-internados-wrap">
    <div class="card" data-role="internados-wrap">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h4 class="mb-0">Pacientes internados</h4>
            <div class="btn-group btn-group-sm" role="group" aria-label="Orden de la ronda" data-role="internados-orden">
                <button type="button" class="btn btn-outline-secondary active" data-orden="recorrido">Por recorrido</button>
                <button type="button" class="btn btn-outline-secondary ms-2" data-orden="nombre">Por paciente</button>
            </div>
        </div>
        <div class="card-body" data-slot="internados-rows"></div>
    </div>
</template>

<template id="tpl-internados-group-piso">
    <div class="mb-3" data-role="internados-piso">
        <div class="px-2 py-1 mb-2 rounded bg-soft-primary text-primary fw-semibold small" data-field="piso-label"></div>
        <div data-slot="salas"></div>
    </div>
</template>

<template id="tpl-internados-group-sala">
    <div class="mb-3" data-role="internados-sala">
        <div class="px-2 py-1 mb-2 rounded bg-soft-success text-success fw-semibold small" data-field="sala-label"></div>
        <div data-slot="pacientes"></div>
    </div>
</template>

<template id="tpl-paciente-internado-row">
    <div class="d-flex align-items-start justify-content-between gap-3 p-3 mb-2 bg-soft-gray rounded" data-role="internado-row">
        <div class="d-flex align-items-start gap-3 flex-grow-1">
            <div class="flex-shrink-0">
                <span class="badge bg-dark" data-field="cama-badge">Cama</span>
            </div>
            <div class="flex-grow-1">
                <h5 class="card-title mb-0" data-field="nombre"></h5>
                <p class="mb-1 small text-muted d-none" data-slot="documento-line">
                    <span data-field="documento"></span>
                </p>
                <p class="mb-1 small text-muted d-none" data-slot="ubicacion-line">
                    <span data-field="piso"></span>
                    <span aria-hidden="true"> · </span>
                    <span data-field="sala"></span>
                </p>
            </div>
        </div>
        <div class="d-flex flex-column gap-1 ms-2 align-items-stretch" style="min-width: 7.5rem;">
            <a href="#" class="btn btn-dark btn-sm" data-role="cta-atender" data-spa-nav="1" data-spa-title="Historia clínica">Atender</a>
            <button type="button" class="btn btn-outline-info btn-sm" data-role="cta-cambio-cama">Cambio cama</button>
            <button type="button" class="btn btn-outline-danger btn-sm" data-role="cta-alta">Alta</button>
        </div>
    </div>
</template>

<template id="tpl-pacientes-guardias-wrap">
    <div class="card" data-role="guardias-wrap">
        <div class="card-body p-0" data-slot="guardias-rows"></div>
    </div>
</template>

<template id="tpl-paciente-guardia-row">
    <div class="d-flex align-items-center justify-content-between p-3 mb-0 border-bottom guardia-tablero-row" data-role="guardia-row">
        <div class="d-flex align-items-start gap-3 flex-grow-1">
            <div style="flex:1;">
                <h5 class="mb-1" data-field="nombre"></h5>
                <p class="mb-1 small" data-field="motivo-line"></p>
                <div class="d-flex flex-wrap gap-2 small">
                    <span class="badge bg-secondary" data-field="circuito-badge"></span>
                    <span class="badge bg-danger d-none" data-field="sla-badge">Plazo</span>
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
                <div class="mb-2 small d-none" data-slot="modalidad">
                    <strong>Modalidad:</strong> <span data-field="modalidad"></span>
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
                        <span data-field="modalidad-sep" class="d-none"> · </span>
                        <span data-field="modalidad" class="d-none"></span>
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

<template id="tpl-staff-kpi-group">
    <div class="card mb-3" data-role="kpi-group">
        <div class="card-header d-none" data-slot="kpi-title-wrap">
            <h5 class="mb-0" data-field="title"></h5>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2" data-slot="kpi-items"></div>
        </div>
    </div>
</template>

<template id="tpl-staff-kpi-item">
    <div class="border rounded px-2 py-2 bg-white d-flex flex-column" style="min-width: 6rem; max-width: 7.5rem; flex: 1 1 6rem; min-height: 4.75rem;" data-role="kpi-item">
        <div class="text-muted small mb-1" data-field="label" style="line-height: 1.2; min-height: 2.4em; white-space: normal;"></div>
        <div class="fs-5 fw-semibold lh-1 mt-auto" data-field="value"></div>
    </div>
</template>

<template id="tpl-clinical-list-panel-wrap">
    <div data-role="clinical-list-panel">
        <div data-slot="kpi-sections" class="mb-3"></div>
        <div data-slot="async-bandeja" class="mb-4"></div>
        <div data-slot="list-content"></div>
    </div>
</template>

<template id="tpl-async-bandeja-wrap">
    <div data-role="async-bandeja-wrap">
        <div class="d-flex justify-content-end mb-2">
            <small class="text-muted d-none" data-field="sla-resumen"></small>
        </div>
        <div data-slot="async-groups"></div>
    </div>
</template>

<template id="tpl-async-bandeja-group">
    <section class="mb-4" data-role="async-group">
        <h3 class="h5 mb-3" data-field="titulo"></h3>
        <div class="row" data-slot="async-grid"></div>
        <div class="d-none" data-slot="empty"></div>
    </section>
</template>

<template id="tpl-async-solicitud-card">
    <div class="col-md-6 col-lg-4 mb-3">
        <div class="card h-100 shadow-sm position-relative" data-role="async-card">
            <div class="card-body d-flex flex-column">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-1 mb-2">
                    <span class="badge bg-info text-dark d-none" data-field="solicitud-tipo"></span>
                    <span class="badge" data-field="estado-badge"></span>
                </div>
                <h5 class="card-title h6 mb-1">
                    <i class="bi bi-person-circle text-primary me-2"></i><span data-field="paciente"></span>
                </h5>
                <div class="small text-muted mb-2" data-field="servicio"></div>
                <div class="small mb-2"><strong>Solicitado:</strong> <span data-field="created-at"></span></div>
                <div class="small mb-2 d-none" data-slot="intake-context">
                    <div class="fw-semibold mb-1" data-field="intake-title">Contexto</div>
                    <div data-field="intake-tipo" class="text-muted mb-1 d-none"></div>
                    <div data-slot="intake-lines" class="mb-1"></div>
                    <div class="text-muted mb-1 d-none" data-field="intake-summary"></div>
                    <div data-slot="intake-encounter-detail" class="border rounded bg-white p-2 mb-2 d-none"></div>
                    <div data-slot="intake-links"></div>
                </div>
                <p class="small mb-2 flex-grow-1" data-field="preview"></p>
                <div class="small mb-2 d-none" data-slot="sla-alerta">
                    <span class="badge bg-danger" data-field="sla-badge"></span>
                </div>
                <div class="mt-auto d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="badge d-none" data-field="prioridad-badge"></span>
                    <div class="d-flex flex-wrap gap-2 ms-auto justify-content-end" data-slot="actions"></div>
                </div>
            </div>
        </div>
    </div>
</template>

<template id="tpl-patient-async-card">
    <div class="col-md-6 col-lg-4 mb-3">
        <div class="card h-100 shadow-sm position-relative" data-role="patient-async-card">
            <div class="card-body d-flex flex-column">
                <div class="d-flex flex-wrap justify-content-between gap-1 mb-2">
                    <span class="badge bg-info text-dark d-none" data-field="solicitud-tipo"></span>
                    <span class="badge" data-field="estado-badge"></span>
                </div>
                <h5 class="card-title h6 mb-2" data-field="servicio"></h5>
                <div class="small mb-2"><strong>Enviada:</strong> <span data-field="created-at"></span></div>
                <p class="small text-muted mb-2 flex-grow-1" data-field="preview"></p>
                <p class="small mb-3 d-none" data-field="resolucion"></p>
                <div class="d-flex flex-wrap gap-2 mt-auto" data-slot="actions"></div>
            </div>
        </div>
    </div>
</template>
