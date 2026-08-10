<?php
/** Panel lateral de alertas (fuera del navbar; position fixed). */
?>
<div
    id="spa-alertas-panel"
    class="spa-alertas-panel"
    aria-hidden="true"
    role="dialog"
    aria-label="Alertas"
>
    <div class="spa-alertas-panel-inner shadow">
        <div class="spa-alertas-panel-header d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
            <span class="fw-semibold">Alertas</span>
            <button type="button" id="spa-alertas-close-btn" class="btn btn-sm btn-link">Cerrar</button>
        </div>
        <div class="spa-alertas-panel-body overflow-auto"></div>
    </div>
</div>

<template id="tpl-alertas-empty">
    <p class="text-muted small mb-0 px-2 py-3" data-field="message">No hay alertas.</p>
</template>

<template id="tpl-alertas-loading">
    <p class="text-muted small px-2 py-3">Cargando…</p>
</template>

<template id="tpl-alertas-list">
    <ul class="list-group list-group-flush bioenlace-alertas-list" data-slot="items"></ul>
</template>

<template id="tpl-alerta-item">
    <li class="list-group-item bioenlace-alerta-item" role="button" tabindex="0" data-notif-id="" data-intent-id="" data-intent-name="">
        <div class="d-block fw-semibold small" data-field="titulo"></div>
        <span class="d-block small text-muted mt-1" data-field="cuerpo"></span>
        <span class="d-block small text-muted mt-1" data-field="fecha"></span>
    </li>
</template>
