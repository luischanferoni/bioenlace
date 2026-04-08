<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;
use common\models\Consulta;
use common\models\Servicio;

$idServicioActual = isset($id_servicio_actual) ? (int) $id_servicio_actual : 0;
$esAmbulatorio = ($encounter_class === Consulta::ENCOUNTER_CLASS_AMB);
$esImpQuirurgico = ($encounter_class === Consulta::ENCOUNTER_CLASS_IMP && $idServicioActual && Servicio::esServicioAgendaQuirurgica($idServicioActual));
$fechaAnterior = date('Y-m-d', strtotime($fecha . ' -1 day'));
$fechaSiguiente = date('Y-m-d', strtotime($fecha . ' +1 day'));
$hoy = date('Y-m-d');

$encounterMeta = [
    Consulta::ENCOUNTER_CLASS_AMB => [
        'label' => 'Ambulatorio',
    ],
    Consulta::ENCOUNTER_CLASS_IMP => [
        'label' => 'Internación',
    ],
    Consulta::ENCOUNTER_CLASS_EMER => [
        'label' => 'Guardia',
    ],
];
$metaEc = ($encounter_class && isset($encounterMeta[$encounter_class]))
    ? $encounterMeta[$encounter_class]
    : ['label' => ''];

$encounterJson = Json::encode($encounter_class);

$this->title = 'Pacientes';
?>

<div class="mb-4">
    <h2 class="mb-2"><?= Html::encode($this->title) ?></h2>
    <?php if ($metaEc['label']): ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="badge bg-primary"><?= Html::encode($metaEc['label']) ?></span>
        </div>
    <?php endif; ?>
</div>

<?php if ($esAmbulatorio || $esImpQuirurgico): ?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div class="text-muted small">
        <?php if ($esAmbulatorio): ?>
        <strong>Filtrar por fecha del turno:</strong> solo se listan turnos <strong>pendientes y sin atender</strong> en la fecha elegida.
        <?php else: ?>
        <strong>Filtrar por fecha:</strong> cirugías agendadas en el efector para el día indicado.
        <?php endif; ?>
    </div>
    <div class="btn-group" role="group">
        <a href="<?= Url::to(['site/pacientes', 'fecha' => $fechaAnterior]) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-left"></i> Fecha anterior
        </a>
        <a href="<?= Url::to(['site/pacientes', 'fecha' => $hoy]) ?>" class="btn btn-outline-secondary btn-sm">
            Fecha de hoy
        </a>
        <a href="<?= Url::to(['site/pacientes', 'fecha' => $fechaSiguiente]) ?>" class="btn btn-outline-secondary btn-sm">
            Fecha siguiente <i class="bi bi-chevron-right"></i>
        </a>
    </div>
</div>
<?php endif; ?>

<div id="pacientes-listado-container"
     data-fecha="<?= Html::encode($fecha) ?>"
     data-encounter="<?= Html::encode($encounter_class) ?>"
     data-url-historia="<?= Html::encode(Url::to(['/paciente/historia'], true)) ?>"
     data-url-internacion-view="<?= Html::encode(Url::to(['internacion/view'], true)) ?>"
     data-msg-empty-turnos="<?= Html::encode('No hay pacientes con turno pendiente de atención en la fecha seleccionada.') ?>"
     data-msg-empty-internados="<?= Html::encode('No hay pacientes internados para mostrar.') ?>"
     data-msg-empty-guardias="<?= Html::encode('No hay ingresos en guardia pendientes.') ?>"
     data-msg-empty-cirugias="<?= Html::encode('No hay cirugías agendadas para la fecha seleccionada.') ?>"
>
    <div id="pacientes-listado-loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
        <p class="mt-2 text-muted">Cargando listado de pacientes…</p>
    </div>
    <div id="pacientes-listado-content" class="d-none"></div>
    <div id="pacientes-listado-error" class="d-none alert alert-warning"></div>
</div>

<?= $this->render('_listado_templates') ?>

<?php
$this->registerJsFile('@web/js/pacientes-listado.js', ['depends' => [\yii\web\JqueryAsset::class]]);
?>
