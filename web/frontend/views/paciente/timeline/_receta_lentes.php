<?php

use yii\helpers\Html;

/**
 * Receta de lentes del paciente.
 *
 * @var yii\web\View $this
 * @var array $receta view model de {@see \frontend\components\Clinical\RecetaLentesViewBuilder::build()}
 */
?>
<div class="mb-3 pb-2 border-bottom border-2" id="tl_receta_lentes_section">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <h6 class="mb-0 text-primary"><b>RECETA DE LENTES</b></h6>
        <?php if ($receta['registradoEn'] !== ''): ?>
            <span class="small text-muted"><?= Html::encode($receta['registradoEn']) ?></span>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col" class="small text-uppercase text-muted"></th>
                    <th scope="col" class="small text-uppercase text-muted">OD</th>
                    <th scope="col" class="small text-uppercase text-muted">OI</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($receta['rows'] as $row): ?>
                <tr>
                    <th scope="row" class="small fw-normal text-muted"><?= Html::encode($row['label']) ?></th>
                    <td><?= Html::encode($row['od'] !== '' ? $row['od'] : '—') ?></td>
                    <td><?= Html::encode($row['oi'] !== '' ? $row['oi'] : '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
