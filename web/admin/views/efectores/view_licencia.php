<?php

use common\models\BillingAccount;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Efector */
/* @var $account common\models\BillingAccount|null */
/* @var $summary array */
/* @var $affiliations list<array{id: int, nombre: string, tipo: string}> */

$this->title = $model->nombre;
$this->params['breadcrumbs'][] = $this->title;
$affiliations = $affiliations ?? [];
?>
<div class="card">
    <div class="card-header">
        <h4 class="px-3"><?= Html::encode($this->title) ?></h4>
    </div>
    <div class="card-body">
        <?= $this->render('_view_tabs', ['model' => $model, 'tab' => 'licencia']) ?>

        <h5 class="mt-3">Cuenta de facturación (pool)</h5>
        <?php if ($account === null): ?>
            <p class="text-muted">Este efector no consume cupo de ninguna cuenta (sin membresía Pool).</p>
            <p><?= Html::a('Ir a Licencias / Contratos', ['/billing-account/index'], ['class' => 'btn btn-primary']) ?></p>
        <?php else: ?>
            <p>
                <strong>Cuenta:</strong>
                <?= Html::a(
                    Html::encode($account->nombre),
                    ['/billing-account/view', 'id' => $account->id]
                ) ?>
                (<?= Html::encode(BillingAccount::tipoOptions()[$account->tipo] ?? $account->tipo) ?>)
            </p>
            <p class="text-muted small">El cupo es compartido con los demás efectores Pool de la misma cuenta.</p>
            <table class="table table-bordered table-sm">
                <thead>
                <tr>
                    <th>Clase</th>
                    <th>Máx.</th>
                    <th>Uso (pool)</th>
                    <th>Pending</th>
                    <th>Dictado</th>
                    <th>Videollamada</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($summary as $row): ?>
                    <tr>
                        <td><?= Html::encode($row['label']) ?> (<?= Html::encode($row['code']) ?>)</td>
                        <td><?= $row['max_pes'] !== null ? (int) $row['max_pes'] : '—' ?></td>
                        <td><?= (int) $row['used'] ?></td>
                        <td>
                            <?php if ($row['pending_max_pes'] !== null): ?>
                                <?= (int) $row['pending_max_pes'] ?> (<?= Html::encode($row['pending_effective_on'] ?? '') ?>)
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= !empty($row['dictado_incluido']) ? 'Sí' : 'No' ?></td>
                        <td><?= !empty($row['videollamada_permitida']) ? 'Sí' : 'No' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($summary === []): ?>
                    <tr><td colspan="6" class="text-muted">Sin clases contratadas en la cuenta.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?= Html::a('Editar contrato', ['/billing-account/view', 'id' => $account->id], ['class' => 'btn btn-primary']) ?>
        <?php endif; ?>

        <h5 class="mt-4">Afiliaciones (sin cupo)</h5>
        <?php if ($affiliations === []): ?>
            <p class="text-muted small">No está afiliado a ninguna cuenta ministerio/red.</p>
        <?php else: ?>
            <ul class="list-unstyled">
                <?php foreach ($affiliations as $aff): ?>
                    <li class="mb-1">
                        <?= Html::a(
                            Html::encode($aff['nombre']),
                            ['/billing-account/view', 'id' => $aff['id']]
                        ) ?>
                        <span class="text-muted">
                            (<?= Html::encode(BillingAccount::tipoOptions()[$aff['tipo']] ?? $aff['tipo']) ?>)
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
