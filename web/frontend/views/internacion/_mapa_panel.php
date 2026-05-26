<?php

use frontend\assets\InternacionMapaAsset;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var array<int, mixed> $pisos_efector */
/** @var array<string, mixed>|null $mapa */
/** @var bool $pacienteInternado */
/** @var string $formAction URL del filtro piso/sala */

InternacionMapaAsset::register($this);

$urlReset = $formAction;
?>
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <p class="mb-0 fw-semibold">Mapa de camas</p>
            <div class="d-flex flex-wrap gap-2">
                <?= Html::a(
                    'Ronda de pacientes',
                    ['/internacion/ronda'],
                    ['class' => 'btn btn-sm btn-outline-primary rounded-pill']
                ) ?>
            </div>
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
<?php if ($pacienteInternado): ?>
<?php
$this->registerJs(<<<'JS'
$(function () {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'La persona seleccionada ya se encuentra en internación.',
            backdrop: 'rgba(60,60,60,0.8)',
        });
    }
});
JS
);
?>
<?php endif; ?>
