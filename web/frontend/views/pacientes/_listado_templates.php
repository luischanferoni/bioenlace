<?php
/**
 * Plantillas HTML del listado de pacientes (turnos / internados / guardia).
 * El JS clona estos <template> y asigna textos y URLs (página web común; sin SPA shell).
 */

use yii\helpers\Html;
use yii\helpers\Url;

$urlInternacionRonda = Url::to(['internacion/ronda'], true);
$urlGuardiaIndex = Url::to(['guardia/index'], true);
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
            <button type="button" class="btn btn-outline-secondary btn-sm" data-role="tablero-refresh">
                <i class="bi bi-arrow-clockwise"></i> Actualizar
            </button>
        </div>
        <div class="card-body p-0" data-slot="guardias-rows"></div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="text-muted small" data-role="tablero-updated"></span>
            <a href="<?= Html::encode($urlGuardiaIndex) ?>" class="btn btn-success btn-sm">Ingresos y libro</a>
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
                    <span class="text-muted" data-field="espera-line"></span>
                    <span class="text-muted d-none" data-field="profesional-line"></span>
                </div>
            </div>
        </div>
        <div class="d-flex flex-column gap-1 ms-2">
            <a class="btn btn-dark btn-sm" href="#" data-role="cta-atender" data-spa-nav="1">Atender</a>
            <button type="button" class="btn btn-outline-primary btn-sm d-none" data-role="cta-triage">Triage</button>
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
