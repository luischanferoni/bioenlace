<?php

use frontend\assets\AppAsset;
use frontend\assets\GuardiaTableroAsset;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;
use common\models\Consulta;
use common\models\Servicio;

$idServicioActual = isset($id_servicio_actual) ? (int) $id_servicio_actual : 0;
$esAmbulatorio = ($encounter_class === Consulta::ENCOUNTER_CLASS_AMB);
$esGuardia = ($encounter_class === Consulta::ENCOUNTER_CLASS_EMER);
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

$this->title = $esGuardia ? 'Tablero de guardia' : 'Pacientes';
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
     data-msg-empty-guardias="<?= Html::encode('No hay pacientes en el tablero de guardia.') ?>"
     data-msg-empty-cirugias="<?= Html::encode('No hay cirugías agendadas para la fecha seleccionada.') ?>"
     data-es-guardia="<?= $esGuardia ? '1' : '0' ?>"
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

<?php if ($esGuardia): ?>
<div class="modal fade" id="guardia-triage-modal" tabindex="-1" aria-labelledby="guardiaTriageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="guardiaTriageModalLabel">Triage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="guardia-triage-paciente-nombre"></p>
                <div class="mb-3">
                    <label class="form-label">Prioridad (Manchester)</label>
                    <div class="d-flex flex-wrap gap-2" id="guardia-triage-levels">
                        <?php for ($n = 1; $n <= 5; $n++): ?>
                        <input type="radio" class="btn-check" name="guardia_triage_level" id="guardia-triage-level-<?= $n ?>" value="<?= $n ?>"<?= $n === 3 ? ' checked' : '' ?>>
                        <label class="btn btn-outline-secondary btn-sm" for="guardia-triage-level-<?= $n ?>"><?= $n ?></label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="guardia-triage-reason" class="form-label">Motivo de consulta</label>
                    <textarea class="form-control" id="guardia-triage-reason" rows="2" required></textarea>
                </div>
                <div class="row g-2">
                    <div class="col-4">
                        <label class="form-label" for="guardia-triage-bp-sys">TA sist.</label>
                        <input type="number" class="form-control form-control-sm" id="guardia-triage-bp-sys" min="0">
                    </div>
                    <div class="col-4">
                        <label class="form-label" for="guardia-triage-bp-dia">TA diast.</label>
                        <input type="number" class="form-control form-control-sm" id="guardia-triage-bp-dia" min="0">
                    </div>
                    <div class="col-4">
                        <label class="form-label" for="guardia-triage-hr">FC</label>
                        <input type="number" class="form-control form-control-sm" id="guardia-triage-hr" min="0">
                    </div>
                </div>
                <div id="guardia-triage-error" class="alert alert-danger d-none mt-3 mb-0"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="guardia-triage-submit">Registrar triage</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="guardia-derivar-modal" tabindex="-1" aria-labelledby="guardiaDerivarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="guardiaDerivarModalLabel">Derivar paciente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="guardia-derivar-paciente-nombre"></p>
                <div class="mb-3">
                    <label for="guardia-derivar-efector" class="form-label">Efector destino</label>
                    <select class="form-select" id="guardia-derivar-efector" required></select>
                </div>
                <div class="mb-3">
                    <label for="guardia-derivar-condiciones" class="form-label">Condiciones / motivo</label>
                    <textarea class="form-control" id="guardia-derivar-condiciones" rows="2"></textarea>
                </div>
                <div id="guardia-derivar-error" class="alert alert-danger d-none mb-0"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="guardia-derivar-submit">Confirmar derivación</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="guardia-finalizar-modal" tabindex="-1" aria-labelledby="guardiaFinalizarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="guardiaFinalizarModalLabel">Egreso de guardia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="guardia-finalizar-paciente-nombre"></p>
                <p class="small">Confirme el egreso del episodio en el tablero (libro de guardia).</p>
                <div id="guardia-finalizar-error" class="alert alert-danger d-none mb-0"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="guardia-finalizar-submit">Confirmar egreso</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Tras AppAsset: BioenlaceApiClient.mergeHeaders + native-page-bridge (BioenlaceNativePage).
$jsDepends = [AppAsset::class];
if ($esGuardia) {
    GuardiaTableroAsset::register($this);
    $jsDepends[] = GuardiaTableroAsset::class;
}
$this->registerJsFile('@web/js/pacientes-listado.js', ['depends' => $jsDepends]);
?>
