<?php

use common\components\Domain\Scheduling\Service\TurnoCancelacionRazones;
use frontend\assets\TurnosCalendarioAsset;
use frontend\components\Scheduling\TurnosCalendarioPageBuilder;
use yii\bootstrap5\Modal;
use yii\helpers\Html;
use yii\helpers\Json;

/**
 * @var yii\web\View $this
 * @var mixed $feriados
 * @var \common\models\Person\Persona|null $persona
 */

$persona = $persona ?? null;
$page = TurnosCalendarioPageBuilder::build($persona, $feriados);
TurnosCalendarioAsset::register($this);

Modal::begin([
    'title' => '',
    'id' => 'modal-general',
    'size' => 'modal-xl',
]);
?>
<div id="turnos-calendario-root" data-turnos-config="<?= Json::htmlEncode($page['jsConfig']) ?>">
<div class="row mb-1">
    <div class="col-12 d-flex justify-content-center controls" id="controles-personalizados">
        <button type="button" class="btn btn-sm btn-soft-primary me-1 prev" data-controls="prev">Días anteriores</button>
        <button type="button" class="btn btn-sm btn-soft-primary ms-1 next" data-controls="next">Siguientes días</button>
    </div>
</div>
<div class="weekday-slider">
    <?php foreach ($page['days'] as $day): ?>
        <div class="card text-center mb-3 me-4 <?= $day['isToday'] ? 'border border-dark' : '' ?> <?= Html::encode($day['bgClass']) ?>" style="height: 9rem; width: 8rem;">
            <a href="<?= Html::encode($day['date']) ?>" class="mostrar-turnos">
                <div class="card-body pb-1">
                    <div class="d-flex flex-column align-items-center">
                        <div>
                            <span><?= Html::encode($day['weekday']) ?></span>
                            <span>
                                <h4><?= Html::encode($day['dayNum']) ?></h4>
                            </span>
                            <span>
                                <h5 class="counter mb-2" style="visibility: visible;"><?= Html::encode($day['month']) ?></h5>
                            </span>
                            <span class="text-muted"><?= $day['isToday'] ? 'HOY' : ' ' ?></span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
<div class="row">
    <div id="mensaje_feriado" class="text-center mt-5"></div>
    <div class="col-12 border-bottom">
        <h5><i class="bi bi-brightness-high"></i> Por la mañana</h5>
        <div id="eventos_maniana" class="mt-1 mb-3"></div>
        <h5><i class="bi bi-moon"></i> Por la tarde</h5>
        <div id="eventos_tarde" class="mt-1 mb-3"></div>
    </div>
    <div class="col-12">
        <div class="row pt-3">
            <input type="hidden" name="id_turnos" id="id_turnos" value="">
            <input type="hidden" name="fecha" id="fecha_input" value="<?= Html::encode($page['hoy']) ?>">
            <input type="hidden" name="hora" id="hora_input" value="">
            <input type="hidden" name="todosTomados" id="todosTomados" value="">

            <div class="col pe-0">
                <div id="motivo_cancelacion_div" class="col float-end" style="display: none;">
                    <?= Html::dropDownList(
                        'motivos_cancelacion',
                        [],
                        TurnoCancelacionRazones::medicoAppOpcionesDropdown(),
                        [
                            'prompt' => 'Motivo de Cancelación',
                            'class' => 'form-control',
                            'id' => 'motivo_cancelacion',
                        ]
                    ) ?>
                </div>
            </div>

            <div id="msg_turno_atendido" class="text-center" style="display: none;">
                <h5>EL TURNO YA SE ATENDIÓ O ESTA ATENDIENDOSE</h5>
            </div>

            <div class="col-3 ps-0">
                <button id="btn_turno_create" class="btn btn-success float-end" disabled>Crear turno</button>
                <button id="btn_turno_cancel" class="btn btn-danger float-end">Cancelar Turno</button>
                <button id="btn_turno_sobreturno" class="btn btn-secondary float-end">Sobreturno</button>
            </div>
        </div>
    </div>
</div>
</div>
<?php
Modal::end();
