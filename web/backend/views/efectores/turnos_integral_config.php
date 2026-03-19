<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use common\models\EfectorTurnosConfig;
use common\models\SolicitudRrhh;

$this->title = 'Turnos — ' . $efector->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Efectores', 'url' => ['indexuserefector']];
$this->params['breadcrumbs'][] = ['label' => $efector->nombre, 'url' => ['view', 'id' => $efector->id_efector]];
$this->params['breadcrumbs'][] = 'Config. turnos';
?>

<div class="card">
    <div class="card-header">
        <h4 class="mb-0"><?= Html::encode($this->title) ?></h4>
    </div>
    <div class="card-body">
        <?php $form = ActiveForm::begin(); ?>
        <?= $form->field($model, 'cancel_suave_umbral')->input('number', ['min' => 0]) ?>
        <?= $form->field($model, 'cancel_moderada_umbral')->input('number', ['min' => 0]) ?>
        <?= $form->field($model, 'cancel_ventana_dias')->input('number', ['min' => 1]) ?>
        <?= $form->field($model, 'autogestion_liberacion_vigencia_dias')->input('number', ['min' => 1]) ?>
        <?= $form->field($model, 'confirmacion_requerida')->checkbox() ?>
        <?= $form->field($model, 'permitir_cambio_modalidad')->checkbox() ?>
        <?= $form->field($model, 'recordatorios_habilitados')->checkbox() ?>
        <?= $form->field($model, 'modo_comunicacion_medicos')->dropDownList([
            EfectorTurnosConfig::MODO_MEDICOS_DESHABILITADO => 'Deshabilitado',
            EfectorTurnosConfig::MODO_MEDICOS_DIRECTO => 'Directo entre médicos',
            EfectorTurnosConfig::MODO_MEDICOS_INTERMEDIARIO => 'Con intermediario',
            EfectorTurnosConfig::MODO_MEDICOS_AUTO_ASIGNACION => 'Asignación automática',
        ]) ?>
        <?= $form->field($model, 'sobreturno_notificar_retraso')->checkbox() ?>
        <?= $form->field($model, 'sobreturno_minutos_retraso_estimado')->input('number', ['min' => 5]) ?>
        <?= $form->field($model, 'cancelacion_masiva')->checkbox() ?>
        <div class="form-group">
            <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Volver', ['view', 'id' => $efector->id_efector], ['class' => 'btn btn-outline-secondary']) ?>
        </div>
        <?php ActiveForm::end(); ?>

        <hr>
        <h5>Liberar autogestión (paciente)</h5>
        <p class="text-muted small">Tras verificación presencial o telefónica, el paciente podrá usar de nuevo la app para turnos.</p>
        <?php $f2 = ActiveForm::begin(['action' => ['liberar-autogestion', 'id' => $efector->id_efector], 'method' => 'post']); ?>
        <div class="row">
            <div class="col-md-4">
                <?= Html::label('ID persona', 'id_persona') ?>
                <?= Html::input('number', 'id_persona', '', ['class' => 'form-control', 'required' => true]) ?>
            </div>
            <div class="col-md-6">
                <?= Html::label('Motivo (opcional)', 'motivo') ?>
                <?= Html::textInput('motivo', '', ['class' => 'form-control']) ?>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <?= Html::submitButton('Registrar', ['class' => 'btn btn-warning']) ?>
            </div>
        </div>
        <?php ActiveForm::end(); ?>

        <hr>
        <h5>Últimas solicitudes entre médicos</h5>
        <?php
        $sols = SolicitudRrhh::find()->where(['id_efector' => $efector->id_efector])->orderBy(['id' => SORT_DESC])->limit(25)->all();
        ?>
        <table class="table table-sm">
            <thead><tr><th>ID</th><th>Estado</th><th>Tipo</th><th>Mensaje</th><th>Fecha</th></tr></thead>
            <tbody>
            <?php foreach ($sols as $s): ?>
                <tr>
                    <td><?= (int) $s->id ?></td>
                    <td><?= Html::encode($s->estado) ?></td>
                    <td><?= Html::encode($s->tipo) ?></td>
                    <td><?= Html::encode(mb_substr($s->mensaje, 0, 80)) ?></td>
                    <td><?= Html::encode($s->created_at) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$sols): ?>
                <tr><td colspan="5">Sin solicitudes</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

