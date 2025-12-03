<?php
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $sugerencias array */
/* @var $limite int */

$this->title = 'Sugerencias pendientes';
?>

<div class="abreviaturas-sugerencias-pendientes">
    <h1><?= Html::encode($this->title) ?></h1>
    <p>Límite: <?= $limite ?></p>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Abreviatura</th>
                <th>Expansión Propuesta</th>
                <th>Frecuencia</th>
                <th>Fecha</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($sugerencias as $s): ?>
            <tr>
                <td><?= Html::encode($s->id) ?></td>
                <td><?= Html::encode($s->abreviatura) ?></td>
                <td>
                    <?php if (!empty($s->expansion_propuesta)): ?>
                        <span class="text-success"><?= Html::encode($s->expansion_propuesta) ?></span>
                    <?php else: ?>
                        <span class="text-danger">Sin expansión</span>
                    <?php endif; ?>
                </td>
                <td><?= Html::encode($s->frecuencia_reporte) ?></td>
                <td><?= Html::encode($s->fecha_reporte) ?></td>
                <td>
                    <?php if (!empty($s->expansion_propuesta)): ?>
                        <?= Html::a('Aprobar', ['aprobar', 'id' => $s->id], ['class' => 'btn btn-success btn-sm', 'data-method' => 'post']) ?>
                    <?php else: ?>
                        <?= Html::a('Agregar expansión', ['agregar-expansion', 'id' => $s->id], ['class' => 'btn btn-primary btn-sm']) ?>
                    <?php endif; ?>
                    <?= Html::a('Rechazar', ['rechazar', 'id' => $s->id], ['class' => 'btn btn-danger btn-sm', 'data-method' => 'post']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>


