<?php

use common\models\BillingAccount;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Efector */
/* @var $account common\models\BillingAccount|null */
/* @var $summary array */

$this->title = $model->nombre;
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="card">
    <div class="card-header">
        <h4 class="px-3"><?= Html::encode($this->title) ?></h4>
    </div>
    <div class="card-body">
        <?= $this->render('_view_tabs', ['model' => $model, 'tab' => 'licencia']) ?>

        <?php if ($account === null): ?>
            <p class="text-muted">Este efector no está asociado a ninguna cuenta de licencia.</p>
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
            <p class="text-muted small">El cupo es compartido con los demás efectores de la misma cuenta.</p>
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
    </div>
</div>
