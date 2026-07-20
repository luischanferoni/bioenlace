<?php

use common\models\Clinical\CareProtocol;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var list<array<string, mixed>> $protocolos */
/** @var bool $incluirDeshabilitados */
/** @var array<int, string> $provincias */

$this->title = 'Protocolos de cuidado';
$this->params['breadcrumbs'][] = ['label' => 'Datos', 'url' => ['#']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="care-protocol-index">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="header-title d-flex align-items-self justify-content-between flex-wrap gap-2">
                    <h2 class="card-title mt-1"><?= Html::encode($this->title) ?></h2>
                    <?= Html::a('Nuevo protocolo', ['create'], ['class' => 'btn btn-success']) ?>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Catálogo Nación/Provincia para el hub Control/Seguimiento (controles recomendados, vacunas, preventivos).
                    No confundir con tratamientos (CarePlan) del paciente.
                </p>

                <?php if (Yii::$app->session->hasFlash('success')): ?>
                    <div class="alert alert-success"><?= Html::encode((string) Yii::$app->session->getFlash('success')) ?></div>
                <?php endif; ?>
                <?php if (Yii::$app->session->hasFlash('error')): ?>
                    <div class="alert alert-danger"><?= Html::encode((string) Yii::$app->session->getFlash('error')) ?></div>
                <?php endif; ?>

                <p class="mb-3">
                    <?= Html::a(
                        $incluirDeshabilitados ? 'Ocultar deshabilitados' : 'Mostrar deshabilitados',
                        ['index', 'incluir_deshabilitados' => $incluirDeshabilitados ? 0 : 1],
                        ['class' => 'btn btn-sm btn-outline-secondary']
                    ) ?>
                </p>

                <?php if ($protocolos === []): ?>
                    <p class="text-muted mb-0">No hay protocolos cargados.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Orden</th>
                                <th>Clave</th>
                                <th>Título</th>
                                <th>Alcance</th>
                                <th>Match</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($protocolos as $p): ?>
                                <?php
                                $scope = (string) ($p['scope_type'] ?? '');
                                $provId = isset($p['id_provincia']) ? (int) $p['id_provincia'] : 0;
                                $scopeLabel = $scope === CareProtocol::SCOPE_PROVINCE
                                    ? ('Provincia: ' . ($provincias[$provId] ?? ('#' . $provId)))
                                    : 'Nación';
                                ?>
                                <tr class="<?= empty($p['enabled']) ? 'table-secondary' : '' ?>">
                                    <td><?= (int) ($p['orden'] ?? 0) ?></td>
                                    <td><code><?= Html::encode((string) ($p['protocol_key'] ?? '')) ?></code></td>
                                    <td>
                                        <?= Html::encode((string) ($p['title'] ?? '')) ?>
                                        <?php if (!empty($p['hub_label']) && $p['hub_label'] !== $p['title']): ?>
                                            <div class="small text-muted"><?= Html::encode((string) $p['hub_label']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= Html::encode($scopeLabel) ?></td>
                                    <td><code><?= Html::encode((string) ($p['condition_match'] ?? '')) ?></code></td>
                                    <td>
                                        <?php if (!empty($p['enabled'])): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Deshabilitado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <?= Html::a('Editar', ['update', 'id' => $p['id']], ['class' => 'btn btn-sm btn-outline-primary']) ?>
                                        <?php if (!empty($p['enabled'])): ?>
                                            <?= Html::beginForm(['toggle-enabled', 'id' => $p['id']], 'post', ['class' => 'd-inline']) ?>
                                            <input type="hidden" name="activar" value="0">
                                            <?= Html::submitButton('Desactivar', [
                                                'class' => 'btn btn-sm btn-outline-danger',
                                                'data' => ['confirm' => '¿Desactivar este protocolo?'],
                                            ]) ?>
                                            <?= Html::endForm() ?>
                                        <?php else: ?>
                                            <?= Html::beginForm(['toggle-enabled', 'id' => $p['id']], 'post', ['class' => 'd-inline']) ?>
                                            <input type="hidden" name="activar" value="1">
                                            <?= Html::submitButton('Activar', ['class' => 'btn btn-sm btn-outline-success']) ?>
                                            <?= Html::endForm() ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
