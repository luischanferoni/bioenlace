<?php

use yii\helpers\Html;
use yii\helpers\Url;
use common\assets\RegistroPacienteStaffAsset;

/** @var yii\web\View $this */
/** @var string $diditVerificationId */
/** @var string $diditStatus */

$this->title = 'Registrar paciente';
$this->params['breadcrumbs'][] = ['label' => 'Personas', 'url' => ['buscar-persona']];
$this->params['breadcrumbs'][] = $this->title;

RegistroPacienteStaffAsset::register($this);

$csrf = Yii::$app->request->csrfToken;
$config = [
    'csrfToken' => $csrf,
    'diditVerificationId' => $diditVerificationId,
    'diditStatus' => $diditStatus,
    'urls' => [
        'previewRenaper' => Url::to(['personas/preview-renaper-staff']),
        'registrar' => Url::to(['personas/registrar-paciente-submit']),
        'crearSesionDidit' => Url::to(['personas/crear-sesion-didit-staff']),
        'diditCallback' => Url::to(['personas/registrar-paciente'], true),
        'verPersona' => Url::to(['personas/view', 'id' => '__ID__']),
    ],
];
$this->registerJs(
    'window.BioenlaceRegistroPacienteStaff = ' . json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';',
    \yii\web\View::POS_HEAD
);
?>

<div class="registrar-paciente-staff">
    <div class="iq-loader-box" id="registro-paciente-cover-spin" style="display:none;">
        <div class="iq-loader-1"></div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0"><?= Html::encode($this->title) ?></h1>
        <?= Html::a('Búsqueda legacy (MPI)', ['buscar-persona'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
    </div>

    <p class="text-muted">
        Alta sin MPI: identidad por <strong>lector de DNI</strong> o <strong>foto del documento (Didit)</strong>.
        El domicilio se obtiene de RENAPER en segundo plano (mismo flujo que la app paciente).
    </p>

    <ul class="nav nav-tabs" id="registroPacienteTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-lector" data-bs-toggle="tab" data-bs-target="#panel-lector" type="button" role="tab">
                Lector DNI
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-didit" data-bs-toggle="tab" data-bs-target="#panel-didit" type="button" role="tab">
                Foto del DNI (Didit)
            </button>
        </li>
    </ul>

    <div class="tab-content border border-top-0 p-3 bg-white" id="registroPacienteTabContent">
        <div class="tab-pane fade show active" id="panel-lector" role="tabpanel">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="registro-paciente-lector" checked>
                <label class="form-check-label" for="registro-paciente-lector">Activar lector de código (PDF417)</label>
            </div>
            <p class="small text-muted">Escaneá el reverso del DNI. Los datos se validan en RENAPER antes del alta.</p>
            <dl class="row small mb-2">
                <dt class="col-sm-2">DNI</dt>
                <dd class="col-sm-10" id="registro-paciente-documento">—</dd>
                <dt class="col-sm-2">Sexo</dt>
                <dd class="col-sm-10" id="registro-paciente-sexo">—</dd>
            </dl>
            <input type="hidden" id="registro-paciente-codigo" value="">
            <div id="registro-paciente-preview" class="mb-3"></div>
            <button type="button" class="btn btn-primary" id="btn-registrar-desde-lector">Registrar paciente</button>
        </div>

        <div class="tab-pane fade" id="panel-didit" role="tabpanel">
            <?php if ($diditVerificationId !== ''): ?>
                <div class="alert alert-info" id="registro-paciente-didit-status">
                    Verificación Didit: <?= Html::encode($diditStatus !== '' ? $diditStatus : 'completada') ?>.
                    Session: <?= Html::encode($diditVerificationId) ?>
                </div>
                <input type="hidden" id="registro-paciente-verification-id" value="<?= Html::encode($diditVerificationId) ?>">
                <button type="button" class="btn btn-primary" id="btn-registrar-desde-didit">Confirmar alta con Didit</button>
            <?php else: ?>
                <p class="small text-muted">
                    Se abrirá la verificación Didit en una nueva página. El paciente (o usted con el DNI físico) completa foto y validación.
                </p>
                <button type="button" class="btn btn-primary" id="btn-iniciar-didit">Iniciar verificación Didit</button>
            <?php endif; ?>
        </div>
    </div>
</div>
