<?php

use yii\helpers\Html;
use yii\helpers\Url;
use common\components\Domain\Clinical\PatientHistoriaUrl;
use common\components\Domain\Clinical\Inpatient\Service\InternacionMapaCamasService;
use common\models\Clinical\Encounter;

/** @var array<string, mixed>|null $mapa */
if (!isset($mapa) || !is_array($mapa) || ($mapa['pisos'] ?? []) === []) {
    return;
}

$resumen = $mapa['resumen'] ?? [];
$libre = InternacionMapaCamasService::ESTADO_LIBRE;
$ocupada = InternacionMapaCamasService::ESTADO_OCUPADA;
$bloqueada = InternacionMapaCamasService::ESTADO_BLOQUEADA;
$aislamiento = InternacionMapaCamasService::ESTADO_AISLAMIENTO;
?>
<div id="internacion-mapa-root">
<p class="text-muted mb-2"><?= Html::encode((string) ($mapa['resumen_texto'] ?? '')) ?></p>
<p id="internacion-indicadores-resumen" class="small text-muted mb-3 d-none"></p>
<?php foreach ($mapa['pisos'] as $piso): ?>
    <div class="card mb-3">
        <div class="card-header bg-soft-info">
            <h3 class="mb-0">Piso: <?= Html::encode((string) ($piso['descripcion'] ?? '')) ?></h3>
        </div>
        <div class="card-body">
            <?php foreach ($piso['salas'] as $sala): ?>
                <h4 class="mb-3">Sala: <?= Html::encode((string) ($sala['descripcion'] ?? '')) ?></h4>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <?php foreach ($sala['camas'] as $cama):
                        $estado = (string) ($cama['estado_mapa'] ?? '');
                        if ($estado === $ocupada) {
                            $class = 'btn btn-danger';
                        } elseif ($estado === $bloqueada) {
                            $class = 'btn btn-secondary';
                        } elseif ($estado === $aislamiento) {
                            $class = 'btn btn-warning';
                        } else {
                            $class = 'btn btn-success';
                        }
                        $title = 'Cama ' . ($cama['nro_cama'] ?? '') . ' — ' . $estado;
                        if (!empty($cama['paciente_nombre'])) {
                            $title .= ': ' . $cama['paciente_nombre'];
                        }
                        $camaId = (int) ($cama['id'] ?? 0);
                        $internacionId = (int) ($cama['internacion_id'] ?? 0);
                        $idPersona = (int) ($cama['id_persona'] ?? 0);
                        if ($estado === $ocupada && $internacionId > 0 && $idPersona > 0) {
                            $url = PatientHistoriaUrl::captura(
                                $idPersona,
                                Encounter::PARENT_INTERNACION,
                                $internacionId
                            );
                            $title .= ' — clic: atender (historia clínica)';
                        } elseif ($estado === $ocupada && $internacionId > 0) {
                            $url = Url::to(['internacion/view', 'id' => $internacionId]);
                        } else {
                            $url = Url::to(['internacion/ingreso', 'id' => $camaId]);
                        }
                        $opts = ['class' => $class, 'title' => $title];
                        if ($estado !== $ocupada && !empty($pacienteInternado)) {
                            $opts['class'] .= ' disabled';
                        }
                        ?>
                        <div class="d-inline-flex flex-column align-items-center me-1 mb-1">
                            <?= Html::a(Html::encode((string) ($cama['nro_cama'] ?? '?')), $url, $opts) ?>
                            <?php if ($estado !== $ocupada): ?>
                                <span class="btn-group btn-group-sm mt-1" role="group">
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                        data-internacion-cama-action="1"
                                        data-cama-id="<?= $camaId ?>"
                                        data-estado-mapa="<?= Html::encode($bloqueada) ?>"
                                        title="Bloquear">B</button>
                                    <button type="button" class="btn btn-outline-warning btn-sm"
                                        data-internacion-cama-action="1"
                                        data-cama-id="<?= $camaId ?>"
                                        data-estado-mapa="<?= Html::encode($aislamiento) ?>"
                                        title="Aislamiento">A</button>
                                    <?php if ($estado !== $libre): ?>
                                        <button type="button" class="btn btn-outline-success btn-sm"
                                            data-internacion-cama-action="1"
                                            data-cama-id="<?= $camaId ?>"
                                            data-estado-mapa="<?= Html::encode($libre) ?>"
                                            title="Liberar">L</button>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>
