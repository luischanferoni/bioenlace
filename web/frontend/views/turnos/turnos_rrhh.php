<?php

use yii\helpers\Url;
use yii\helpers\Html;
use kartik\select2\Select2;
use yii\bootstrap5\Dropdown;
use common\models\Turno;

$hoy = (new DateTime())->format('Y-m-d');

$period = new DatePeriod(
     new DateTime('NOW -60 day'),
     new DateInterval('P1D'),
     new DateTime('NOW +90 day')
);
?>
<div class="row mb-1">
    <div class="col-3 d-none"><a href="<?=Url::to(['turnos/historial'])?>" target="_blank" class="btn btn-sm btn-soft-dark me-1">Turnos pasados</a></div>
    <div class="col-6 d-flex justify-content-center controls" id="controles-personalizados">
        <button type="button" class="btn btn-sm btn-soft-primary me-1 prev" data-controls="prev">Días anteriores</button>
        <button type="button" class="btn btn-sm btn-soft-primary ms-1 next" data-controls="next">Siguientes días</button>
    </div>    
</div>

<div class="weekday-slider">
    <?php foreach ($period as $key => $value) { ?>
    <div class="card text-center mb-3 me-4 <?=$value->format('Y-m-d') === $hoy ?  'bg-soft-info border border-dark' : 'bg-soft-secondary'?>">
        <div class="card-body pb-1">            
            <div class="d-flex flex-column align-items-between">
                <a href="<?=$value->format('Y-m-d')?>" class="mostrar-turnos">
                    <div>                    
                        <span><?=$value->format('D') ?></span>
                        <div>
                            <h3 class="counter" style="visibility: visible;"><?=$value->format('d M') ?></h3>
                        </div>                    
                    </div>
                </a>
            </div>
        </div>

        <div class="card-footer text-muted p-1"><?=$value->format('Y-m-d') === $hoy ?  'hoy' : ' '?></div>
    </div>
    <?php } ?>
</div>

<div class="row">
    <div class="col-12 border-bottom">
        <h5><i class="bi bi-brightness-high"></i> Por la mañana</h5>
        <div id="eventos_maniana" class="mt-1 mb-3"></div>
        <h5><i class="bi bi-moon"></i> Por la tarde</h5>
        <div id="eventos_tarde" class="mt-1 mb-3"></div>
    </div>
    <div class="col-12">
        <div class="row pt-3">
            <input type="hidden" name="id_turnos" id="id_turnos" value="">
            <input type="hidden" name="fecha" id="fecha_input" value="<?=$hoy?>">
            <input type="hidden" name="hora" id="hora_input" value="">
            <input type="hidden" name="todosTomados" id="todosTomados" value="">

            <div class="col pe-0">
                <div id="motivo_cancelacion_div" class="col float-end" style="display: none;">
                <?php
                    echo html::dropDownList('motivos_cancelacion', [], 
                                            Turno::MOTIVOS_CANCELACION, 
                                            ['prompt' => 'Motivo de Cancelación', 'class' => 'form-control', 'id' => 'motivo_cancelacion']);
                ?>
                </div>
            </div>
            <div class="col-3 ps-0">
                <button id="crear_turno" class="btn btn-success float-end" disabled>Crear turno</button>
            </div>
        </div>
    </div>
</div>