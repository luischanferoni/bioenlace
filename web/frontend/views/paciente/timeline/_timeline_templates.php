<?php
/**
 * Templates HTML de la HC (camino A: markup en PHP, JS solo clona/rellena).
 */
?>

<template id="tpl-tl-badge">
    <span class="badge me-1" data-field="termino"></span>
</template>

<template id="tpl-tl-empty-inline">
    <span class="ms-2" data-field="message">Sin datos</span>
</template>

<template id="tpl-tl-episodio-accion">
    <button type="button" class="btn btn-sm me-1 mb-1" data-tl-accion="" data-field="label"></button>
</template>

<template id="tpl-tl-spinner">
    <div class="text-center py-4">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando…</span>
        </div>
    </div>
</template>

<template id="tpl-tl-alert">
    <div class="alert mb-0" role="alert" data-field="message"></div>
</template>

<template id="tpl-tl-motivos-resumen-wrap">
    <div>
        <div class="mb-2"><span class="small text-uppercase text-muted">Resumen</span></div>
        <div class="text-body tl-motivos-resumen text-break" style="white-space:pre-wrap" data-slot="resumen"></div>
        <div data-slot="sugerencias"></div>
    </div>
</template>

<template id="tpl-tl-motivos-img">
    <div class="my-2">
        <img class="tl-motivos-secure-media img-fluid rounded" style="max-height:220px" data-secure-src="" alt="" data-field="img">
    </div>
</template>

<template id="tpl-tl-motivos-muted">
    <p class="text-muted mb-0" data-field="message"></p>
</template>

<template id="tpl-tl-motivos-sugerencias">
    <div class="mt-3">
        <div class="small text-uppercase text-muted">Orientación preliminar</div>
        <div class="d-none" data-slot="diagnosticos">
            <div class="fw-semibold mt-2">Diagnósticos a considerar</div>
            <ul class="mb-1" data-slot="diagnosticos-list"></ul>
        </div>
        <div class="d-none" data-slot="practicas">
            <div class="fw-semibold mt-2">Prácticas / estudios</div>
            <ul class="mb-0" data-slot="practicas-list"></ul>
        </div>
    </div>
</template>

<template id="tpl-tl-li-text">
    <li data-field="text"></li>
</template>

<template id="tpl-tl-intake-root">
    <div>
        <div class="small text-muted mb-2 d-none" data-slot="title" data-field="title"></div>
        <div class="d-none" data-slot="notes-wrap">
            <div class="small text-uppercase text-muted">Orientación</div>
            <p class="mb-2 text-break" style="white-space:pre-wrap" data-field="notes"></p>
        </div>
        <p class="text-muted mb-0 d-none" data-slot="empty-answers">El paciente aún no completó las preguntas previas.</p>
        <div class="d-none" data-slot="answers-wrap">
            <div class="small text-uppercase text-muted">Respuestas del paciente</div>
            <dl class="mb-0 mt-2" data-slot="answers"></dl>
        </div>
    </div>
</template>

<template id="tpl-tl-intake-answer">
    <dt class="fw-semibold" data-field="question"></dt>
    <dd class="mb-2 text-break" style="white-space:pre-wrap" data-field="answer"></dd>
</template>

<template id="tpl-tl-care-pack-root">
    <div>
        <div class="small text-muted mb-2 d-none" data-slot="cohort" data-field="cohort"></div>
        <div class="mb-2 d-none" data-slot="profile" data-field="profile"></div>
        <div class="d-none" data-slot="notes-wrap">
            <div class="small text-uppercase text-muted">Orientación</div>
            <p class="mb-2 text-break" style="white-space:pre-wrap" data-field="notes"></p>
        </div>
        <div class="d-none" data-slot="answers-wrap">
            <div class="small text-uppercase text-muted">Respuestas del paciente</div>
            <ul class="mb-0" data-slot="answers"></ul>
        </div>
        <p class="text-muted mb-0 d-none" data-slot="empty-submitted">Respuestas registradas.</p>
        <p class="text-muted mb-0 d-none" data-slot="empty-pending">El paciente aún no completó el cuestionario.</p>
        <p class="mt-2 mb-0 d-none" data-slot="delta">
            <span class="badge text-bg-warning">Requiere adaptación del pack</span>
        </p>
    </div>
</template>

<template id="tpl-tl-care-pack-answer">
    <li class="mb-2">
        <strong data-field="question"></strong><br>
        <span data-field="answer"></span>
    </li>
</template>

<template id="tpl-tl-mensajes-wrap">
    <div class="border rounded p-2 bg-light">
        <div class="small text-uppercase text-primary fw-bold mb-2">Mensajes del paciente (app)</div>
        <ul class="list-unstyled mb-0" data-slot="list"></ul>
    </div>
</template>

<template id="tpl-tl-mensaje-item">
    <li class="mb-2 pb-2 border-bottom border-light">
        <div class="small text-muted" data-field="meta"></div>
        <div data-slot="body"></div>
    </li>
</template>

<template id="tpl-tl-mensaje-texto">
    <span class="text-break" style="white-space:pre-wrap" data-field="content"></span>
</template>

<template id="tpl-tl-mensaje-imagen">
    <img class="tl-motivos-secure-media img-fluid rounded" style="max-height:220px" data-secure-src="" alt="Imagen adjunta" data-field="img">
</template>

<template id="tpl-tl-mensaje-audio">
    <audio class="tl-motivos-secure-media w-100" controls preload="none" data-secure-src="" data-field="audio"></audio>
</template>

<template id="tpl-tl-doc-section">
    <div class="mb-3">
        <div class="fw-semibold" data-field="titulo"></div>
        <ul class="mb-0" data-slot="items"></ul>
    </div>
</template>

<template id="tpl-tl-doc-item">
    <li class="text-break" style="white-space:pre-wrap" data-field="text"></li>
</template>

<template id="tpl-tl-sv-chip">
    <span class="badge text-bg-light border me-1 mb-1">
        <span data-field="label"></span>
        <strong data-field="value" class="d-none"></strong>
        <span data-field="unit"></span>
        <span class="text-muted" data-field="at"></span>
    </span>
</template>

<template id="tpl-tl-solo-lectura">
    <span class="text-muted">Consulta en solo lectura</span>
</template>

<template id="tpl-tl-triage-form">
    <form id="tl-triage-form" class="p-1">
        <input type="hidden" name="guardia_id" data-field="guardia_id" value="">
        <label class="form-label">Prioridad (Manchester)</label>
        <div class="d-flex flex-wrap gap-2 mb-3" role="group" data-slot="levels"></div>
        <label class="form-label">Motivo de consulta</label>
        <textarea class="form-control mb-2" name="reason_text" rows="3" required data-field="reason"></textarea>
        <div class="row g-2 mb-2">
            <div class="col-4">
                <label class="form-label">TA sys</label>
                <input class="form-control" name="bp_sys" data-triage-vital="bp_sys" inputmode="numeric" maxlength="3" data-field="bp_sys" value="">
            </div>
            <div class="col-4">
                <label class="form-label">TA dia</label>
                <input class="form-control" name="bp_dia" data-triage-vital="bp_dia" inputmode="numeric" maxlength="3" data-field="bp_dia" value="">
            </div>
            <div class="col-4">
                <label class="form-label">FC</label>
                <input class="form-control" name="hr" data-triage-vital="hr" inputmode="numeric" maxlength="3" data-field="hr" value="">
            </div>
        </div>
        <p class="small text-muted mb-2">TA y FC: enteros de 2–3 dígitos (opcionales).</p>
        <div class="alert alert-danger d-none" id="tl-triage-error"></div>
        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="tl-triage-submit">Guardar</button>
        </div>
    </form>
</template>

<template id="tpl-tl-triage-level">
    <span>
        <input type="radio" class="btn-check" name="level" autocomplete="off" required data-field="input">
        <label class="btn btn-sm btn-outline-secondary fw-bold guardia-triage-level" data-field="label"></label>
    </span>
</template>
