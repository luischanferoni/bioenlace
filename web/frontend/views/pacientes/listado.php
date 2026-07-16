<?php

use frontend\assets\AppAsset;
use frontend\assets\GuardiaTableroAsset;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;
use common\models\Clinical\Encounter;
use common\models\Servicio;

$idServicioActual = isset($id_servicio_actual) ? (int) $id_servicio_actual : 0;
$esAmbulatorio = ($encounter_class === Encounter::ENCOUNTER_CLASS_AMB);
$esGuardia = ($encounter_class === Encounter::ENCOUNTER_CLASS_EMER);
$esImpQuirurgico = ($encounter_class === Encounter::ENCOUNTER_CLASS_IMP && $idServicioActual && Servicio::esServicioAgendaQuirurgica($idServicioActual));
$esImpPiso = !empty($es_imp_piso);
$mapaCtx = $mapa_ctx ?? null;
$fechaAnterior = date('Y-m-d', strtotime($fecha . ' -1 day'));
$fechaSiguiente = date('Y-m-d', strtotime($fecha . ' +1 day'));
$hoy = date('Y-m-d');

$encounterMeta = [
    Encounter::ENCOUNTER_CLASS_AMB => [
        'label' => 'Ambulatorio',
    ],
    Encounter::ENCOUNTER_CLASS_IMP => [
        'label' => 'Internación',
    ],
    Encounter::ENCOUNTER_CLASS_EMER => [
        'label' => 'Guardia',
    ],
];
$metaEc = ($encounter_class && isset($encounterMeta[$encounter_class]))
    ? $encounterMeta[$encounter_class]
    : ['label' => ''];

$encounterJson = Json::encode($encounter_class);
$esPacienteHome = empty($encounter_class);

$this->title = $esGuardia ? 'Tablero de guardia' : ($esPacienteHome ? 'Inicio' : 'Pacientes');
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
        <a href="<?= Url::to(['site/index', 'fecha' => $fechaAnterior]) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-left"></i> Fecha anterior
        </a>
        <a href="<?= Url::to(['site/index', 'fecha' => $hoy]) ?>" class="btn btn-outline-secondary btn-sm">
            Fecha de hoy
        </a>
        <a href="<?= Url::to(['site/index', 'fecha' => $fechaSiguiente]) ?>" class="btn btn-outline-secondary btn-sm">
            Fecha siguiente <i class="bi bi-chevron-right"></i>
        </a>
    </div>
</div>
<?php endif; ?>

<?php if ($esImpPiso && is_array($mapaCtx)): ?>
    <?= $this->render('//internacion/_mapa_panel', [
        'pisos_efector' => $mapaCtx['pisos_efector'] ?? [],
        'mapa' => $mapaCtx['mapa'] ?? null,
        'pacienteInternado' => !empty($mapaCtx['paciente_internado']),
        'formAction' => Url::to(['site/index', 'fecha' => $fecha]),
    ]) ?>
<?php endif; ?>

<div id="pacientes-listado-container"
     data-fecha="<?= Html::encode($fecha) ?>"
     data-encounter="<?= Html::encode($encounter_class) ?>"
     data-url-historia="<?= Html::encode(Url::to(['/paciente/historia'], true)) ?>"
     data-url-internacion-view="<?= Html::encode(Url::to(['internacion/view'], true)) ?>"
     data-url-asistente="<?= Html::encode(Url::to(['/site/asistente'], true)) ?>"
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
        <p class="mt-2 text-muted"><?= $esPacienteHome ? 'Cargando tu panel…' : 'Cargando listado de pacientes…' ?></p>
    </div>
    <div id="pacientes-listado-content" class="d-none"></div>
    <div id="pacientes-listado-error" class="d-none alert alert-warning"></div>
</div>

<?= $this->render('_listado_templates') ?>

<div class="modal fade" id="async-chat-modal" tabindex="-1" aria-labelledby="asyncChatModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="asyncChatModalLabel">Consulta clínica por mensaje</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body d-flex flex-column" style="min-height: 320px;">
                <p class="text-muted small mb-2" id="async-chat-subtitle"></p>
                <div id="async-chat-intake-context" class="alert alert-light border small py-2 px-3 mb-2 d-none" data-role="async-chat-intake-context">
                    <div class="fw-semibold mb-1" data-field="intake-title">Contexto</div>
                    <div data-field="intake-summary"></div>
                    <div class="mt-1" data-slot="intake-links"></div>
                </div>
                <div id="async-chat-loading" class="text-muted small">Cargando mensajes…</div>
                <div id="async-chat-messages" class="flex-grow-1 overflow-auto mb-3 d-none" style="max-height: 360px;"></div>
                <div id="async-chat-compose" class="d-none">
                    <label class="form-label visually-hidden" for="async-chat-input">Mensaje</label>
                    <textarea class="form-control form-control-sm mb-2" id="async-chat-input" rows="3" placeholder="Escribí tu mensaje…"></textarea>
                    <button type="button" class="btn btn-primary btn-sm" id="async-chat-send">Enviar</button>
                </div>
                <div id="async-chat-error" class="alert alert-danger d-none mt-2 mb-0"></div>
            </div>
        </div>
    </div>
</div>

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
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="guardia-derivar-solicitar-internacion">
                    <label class="form-check-label" for="guardia-derivar-solicitar-internacion">
                        Solicitar internación (cama) en el efector destino
                    </label>
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
<div class="modal fade" id="guardia-clinical-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pedidos y laboratorio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small" id="guardia-clinical-paciente-nombre"></p>
                <div id="guardia-clinical-loading" class="text-muted small">Cargando…</div>
                <ul id="guardia-clinical-orders" class="list-group list-group-flush mb-3 d-none"></ul>
                <ul id="guardia-clinical-lab" class="list-group list-group-flush mb-3 d-none"></ul>
                <div class="border rounded p-3 mb-3">
                    <label class="form-label" for="guardia-clinical-pedido-display">Nuevo pedido (laboratorio)</label>
                    <input type="text" class="form-control form-control-sm mb-2" id="guardia-clinical-pedido-display" placeholder="Ej. Hemograma completo">
                    <button type="button" class="btn btn-sm btn-primary" id="guardia-clinical-pedido-submit">Agregar pedido</button>
                </div>
                <a href="#" class="btn btn-outline-secondary btn-sm" id="guardia-clinical-captura-link" data-spa-nav="1">Abrir captura clínica</a>
                <div id="guardia-clinical-error" class="alert alert-danger d-none mt-3 mb-0"></div>
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
