<?php

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * Solo lectura de una consulta ya documentada (staff).
 * No es historia clínica: no muestra estado actual del paciente.
 *
 * @var \common\models\Person\Persona|null $persona
 * @var string $apiPath
 * @var string $pageTitle
 */

?>

<div class="container-fluid py-3 px-3" id="ver-consulta-root"
     data-api="<?= Html::encode($apiPath) ?>"
     data-url-inicio="<?= Html::encode(Url::to(['/site/index'])) ?>">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="vc-btn-volver">
            <i class="bi bi-arrow-left"></i> Volver
        </button>
        <div class="text-body-secondary small text-truncate" title="<?= Html::encode($pageTitle) ?>">
            <?= Html::encode($pageTitle) ?>
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

<template id="tpl-vc-care-answer">
    <div class="mb-2">
        <strong data-field="question"></strong><br>
        <span data-field="answer"></span>
    </div>
</template>

<template id="tpl-vc-care-notes">
    <p class="mb-0"><em data-field="notes"></em></p>
</template>

<template id="tpl-vc-doc-section">
    <div class="mb-3">
        <div class="fw-semibold" data-field="titulo"></div>
        <ul class="mb-0" data-slot="items"></ul>
    </div>
</template>

<template id="tpl-vc-doc-item">
    <li style="white-space:pre-wrap" data-field="text"></li>
</template>
