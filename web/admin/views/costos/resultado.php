<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $titulo string */
/* @var $resultado array<string, mixed> */
/* @var $estado array<string, mixed> */

$this->title = $titulo;

$resumen = is_array($resultado['resumen'] ?? null) ? $resultado['resumen'] : [];
$estimacion = is_array($resultado['estimacion'] ?? null) ? $resultado['estimacion'] : [];
$tokens = is_array($resumen['tokens'] ?? null) ? $resumen['tokens'] : [];
$usd = is_array($estimacion['usd'] ?? null) ? $estimacion['usd'] : [];
$detalle = is_array($resultado['detalle'] ?? null) ? $resultado['detalle'] : [];
$porContexto = is_array($resumen['por_contexto'] ?? null) ? $resumen['por_contexto'] : [];
?>
<div class="costos-resultado">
    <p>
        <?= Html::a('← Volver al listado', ['index'], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
    </p>

    <?= $this->render('_resumen-cards', [
        'resumen' => $resumen,
        'estimacion' => $estimacion,
    ]) ?>

    <div class="card mb-4">
        <div class="card-header">Desglose por contexto IAManager</div>
        <div class="card-body p-0">
            <?php if ($porContexto === []): ?>
                <p class="p-3 text-muted mb-0">Sin llamadas registradas por contexto en esta ejecución.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Contexto</th>
                                <th class="text-end">Llamadas</th>
                                <th class="text-end">Prompt</th>
                                <th class="text-end">Cache</th>
                                <th class="text-end">Output</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($porContexto as $ctx => $stats): ?>
                                <?php if (!is_array($stats)) {
                                    continue;
                                } ?>
                                <tr>
                                    <td><code><?= Html::encode((string) $ctx) ?></code></td>
                                    <td class="text-end"><?= (int) ($stats['llamadas'] ?? 0) ?></td>
                                    <td class="text-end"><?= (int) ($stats['prompt_tokens'] ?? 0) ?></td>
                                    <td class="text-end"><?= (int) ($stats['cached_tokens'] ?? 0) ?></td>
                                    <td class="text-end"><?= (int) ($stats['candidates_tokens'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Mensajes de la conversación</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Mensaje usuario</th>
                            <th>Goal</th>
                            <th>Éxito</th>
                            <th>Respuesta (extracto)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalle as $fila): ?>
                            <tr>
                                <td><?= (int) ($fila['indice'] ?? 0) + 1 ?></td>
                                <td><?= Html::encode((string) ($fila['mensaje'] ?? '')) ?></td>
                                <td><code><?= Html::encode((string) ($fila['user_goal'] ?? '')) ?></code></td>
                                <td><?= !empty($fila['exito']) ? 'Sí' : 'No' ?></td>
                                <td class="small"><?= Html::encode(\yii\helpers\StringHelper::truncate((string) ($fila['respuesta'] ?? ''), 120)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <details class="mb-4">
        <summary class="text-muted small">JSON crudo del resumen</summary>
        <pre class="small bg-light p-3 border rounded mt-2"><?= Html::encode(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    </details>
</div>
