<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $batch array<string, mixed> */
/* @var $estado array<string, mixed> */

$this->title = 'Resultado: todas las conversaciones';

$resumen = is_array($batch['resumen_agregado'] ?? null) ? $batch['resumen_agregado'] : [];
$estimacion = is_array($batch['estimacion_agregada'] ?? null) ? $batch['estimacion_agregada'] : [];
$resultados = is_array($batch['resultados'] ?? null) ? $batch['resultados'] : [];
?>
<div class="costos-resultado-todas">
    <p>
        <?= Html::a('← Volver al listado', ['index'], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
    </p>

    <?= $this->render('_resumen-cards', [
        'resumen' => $resumen,
        'estimacion' => $estimacion,
    ]) ?>

    <div class="card mb-4">
        <div class="card-header">Por conversación</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Conversación</th>
                            <th class="text-end">Evitadas</th>
                            <th class="text-end">Simuladas</th>
                            <th class="text-end">USD est.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultados as $resultado): ?>
                            <?php
                            $r = is_array($resultado['resumen'] ?? null) ? $resultado['resumen'] : [];
                            $e = is_array($resultado['estimacion'] ?? null) ? $resultado['estimacion'] : [];
                            $u = is_array($e['usd'] ?? null) ? $e['usd'] : [];
                            $conv = is_array($resultado['conversacion'] ?? null) ? $resultado['conversacion'] : [];
                            ?>
                            <tr>
                                <td><?= Html::encode((string) ($conv['nombre'] ?? $resultado['ruta'] ?? '')) ?></td>
                                <td class="text-end"><?= (int) ($r['total_evitadas'] ?? 0) ?></td>
                                <td class="text-end"><?= (int) ($r['llamada_simulada'] ?? 0) ?></td>
                                <td class="text-end"><?= number_format((float) ($u['total'] ?? 0), 6) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
