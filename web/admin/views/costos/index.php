<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $conversaciones list<array<string, mixed>> */
/* @var $estado array<string, mixed> */
/* @var $referencia array<string, mixed> */

$this->title = 'Costos de IA';
?>
<div class="costos-index">
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">Tracking en producción</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-7">ia_usage_tracking_habilitado</dt>
                        <dd class="col-5"><?= $estado['ia_usage_tracking_habilitado'] ? 'Sí' : 'No' ?></dd>
                        <dt class="col-7">vertex_context_cache_simulado</dt>
                        <dd class="col-5"><?= $estado['vertex_context_cache_simulado'] ? 'Sí' : 'No' ?></dd>
                        <dt class="col-7">Modelo params</dt>
                        <dd class="col-5"><?= Html::encode($estado['vertex_ai_model'] ?: '—') ?></dd>
                        <dt class="col-7">Modelo referencia costos</dt>
                        <dd class="col-5"><?= Html::encode($estado['modelo_referencia']) ?></dd>
                    </dl>
                    <p class="text-muted small mt-3 mb-0">
                        Las pruebas de abajo <strong>simulan</strong> llamadas (sin HTTP). En staging/producción con tracking activo,
                        los tokens reales se acumulan en memoria del request vía <code>AICostTracker</code>.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header">Estimación de referencia (1 preprocess)</div>
                <div class="card-body">
                    <?php
                    $ej = is_array($estado['estimacion_ejemplo'] ?? null) ? $estado['estimacion_ejemplo'] : [];
                    $usd = is_array($ej['usd'] ?? null) ? $ej['usd'] : [];
                    ?>
                    <p class="mb-2">
                        Fuente tokens: <strong><?= Html::encode((string) ($ej['fuente_tokens'] ?? '')) ?></strong>
                        · Total estimado:
                        <strong>USD <?= number_format((float) ($usd['total'] ?? 0), 6) ?></strong>
                    </p>
                    <p class="text-muted small mb-0">
                        Tarifas declaradas en <code>metadata/bioenlace/ai/ai-cost-reference.yaml</code>
                        (alineado con <code>web/docs/costos/costos-api.md</code>).
                        Persistencia histórica en BD aún no implementada.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Conversaciones de prueba</span>
            <?= Html::beginForm(['ejecutar-todas'], 'post', ['class' => 'd-inline']) ?>
                <?= Html::submitButton('Ejecutar todas (simulado)', [
                    'class' => 'btn btn-sm btn-primary',
                    'data' => ['confirm' => '¿Ejecutar todas las conversaciones de prueba?'],
                ]) ?>
            <?= Html::endForm() ?>
        </div>
        <div class="card-body p-0">
            <?php if ($conversaciones === []): ?>
                <p class="p-3 text-muted mb-0">No hay conversaciones en <code>common/data/conversaciones/</code>.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Mensajes</th>
                                <th>Ruta</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($conversaciones as $item): ?>
                                <tr>
                                    <td><?= Html::encode((string) $item['nombre']) ?></td>
                                    <td><span class="badge bg-secondary"><?= Html::encode((string) $item['tipo']) ?></span></td>
                                    <td><?= (int) $item['mensajes'] ?></td>
                                    <td><code><?= Html::encode((string) $item['ruta']) ?></code></td>
                                    <td class="text-end">
                                        <?= Html::beginForm(['ejecutar'], 'post', ['class' => 'd-inline']) ?>
                                            <?= Html::hiddenInput('conversacion', (string) $item['ruta']) ?>
                                            <?= Html::submitButton('Ejecutar', ['class' => 'btn btn-sm btn-outline-primary']) ?>
                                        <?= Html::endForm() ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <p class="text-muted small">
        Ver documentación en <code>web/docs/costos/pruebas-costos-ia.md</code>
        y <code>web/docs/costos/estrategias-reduccion/monitoreo.md</code>.
    </p>
</div>
