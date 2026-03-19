<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Reprogramar turnos';
?>

<div class="container py-4">
    <h1 class="h4 mb-3"><?= Html::encode($this->title) ?></h1>
    <p class="text-muted">
        Esta pantalla es independiente del calendario de agenda. Los turnos se modifican vía API
        <code>POST api/v1/turnos/&lt;id&gt;/reprogramar</code> o desde la app móvil (misma API).
    </p>
    <p>
        <?= Html::a('Volver a turnos', ['turnos/index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
    </p>

    <?php if (empty($turnos)): ?>
        <div class="alert alert-info">No tenés turnos pendientes futuros en este efector.</div>
    <?php else: ?>
        <table class="table table-sm table-striped">
            <thead>
            <tr>
                <th>Servicio / fecha</th>
                <th>Hora</th>
                <th>Acciones sugeridas (app)</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($turnos as $t): ?>
                <?php
                $srv = $t->servicio ? $t->servicio->nombre : '—';
                ?>
                <tr>
                    <td><?= Html::encode($srv) ?> — <?= Html::encode($t->fecha) ?></td>
                    <td><?= Html::encode($t->hora) ?></td>
                    <td>
                        <span class="small text-muted">
                            Slots: <code>GET .../turnos/<?= (int) $t->id_turnos ?>/slots-alternativos</code><br>
                            Reprogramar: <code>POST .../turnos/<?= (int) $t->id_turnos ?>/reprogramar</code>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
