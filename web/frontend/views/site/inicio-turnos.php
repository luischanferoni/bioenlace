<?php

use yii\helpers\Html;
use yii\helpers\Url;
use common\models\Turno;
use common\models\Persona;
use common\helpers\TimelineHelper;

$fechaAnterior = date('Y-m-d', strtotime($fecha . ' -1 day'));
$fechaSiguiente = date('Y-m-d', strtotime($fecha . ' +1 day'));
$tituloFecha = TimelineHelper::formatearFechaAmigable($fecha);
$esHoy = ($fecha == date('Y-m-d'));

$this->title = 'Inicio - Turnos';
?>


    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><?= Html::encode($tituloFecha) ?></h2>
        <div class="btn-group" role="group">
            <a href="<?= Url::to(['site/index', 'fecha' => $fechaAnterior]) ?>" class="btn btn-outline-secondary me-3">
                <i class="bi bi-chevron-left"></i> Anterior
            </a>
            <a href="<?= Url::to(['site/index', 'fecha' => date('Y-m-d')]) ?>" class="btn btn-outline-secondary">
                Hoy
            </a>
            <a href="<?= Url::to(['site/index', 'fecha' => $fechaSiguiente]) ?>" class="btn btn-outline-secondary ms-3">
                Siguiente <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>

    <?php if ($esHoy): ?>
        <!-- Card de prueba: Siguiente turno -->
        <div class="row mb-4">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 spa-card shadow-sm" 
                     data-card-id="next-appointment-card" 
                     data-expandable="false" 
                     data-full-page="true" 
                     data-action-type="appointment" 
                     data-action-url="<?= Url::toRoute(['/paciente/historia', 'id' => 920779], true) ?>">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-calendar-check text-primary me-2" style="font-size: 1.5rem;"></i>
                            <h6 class="card-title text-primary fw-semibold mb-0">Siguiente Turno</h6>
                        </div>
                        <p class="card-text text-muted small mb-2">
                            <strong>Paciente:</strong> [Nombre del Paciente]<br>
                            <strong>Fecha:</strong> [Fecha del turno]<br>
                            <strong>Hora:</strong> [Hora del turno]
                        </p>
                        <small class="text-muted">Haz clic para ver la historia clínica</small>
                        <div class="spa-card-expand-content d-none mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($turnos)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            No hay turnos programados para esta fecha.
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($turnos as $turno): ?>
                <?php
                $paciente = $turno->persona;
                $nombreCompleto = $paciente ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D) : 'Sin paciente';
                $servicio = $turno->servicio ? $turno->servicio->nombre : ($turno->rrhhServicioAsignado ? $turno->rrhhServicioAsignado->servicio->nombre : 'Sin servicio');
                ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-person-circle text-primary me-2"></i>
                                <?= Html::encode($nombreCompleto) ?>
                            </h5>
                            <div class="mb-2">
                                <strong><i class="bi bi-clock me-2"></i>Hora:</strong>
                                <?= Html::encode($turno->hora) ?>
                            </div>
                            <div class="mb-2">
                                <strong><i class="bi bi-hospital me-2"></i>Servicio:</strong>
                                <?= Html::encode($servicio) ?>
                            </div>
                            <?php if ($turno->observaciones): ?>
                                <div class="mb-2">
                                    <strong><i class="bi bi-chat-left-text me-2"></i>Observaciones:</strong>
                                    <small class="text-muted"><?= Html::encode($turno->observaciones) ?></small>
                                </div>
                            <?php endif; ?>
                            <div class="mt-3">
                                <span class="badge bg-<?= $turno->estado == Turno::ESTADO_PENDIENTE ? 'warning' : 'secondary' ?>">
                                    <?= Html::encode(Turno::ESTADOS[$turno->estado] ?? 'Sin estado') ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent">
                            <a href="<?= Url::to(['/paciente/historia', 'id' => $turno->id_persona]) ?>" class="btn btn-sm btn-primary">
                                Ver Historia Clínica
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php if ($esHoy): ?>
<!-- Contenedor de páginas del stack -->
<div id="spa-pages-container" class="spa-pages-container">
    <!-- Las páginas se agregarán dinámicamente aquí -->
</div>

<?php
// Registrar JavaScript para manejo de cards SPA
$this->registerJsFile('@web/js/spa-navigation.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('@web/js/spa-home.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerCssFile('@web/css/spa.css', ['depends' => [\yii\web\JqueryAsset::class]]);
?>
<?php endif; ?>

