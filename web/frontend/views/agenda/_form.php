<?php

use yii\helpers\Html;

/**
 * Estructura HTML equivalente a {@see \frontend\views\agenda-rrhhs\_form.php}
 * para edición por tarjeta (una agenda por servicio) con autosave vía API.
 *
 * @var array<int|string, string> $tiposDia mapa id_tipo_dia => nombre
 */
$tiposDia = is_array($tiposDia ?? null) ? $tiposDia : [];
?>
<div class="agenda-rrhh-form al_agenda_card_inner" data-jornada-horas="14">
    <div class="form-group mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3 col-6">
                <label class="form-label small mb-1">Fecha inicio</label>
                <input type="date" class="form-control form-control-sm al_field" data-field="fecha_inicio" />
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small mb-1">Hora inicio</label>
                <input type="time" class="form-control form-control-sm al_field" data-field="hora_inicio" step="300" />
            </div>
            <div class="col-auto pt-3 small text-muted">a</div>
            <div class="col-md-3 col-6">
                <label class="form-label small mb-1">Fecha fin</label>
                <input type="date" class="form-control form-control-sm al_field" data-field="fecha_fin" />
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small mb-1">Hora fin</label>
                <input type="time" class="form-control form-control-sm al_field" data-field="hora_fin" step="300" />
            </div>
        </div>
    </div>

    <div class="form-group mb-3">
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <label class="form-check form-check-inline small mb-0">
                <input class="form-check-input al_field" type="checkbox" data-field="lunes" />
                Lun
            </label>
            <label class="form-check form-check-inline small mb-0">
                <input class="form-check-input al_field" type="checkbox" data-field="martes" />
                Mar
            </label>
            <label class="form-check form-check-inline small mb-0">
                <input class="form-check-input al_field" type="checkbox" data-field="miercoles" />
                Mié
            </label>
            <label class="form-check form-check-inline small mb-0">
                <input class="form-check-input al_field" type="checkbox" data-field="jueves" />
                Jue
            </label>
            <label class="form-check form-check-inline small mb-0">
                <input class="form-check-input al_field" type="checkbox" data-field="viernes" />
                Vie
            </label>
            <label class="form-check form-check-inline small mb-0">
                <input class="form-check-input al_field" type="checkbox" data-field="sabado" />
                Sáb
            </label>
            <label class="form-check form-check-inline small mb-0">
                <input class="form-check-input al_field" type="checkbox" data-field="domingo" />
                Dom
            </label>
        </div>
    </div>

    <div class="form-group mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4 col-12">
                <label class="form-label small mb-1">Tipo de día</label>
                <select class="form-select form-select-sm al_field" data-field="id_tipo_dia">
                    <option value="">—</option>
                    <?php foreach ($tiposDia as $idTipo => $nombre) : ?>
                        <option value="<?= Html::encode((string) $idTipo) ?>"><?= Html::encode($nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-12">
                <label class="form-label small mb-1">Cupo pacientes</label>
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control al_field al_cupo_input" data-field="cupo_pacientes" min="0" step="1" />
                    <span class="input-group-text al_cupo_hint text-muted small" style="min-width:4rem;">—</span>
                </div>
            </div>
            <div class="col-md-4 col-12">
                <label class="form-label small mb-1">Duración slot (min)</label>
                <input type="number" class="form-control form-control-sm al_field" data-field="duracion_slot_minutos" min="1" step="1" />
            </div>
        </div>
    </div>

    <div class="form-group mb-2">
        <label class="form-check small mb-0">
            <input class="form-check-input al_field" type="checkbox" data-field="acepta_consultas_online" />
            Acepta consultas online
        </label>
    </div>

    <hr class="my-3" />

    <div class="text-muted small mb-2">
        Franjas horarias por día (misma grilla que en asignación de servicios). Los valores se guardan como índices de hora (0–23) o como en BD (CSV).
    </div>
    <div class="table-responsive mb-1">
        <table class="al_scheduler_table w-100"></table>
    </div>
    <?php foreach (['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'] as $campoDia) : ?>
        <input type="hidden" class="al_field" data-field="<?= Html::encode($campoDia) ?>" value="" />
    <?php endforeach; ?>
</div>
