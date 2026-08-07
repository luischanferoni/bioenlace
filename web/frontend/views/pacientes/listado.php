<?php

use frontend\assets\AppAsset;
use frontend\assets\GuardiaTableroAsset;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;
use common\models\Clinical\Encounter;
use common\models\Servicio;
use common\components\Domain\Clinical\Emergency\Enum\TriageScale;

$idServicioActual = isset($id_servicio_actual) ? (int) $id_servicio_actual : 0;
$esAmbulatorio = ($encounter_class === Encounter::ENCOUNTER_CLASS_AMB);
$esVirtual = ($encounter_class === Encounter::ENCOUNTER_CLASS_VR);
$esGuardia = ($encounter_class === Encounter::ENCOUNTER_CLASS_EMER);
$esImpQuirurgico = ($encounter_class === Encounter::ENCOUNTER_CLASS_IMP && $idServicioActual && Servicio::esServicioAgendaQuirurgica($idServicioActual));
$esImpPiso = !empty($es_imp_piso);
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
    Encounter::ENCOUNTER_CLASS_VR => [
        'label' => 'Virtual',
    ],
];
$metaEc = ($encounter_class && isset($encounterMeta[$encounter_class]))
    ? $encounterMeta[$encounter_class]
    : ['label' => ''];

$encounterJson = Json::encode($encounter_class);
$esPacienteHome = empty($encounter_class);

$this->title = $esGuardia
    ? 'Tablero de guardia'
    : ($esVirtual
        ? 'Consultas clínicas por mensaje'
        : ($esPacienteHome ? 'Inicio' : 'Pacientes'));
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
        <strong>Filtrar por fecha del turno:</strong> se listan los turnos del día, agrupados en <strong>por atender</strong> y <strong>atendidos</strong>.
        <?php else: ?>
        <strong>Filtrar por fecha:</strong> cirugías agendadas en el efector para el día indicado.
        <?php endif; ?>
    </div>
    <div class="btn-group" role="group">
        <a href="<?= Url::to(['site/index', 'fecha' => $fechaAnterior]) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-left"></i> Fecha anterior
        </a>
        <a href="<?= Url::to(['site/index', 'fecha' => $hoy]) ?>" class="btn btn-outline-secondary btn-sm ms-2 me-2">
            Fecha de hoy
        </a>
        <a href="<?= Url::to(['site/index', 'fecha' => $fechaSiguiente]) ?>" class="btn btn-outline-secondary btn-sm">
            Fecha siguiente <i class="bi bi-chevron-right"></i>
        </a>
    </div>
</div>
<?php endif; ?>

<div id="pacientes-listado-container"
     data-fecha="<?= Html::encode($fecha) ?>"
     data-encounter="<?= Html::encode($encounter_class) ?>"
     data-url-historia="<?= Html::encode(Url::to(['/paciente/historia'], true)) ?>"
     data-url-ver-consulta="<?= Html::encode(Url::to(['/paciente/ver-consulta'], true)) ?>"
     data-url-asistente="<?= Html::encode(Url::to(['/site/asistente'], true)) ?>"
     data-msg-empty-turnos="<?= Html::encode('No hay pacientes con turno en la fecha seleccionada.') ?>"
     data-msg-empty-internados="<?= Html::encode('No hay pacientes internados para mostrar.') ?>"
     data-msg-empty-guardias="<?= Html::encode('No hay pacientes en el tablero de guardia.') ?>"
     data-msg-empty-cirugias="<?= Html::encode('No hay cirugías agendadas para la fecha seleccionada.') ?>"
     data-es-guardia="<?= $esGuardia ? '1' : '0' ?>"
>
    <div id="pacientes-listado-flash" class="d-none alert mb-3" role="status"></div>
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
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="asyncChatModalLabel">Consulta clínica por mensaje</h5>
                <div class="d-flex align-items-center gap-2 ms-auto me-2" id="async-chat-header-actions"></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body d-flex flex-column p-0" style="height: min(70vh, 640px);">
                <div id="async-chat-scroll" class="flex-grow-1 overflow-auto px-3 pt-3" style="min-height: 0;">
                    <div id="async-chat-policy-hint" class="alert alert-info py-2 px-3 small mb-2 d-none"></div>
                    <div id="async-chat-intake-context" class="mb-3 d-none" data-role="async-chat-intake-context">
                        <div class="fw-bold mb-2" data-field="intake-title">Contexto de la solicitud</div>
                        <div data-slot="intake-lines"></div>
                        <div data-slot="intake-encounter-detail" class="d-none"></div>
                        <div class="mt-2" data-slot="intake-links"></div>
                    </div>
                    <div id="async-chat-loading" class="text-muted small">Cargando mensajes…</div>
                    <div id="async-chat-messages" class="d-none pb-2"></div>
                </div>
                <div id="async-chat-footer" class="border-top bg-white px-3 py-2 flex-shrink-0">
                    <div id="async-chat-compose" class="d-none">
                        <div class="d-flex flex-wrap gap-2 mb-2" id="async-chat-attach-actions"></div>
                        <label class="form-label visually-hidden" for="async-chat-input">Mensaje</label>
                        <textarea class="form-control form-control-sm mb-2" id="async-chat-input" rows="3" placeholder="Escribí tu mensaje…"></textarea>
                        <input type="file" id="async-chat-file-input" class="d-none" accept="application/pdf,.pdf,image/*">
                        <button type="button" class="btn btn-primary btn-sm" id="async-chat-send">Enviar</button>
                    </div>
                    <div id="async-chat-resolve-actions" class="d-none d-grid gap-2"></div>
                    <div id="async-chat-error" class="alert alert-danger d-none mt-2 mb-0"></div>
                </div>
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
                        <?php foreach (TriageScale::levelMeta() as $n => $meta): ?>
                        <input type="radio" class="btn-check" name="guardia_triage_level" id="guardia-triage-level-<?= (int) $n ?>" value="<?= (int) $n ?>"<?= (int) $n === 3 ? ' checked' : '' ?>>
                        <label
                            class="btn btn-sm guardia-triage-level guardia-triage-level--<?= (int) $n ?>"
                            for="guardia-triage-level-<?= (int) $n ?>"
                            title="<?= Html::encode($meta['label']) ?>"
                        ><?= (int) $n ?></label>
                        <?php endforeach; ?>
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
                <h5 class="modal-title" id="guardiaFinalizarModalLabel">Paciente se retiró</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="guardia-finalizar-paciente-nombre"></p>
                <p class="small" id="guardia-finalizar-help">
                    Registrá que el paciente se retiró / abandonó. No pide documentación clínica.
                </p>
                <div class="mb-2 d-none" id="guardia-finalizar-admin-fields">
                    <label class="form-label" for="guardia-finalizar-nota">Nota (opcional)</label>
                    <textarea class="form-control form-control-sm" id="guardia-finalizar-nota" rows="2"></textarea>
                </div>
                <div id="guardia-finalizar-error" class="alert alert-danger d-none mb-0"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="guardia-finalizar-submit">Confirmar retiro</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="guardia-ingreso-cama-modal" tabindex="-1" aria-labelledby="guardiaIngresoCamaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="guardiaIngresoCamaModalLabel">Ingreso a internación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2" id="guardia-ingreso-paciente-nombre"></p>
                <div id="guardia-ingreso-loading" class="text-muted small">Cargando formulario…</div>
                <div id="guardia-ingreso-form" class="d-none">
                    <input type="hidden" id="guardia-ingreso-id-persona" value="">
                    <input type="hidden" id="guardia-ingreso-id-guardia" value="">
                    <input type="hidden" id="guardia-ingreso-fecha" value="">
                    <input type="hidden" id="guardia-ingreso-hora" value="">
                    <input type="hidden" id="guardia-ingreso-tipo" value="1">
                    <div id="guardia-ingreso-aviso-camas" class="alert alert-warning py-2 small d-none" role="status"></div>
                    <div id="guardia-ingreso-campos">
                    <div class="mb-2">
                        <label class="form-label" for="guardia-ingreso-id-cama">Cama</label>
                        <select class="form-select form-select-sm" id="guardia-ingreso-id-cama" required></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="guardia-ingreso-id-pes">Profesional responsable</label>
                        <select class="form-select form-select-sm" id="guardia-ingreso-id-pes" required></select>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label" for="guardia-ingreso-en">Ingresa en</label>
                            <select class="form-select form-select-sm" id="guardia-ingreso-en" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="guardia-ingreso-con">Ingresa con</label>
                            <select class="form-select form-select-sm" id="guardia-ingreso-con" required></select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="guardia-ingreso-obra">Obra social</label>
                        <select class="form-select form-select-sm" id="guardia-ingreso-obra"></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="guardia-ingreso-situacion">Situación al ingresar</label>
                        <textarea class="form-control form-control-sm" id="guardia-ingreso-situacion" rows="2"></textarea>
                    </div>
                    </div>
                </div>
                <div id="guardia-ingreso-error" class="alert alert-danger d-none mt-3 mb-0"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="guardia-ingreso-submit" disabled>Confirmar ingreso</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($esImpPiso): ?>
<div class="modal fade" id="internacion-cambio-cama-modal" tabindex="-1" aria-labelledby="internacionCambioCamaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="internacionCambioCamaModalLabel">Cambio de cama</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2" id="internacion-cambio-cama-paciente"></p>
                <div id="internacion-cambio-cama-loading" class="text-muted small">Cargando…</div>
                <div id="internacion-cambio-cama-form" class="d-none">
                    <p class="small text-muted mb-2" id="internacion-cambio-cama-actual"></p>
                    <div class="mb-2">
                        <label class="form-label" for="internacion-cambio-cama-id-cama">Cama destino</label>
                        <select class="form-select form-select-sm" id="internacion-cambio-cama-id-cama" required></select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="internacion-cambio-cama-motivo">Motivo</label>
                        <input type="text" class="form-control form-control-sm" id="internacion-cambio-cama-motivo" maxlength="128" required>
                    </div>
                </div>
                <div id="internacion-cambio-cama-error" class="alert alert-danger d-none mt-3 mb-0"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-info" id="internacion-cambio-cama-submit" disabled>Confirmar cambio</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="internacion-alta-modal" tabindex="-1" aria-labelledby="internacionAltaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="internacionAltaModalLabel">Alta hospitalaria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2" id="internacion-alta-paciente"></p>
                <p class="text-muted small mb-2" id="internacion-alta-responsable"></p>
                <div id="internacion-alta-loading" class="text-muted small">Cargando…</div>
                <div id="internacion-alta-form" class="d-none">
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label" for="internacion-alta-fecha">Fecha de alta</label>
                            <input type="date" class="form-control form-control-sm" id="internacion-alta-fecha" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="internacion-alta-hora">Hora</label>
                            <input type="time" class="form-control form-control-sm" id="internacion-alta-hora" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="internacion-alta-tipo">Tipo de alta</label>
                            <select class="form-select form-select-sm" id="internacion-alta-tipo" required></select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="internacion-alta-plantilla">Plantilla de epicrisis</label>
                        <select class="form-select form-select-sm" id="internacion-alta-plantilla"></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="internacion-alta-epicrisis">Epicrisis</label>
                        <textarea class="form-control form-control-sm" id="internacion-alta-epicrisis" rows="6" required></textarea>
                    </div>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" id="internacion-alta-chk-med">
                        <label class="form-check-label" for="internacion-alta-chk-med">Medicación e indicaciones entregadas</label>
                    </div>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" id="internacion-alta-chk-ind">
                        <label class="form-check-label" for="internacion-alta-chk-ind">Indicaciones explicadas al paciente/familiar</label>
                    </div>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="internacion-alta-chk-ped">
                        <label class="form-check-label" for="internacion-alta-chk-ped">Pedidos pendientes resueltos o planificados</label>
                    </div>
                </div>
                <div id="internacion-alta-error" class="alert alert-danger d-none mt-3 mb-0"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="internacion-alta-submit" disabled>Registrar alta</button>
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
$this->registerJsFile('@web/js/async-consulta-chat.js', ['depends' => $jsDepends]);
$this->registerJsFile('@web/js/pacientes-listado.js', ['depends' => $jsDepends]);
?>
