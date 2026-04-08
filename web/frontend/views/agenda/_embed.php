<?php

/**
 * Fragmento embebible de Agenda laboral.
 *
 * Requisitos:
 * - El contenedor root define el scope para querySelector de agenda-laboral.js (no IDs globales).
 * - Los assets (css/scheduler.css, js/scheduler.js, js/agenda-laboral.js) deben estar cargados
 *   por el caller (shell SPA o la vista index).
 *
 * @var array<int|string, string> $tiposDia
 */
?>

<div data-native-component="agenda_laboral">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="mb-1">Agenda laboral</h3>
            <div class="text-muted small">
                Editá horarios por servicio. Se guarda automáticamente al modificar un campo.
            </div>
        </div>
        <div class="text-end">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-al-reload>
                Recargar
            </button>
        </div>
    </div>

    <div class="alert alert-danger d-none" data-al-error></div>

    <div class="text-muted" data-al-loading>Cargando agendas…</div>
    <div class="row g-3 d-none" data-al-list></div>

    <template data-al-card-template>
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="fw-semibold al_service"></div>
                    <div class="small al_status text-muted"></div>
                </div>
                <div class="card-body">
                    <?= $this->render('_form', ['tiposDia' => $tiposDia]) ?>
                </div>
            </div>
        </div>
    </template>
</div>

