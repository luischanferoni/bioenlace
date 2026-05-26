<?php

use yii\helpers\Html;
/** @var yii\web\View $this */
/** @var list<array<string, mixed>> $plantillas */
/** @var int $idEfector */
/** @var bool $incluirInactivas */
/** @var list<string> $placeholders */

$this->title = 'Plantillas de epicrisis';
$this->params['breadcrumbs'][] = ['label' => 'Internaciones', 'url' => ['/site/pacientes']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="internacion-epicrisis-plantilla-index">
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h1 class="h4 mb-1"><?= Html::encode($this->title) ?></h1>
                <p class="text-muted mb-0 small">
                    Efector en sesión #<?= (int) $idEfector ?>.
                    Incluye plantillas del efector y plantillas globales (solo lectura salvo superadmin).
                </p>
            </div>
            <?= Html::a('Nueva plantilla', ['create'], ['class' => 'btn btn-primary rounded-pill']) ?>
        </div>
    </div>

    <?php if (Yii::$app->session->hasFlash('success')): ?>
        <div class="alert alert-success"><?= Html::encode((string) Yii::$app->session->getFlash('success')) ?></div>
    <?php endif; ?>
    <?php if (Yii::$app->session->hasFlash('error')): ?>
        <div class="alert alert-danger"><?= Html::encode((string) Yii::$app->session->getFlash('error')) ?></div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body py-2">
            <?= Html::a(
                $incluirInactivas ? 'Ocultar inactivas' : 'Mostrar inactivas',
                ['index', 'incluir_inactivas' => $incluirInactivas ? 0 : 1],
                ['class' => 'btn btn-sm btn-outline-secondary']
            ) ?>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <?php if ($plantillas === []): ?>
                <p class="text-muted mb-0">No hay plantillas para este efector.</p>
            <?php else: ?>
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-soft-primary">
                    <tr>
                        <th>Orden</th>
                        <th>Nombre</th>
                        <th>Ámbito</th>
                        <th>Servicio</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($plantillas as $p): ?>
                        <tr class="<?= empty($p['activo']) ? 'table-secondary' : '' ?>">
                            <td><?= (int) ($p['orden'] ?? 0) ?></td>
                            <td>
                                <?= Html::encode((string) ($p['nombre'] ?? '')) ?>
                                <?php if (!empty($p['es_global'])): ?>
                                    <span class="badge bg-info text-dark">Global</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($p['es_global'])): ?>
                                    Todos los efectores
                                <?php else: ?>
                                    Este efector
                                <?php endif; ?>
                            </td>
                            <td><?= Html::encode((string) ($p['servicio_nombre'] ?? '—')) ?></td>
                            <td>
                                <?php if (!empty($p['activo'])): ?>
                                    <span class="badge bg-success">Activa</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactiva</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if (!empty($p['editable'])): ?>
                                    <?= Html::a('Editar', ['update', 'id' => $p['id']], ['class' => 'btn btn-sm btn-outline-primary']) ?>
                                    <?php if (!empty($p['activo'])): ?>
                                        <?= Html::beginForm(['toggle-activo', 'id' => $p['id']], 'post', ['class' => 'd-inline']) ?>
                                        <input type="hidden" name="activar" value="0">
                                        <?= Html::submitButton('Desactivar', [
                                            'class' => 'btn btn-sm btn-outline-danger',
                                            'data' => ['confirm' => '¿Desactivar esta plantilla?'],
                                        ]) ?>
                                        <?= Html::endForm() ?>
                                    <?php else: ?>
                                        <?= Html::beginForm(['toggle-activo', 'id' => $p['id']], 'post', ['class' => 'd-inline']) ?>
                                        <input type="hidden" name="activar" value="1">
                                        <?= Html::submitButton('Activar', ['class' => 'btn btn-sm btn-outline-success']) ?>
                                        <?= Html::endForm() ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">Solo lectura</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <h2 class="h6">Placeholders al usar la plantilla en el alta</h2>
            <p class="small text-muted mb-2">
                Al dar el alta, el sistema reemplaza automáticamente:
            </p>
            <ul class="small mb-0">
                <?php foreach ($placeholders as $ph): ?>
                    <li><code><?= Html::encode($ph) ?></code></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
