<?php

use common\models\AgendaFeriados;
use yii\bootstrap5\Modal;
use yii\helpers\Html;
use yii\helpers\Url;
use common\models\Turno;


$period = new DatePeriod(
    new DateTime('NOW -60 day'),
    new DateInterval('P1D'),
    new DateTime('NOW +90day')
);

$turnos_url_eventos = Url::to(['turnos/eventos']);
$turnos_url_historial = Url::to(['turnos/historial']);
$turnos_url_create = Url::to(['turnos/create']);
$turnos_url_delete = Url::to(['turnos/delete']);
$turnos_id_efector = Yii::$app->user->getIdEfector();
$hoy = (new DateTime())->format('Y-m-d');

$this->registerJsVar("turnos_url_eventos", $turnos_url_eventos);
$this->registerJsVar("turnos_url_create", $turnos_url_create);
$this->registerJsVar("turnos_url_delete", $turnos_url_delete);
$this->registerJsVar("turnos_id_efector", $turnos_id_efector);
$this->registerJsVar("turnos_id_servicio", 0);
$this->registerJsVar("turnos_id_rrhh_sa", 0);
$this->registerJsFile(
    "@web/js/turnos_calendario.js",
    ['depends' => [\yii\web\JqueryAsset::class]]
);

Modal::begin([
    'title' => '',
    'id' => 'modal-general',
    'size' => 'modal-xl',
]);
?>
<div class="row mb-1">
    <div class="col-3 d-none">
        <a href="<?= $turnos_url_historial ?>" target="_blank" class="btn btn-sm btn-soft-dark me-1">Turnos pasados</a>
    </div>
    <div class="col-12 d-flex justify-content-center controls" id="controles-personalizados">
        <button type="button" class="btn btn-sm btn-soft-primary me-1 prev" data-controls="prev">Días anteriores</button>
        <button type="button" class="btn btn-sm btn-soft-primary ms-1 next" data-controls="next">Siguientes días</button>
    </div>
</div>
<div class="weekday-slider">
    <?php foreach ($period as $key => $value) :
        $colorBgClass = "";

        if (Yii::$app->formatter->asDate($value, 'EEE') == 'sáb.' || Yii::$app->formatter->asDate($value, 'EEE') == 'dom.') {
            $colorBgClass = "bg-soft-dark";
        } else if ($value->format('Y-m-d') === $hoy) {
            $colorBgClass = "bg-soft-info";
        } else {
            $colorBgClass = "bg-soft-secondary";
        }

        if(AgendaFeriados::esFeriado($value->format('Y-m-d'),$feriados)){
            $colorBgClass = "bg-soft-danger";
        }



    ?>

        <div class="card text-center mb-3 me-4 <?= $value->format('Y-m-d') === $hoy ?  'border border-dark' : '' ?> <?= $colorBgClass ?>" style="height: 9rem; width: 8rem; !important">
            <a href="<?= $value->format('Y-m-d') ?>" class="mostrar-turnos">
                <div class="card-body pb-1">
                    <div class="d-flex flex-column align-items-center">

                        <div>
                            <span><?= ucfirst(Yii::$app->formatter->asDate($value, 'EEE')) ?></span>
                            <span>
                                <h4><?= UCWORDS(Yii::$app->formatter->asDate($value, 'dd')) ?></h4>
                            </span>
                            <span>
                                <h5 class="counter mb-2" style="visibility: visible;"><?= UCWORDS(Yii::$app->formatter->asDate($value, 'MMMM')) ?></h5>
                            </span>
                            <span class="text-muted"><?= $value->format('Y-m-d') === $hoy ?  'HOY' : ' ' ?></span>
                        </div>


                    </div>
                </div>
                </a>
        </div>

    <?php endforeach ?>
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
            <input type="hidden" name="fecha" id="fecha_input" value="<?= $hoy ?>">
            <input type="hidden" name="hora" id="hora_input" value="">
            <input type="hidden" name="todosTomados" id="todosTomados" value="">

            <div class="col pe-0">
                <div id="motivo_cancelacion_div" class="col float-end" style="display: none;">
                    <?php
                    echo html::dropDownList(
                        'motivos_cancelacion',
                        [],
                        Turno::getMotivosCancelacion(),
                        [
                            'prompt' => 'Motivo de Cancelación',
                            'class' => 'form-control',
                            'id' => 'motivo_cancelacion'
                        ]
                    );
                    ?>
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
<?php
Modal::end();
