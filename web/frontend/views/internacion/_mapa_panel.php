<?php

use frontend\assets\InternacionMapaAsset;

/** @var array<int, mixed> $pisos_efector */
/** @var array<string, mixed>|null $mapa */
/** @var bool $pacienteInternado */
/** @var string $formAction URL del filtro piso/sala */

InternacionMapaAsset::register($this);

$urlReset = $formAction;
?>
<div class="card mb-4 mt-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <p class="mb-0 fw-semibold">Mapa de camas</p>
            <p class="mb-0 small text-muted">Filtro del plano físico (la ronda de pacientes está arriba).</p>
        </div>
        <div class="row">
            <?= $this->render('_searchPorPisoSala', [
                'pisos_efector' => $pisos_efector,
                'urlReset' => $urlReset,
                'formAction' => $formAction,
            ]) ?>
        </div>
        <div class="mx-auto" style="height: 12px;"></div>
        <div class="row mb-2">
            <?= $this->render('_mapa_camas', [
                'mapa' => $mapa,
                'pacienteInternado' => $pacienteInternado,
            ]) ?>
        </div>
    </div>
</div>
