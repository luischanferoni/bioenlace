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
                <a href="#" class="stretched-link" data-role="link-historia" aria-label="Ver historia clínica"></a>
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
                <a href="#" class="p-2 btn btn-success btn-sm" data-role="link-atender">Atender paciente</a>
                <a href="#" class="p-2 btn btn-outline-primary btn-sm d-none" data-role="link-historia">Historia clínica</a>
            </div>
        </div>
    </div>
</template>

<template id="tpl-pacientes-guardias-wrap">
    <div class="card" data-role="guardias-wrap">
        <div class="card-header bg-light">
            <h4 class="mb-0">Pacientes en guardia</h4>
        </div>
        <div class="card-body" data-slot="guardias-rows"></div>
        <div class="card-footer">
            <a href="<?= Html::encode($urlGuardiaIndex) ?>" class="btn btn-success float-end">Ver todos los ingresos activos</a>
        </div>
    </div>
</template>

<template id="tpl-paciente-guardia-row">
    <div class="d-flex align-items-center justify-content-between p-3 mb-2 bg-soft-gray rounded" data-role="guardia-row">
        <div class="ms-3" style="flex:1;">
            <h5 class="card-title mb-0" data-field="nombre"></h5>
            <p class="mb-1"><span data-field="documento-line"></span></p>
        </div>
        <a class="btn btn-dark btn-sm me-2" href="#" data-role="cta-atender"><i class="bi bi-chevron-right"></i> Atender</a>
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
                <a href="#" class="stretched-link" data-role="link-historia" aria-label="Ver historia clínica"></a>
            </div>
        </div>
    </div>
</template>
