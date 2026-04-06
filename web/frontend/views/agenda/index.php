<?php

use frontend\assets\AgendaLaboralAsset;
use yii\web\View;

/** @var array<int|string, string> $tiposDia */

AgendaLaboralAsset::register($this);

$this->title = 'Agenda laboral';
$this->params['breadcrumbs'][] = $this->title;

?>

<div class="container-fluid">
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h3 class="mb-1">Agenda laboral</h3>
                    <div class="text-muted small">
                        Editá horarios por servicio. Se guarda automáticamente al modificar un campo.
                    </div>
                </div>
                <div class="text-end">
                    <button class="btn btn-outline-secondary btn-sm" type="button" id="al_reload">
                        Recargar
                    </button>
                </div>
            </div>

            <div id="al_error" class="alert alert-danger d-none"></div>

            <div id="al_loading" class="text-muted">Cargando agendas…</div>
            <div id="al_list" class="row g-3 d-none"></div>
        </div>
    </div>
</div>

<template id="al_agenda_card_tpl">
    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="fw-semibold al_service"></div>
                <div class="small al_status text-muted"></div>
            </div>
            <div class="card-body">
                <?= $this->render('_form', ['tiposDia' => $tiposDia]) ?>
            </div>
        </div>
    </div>
</template>
