<?php

use yii\helpers\Html;
use yii\helpers\Url;
use common\components\Domain\Clinical\PatientHistoriaUrl;
use common\components\Domain\Clinical\Inpatient\Service\InternacionMapaCamasService;
use common\models\Clinical\Encounter;

/** @var array<string, mixed>|null $mapa */
/** @var bool $pacienteInternado */
if (!isset($mapa) || !is_array($mapa) || ($mapa['pisos'] ?? []) === []) {
    echo '<p class="text-muted mb-0 small">No hay camas para mostrar con el filtro actual.</p>';
    return;
}

$libre = InternacionMapaCamasService::ESTADO_LIBRE;
$ocupada = InternacionMapaCamasService::ESTADO_OCUPADA;
$bloqueada = InternacionMapaCamasService::ESTADO_BLOQUEADA;
$aislamiento = InternacionMapaCamasService::ESTADO_AISLAMIENTO;
?>
<div id="internacion-mapa-root">
<?php foreach ($mapa['pisos'] as $piso): ?>
    <div class="mb-3">
        <div class="px-2 py-1 mb-2 rounded bg-soft-primary text-primary fw-semibold small">
            <?= Html::encode((string) ($piso['descripcion'] ?? 'Piso')) ?>
        </div>
        <?php foreach ($piso['salas'] as $sala): ?>
            <div class="px-2 py-1 mb-2 rounded bg-soft-success text-success fw-semibold small">
                <?= Html::encode((string) ($sala['descripcion'] ?? 'Sala')) ?>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <?php foreach ($sala['camas'] as $cama):
                    $estado = (string) ($cama['estado_mapa'] ?? '');
                    if ($estado === $ocupada) {
                        $class = 'btn btn-danger btn-sm text-start';
                    } elseif ($estado === $bloqueada) {
                        $class = 'btn btn-secondary btn-sm text-start';
                    } elseif ($estado === $aislamiento) {
                        $class = 'btn btn-warning btn-sm text-start';
                    } else {
                        $class = 'btn btn-success btn-sm text-start';
                    }
                    $nro = (string) ($cama['nro_cama'] ?? '?');
                    $nombre = trim((string) ($cama['paciente_nombre'] ?? ''));
                    $title = 'Cama ' . $nro . ' — ' . $estado;
                    if ($nombre !== '') {
                        $title .= ': ' . $nombre;
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
                        $title .= ' — clic: atender';
                    } elseif ($estado === $ocupada && $internacionId > 0) {
                        $url = Url::to(['internacion/view', 'id' => $internacionId]);
                    } else {
                        $url = Url::to(['internacion/ingreso', 'id' => $camaId]);
                    }
                    $opts = [
                        'class' => $class,
                        'title' => $title,
                        'style' => 'min-width: 5.5rem; max-width: 9rem;',
                        'encode' => false,
                    ];
                    if ($estado !== $ocupada && !empty($pacienteInternado)) {
                        $opts['class'] .= ' disabled';
                    }
                    $labelHtml = '<span class="fw-semibold">Cama ' . Html::encode($nro) . '</span>';
                    if ($nombre !== '') {
                        $labelHtml .= '<br><span class="small">' . Html::encode($nombre) . '</span>';
                    } elseif ($estado !== $ocupada) {
                        $labelHtml .= '<br><span class="small">' . Html::encode($estado) . '</span>';
                    }
                    ?>
                    <div class="d-inline-flex flex-column align-items-stretch me-1 mb-1">
                        <?= Html::a($labelHtml, $url, $opts) ?>
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
<?php endforeach; ?>
</div>
