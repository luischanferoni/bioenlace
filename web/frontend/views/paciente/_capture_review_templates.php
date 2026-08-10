<?php
/**
 * Templates del panel capture_review (Camino A: markup en PHP, JS clona/rellena).
 */
?>

<template id="tpl-capture-panel">
    <div class="capture-review-panel">
        <div class="text-center mb-3">
            <span class="fw-semibold text-decoration-underline">Nota de esta atención</span>
        </div>
        <div data-slot="body"></div>
    </div>
</template>

<template id="tpl-capture-text-block">
    <div class="mb-3">
        <div class="fw-semibold mb-1" data-field="title"></div>
        <div data-field="body"></div>
    </div>
</template>

<template id="tpl-capture-text-block-html">
    <div class="mb-3">
        <div class="fw-semibold mb-1" data-field="title"></div>
        <div class="texto-formateado" data-field="body"></div>
    </div>
</template>

<template id="tpl-capture-alert">
    <div class="alert mb-3" role="alert" data-field="message"></div>
</template>

<template id="tpl-capture-system-error">
    <div class="alert alert-danger" role="alert">
        <h6 class="alert-heading"><i class="bi bi-exclamation-triangle-fill"></i> Error en el procesamiento</h6>
        <p class="mb-0" data-field="texto"></p>
        <div class="d-none" data-slot="detalle">
            <hr>
            <p class="mb-0"><strong>Recomendación:</strong> <span data-field="detalle"></span></p>
        </div>
    </div>
</template>

<template id="tpl-capture-resultado-title">
    <div class="fw-semibold mb-2">Resultado del procesamiento</div>
</template>

<template id="tpl-capture-category">
    <div class="mb-3">
        <div class="small fw-semibold mb-1">
            <span data-field="title"></span>
            <span class="badge bg-danger d-none" data-slot="required">Requerido</span>
        </div>
        <div data-slot="items"></div>
    </div>
</template>

<template id="tpl-capture-cat-empty">
    <p data-field="message"></p>
</template>

<template id="tpl-capture-item-block">
    <div class="capture-review-item-block mb-2" data-capture-item-block="">
        <div data-slot="chip"></div>
        <div class="capture-review-item-issues mt-2 d-none" data-slot="issues">
            <div class="small fw-semibold text-danger mb-1">Completar datos</div>
            <div class="row g-2" data-slot="issues-row"></div>
        </div>
        <div class="text-danger small mt-1 d-none" data-slot="incomplete-msg" data-field="incomplete-msg"></div>
    </div>
</template>

<template id="tpl-capture-item-chip">
    <button type="button" class="btn btn-sm capture-review-item me-1 mb-1" data-capture-item-id="" data-incomplete="0" data-ai-suggestion="0" aria-pressed="false">
        <i class="bi bi-plus-circle me-1" data-field="icon"></i><span data-field="label"></span>
    </button>
</template>

<template id="tpl-capture-ai-wrap">
    <div class="mb-2">
        <div data-slot="item"></div>
        <span class="text-info small">Sugerido por IA</span>
    </div>
</template>

<template id="tpl-capture-issue">
    <div class="col-12 col-md-6 col-lg-4 mb-2" data-capture-issue-id="">
        <div class="small mb-1 d-none" data-slot="field" data-field="field"></div>
        <div class="d-flex flex-wrap gap-1 mb-1 d-none" data-slot="options"></div>
        <input type="text" class="form-control form-control-sm capture-issue-custom d-none" data-issue-id="" data-field="custom" placeholder="">
    </div>
</template>

<template id="tpl-capture-issue-option">
    <button type="button" class="btn btn-sm btn-outline-secondary capture-issue-option me-1 mb-1" data-issue-id="" data-issue-value="" aria-pressed="false" data-field="label"></button>
</template>

<template id="tpl-capture-issues-section">
    <div class="capture-review-issues mb-3">
        <div class="fw-semibold mb-2 text-danger">Completar datos</div>
        <div class="row g-2" data-slot="issues"></div>
    </div>
</template>

<template id="tpl-capture-open-problems">
    <div class="capture-open-problems mb-3">
        <div class="fw-semibold mb-2">Problemas y tratamientos abiertos</div>
        <p class="small text-muted mb-2">Opcional: indicá el estado al cerrar. Si no elegís, se mantienen como están.</p>
        <div class="row g-2" data-slot="items"></div>
    </div>
</template>

<template id="tpl-capture-open-problem-item">
    <div class="col-12 col-md-6 col-lg-4 mb-3" data-open-problem-kind="" data-open-problem-id="">
        <div class="small fw-semibold" data-field="label"></div>
        <div class="text-muted small d-none" data-slot="detail" data-field="detail"></div>
        <div class="text-muted small mb-1 d-none" data-slot="status" data-field="status"></div>
        <div class="d-flex flex-wrap gap-1 d-none" data-slot="options"></div>
    </div>
</template>

<template id="tpl-capture-open-problem-option">
    <button type="button" class="btn btn-sm btn-outline-secondary capture-open-problem-option me-1 mb-1" data-problem-value="" aria-pressed="false" data-field="label"></button>
</template>
