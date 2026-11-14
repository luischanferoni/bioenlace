<?php

use yii\helpers\Html;

/**
 * Panel de valores con unidades (control prenatal, evaluación nutricional, sesiones).
 *
 * @var yii\web\View $this
 * @var array $panel view model de {@see \frontend\components\Clinical\PanelValoresViewBuilder::build()}
 * @var string $titulo
 * @var string $subtitulo
 */
?>
<div class="mb-3 pb-2 border-bottom border-2">
    <h6 class="mb-2 text-primary"><b><?= Html::encode(mb_strtoupper($titulo)) ?></b></h6>
    <p class="small text-muted mb-2"><?= Html::encode($subtitulo) ?></p>
    <?php foreach ($panel['paneles'] as $item): ?>
        <div class="mb-3">
            <?php if ($item['fecha'] !== ''): ?>
                <div class="small text-uppercase text-muted mb-1"><?= Html::encode($item['fecha']) ?></div>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($item['rows'] as $row): ?>
                    <div class="border rounded px-2 py-1">
                        <div class="small text-muted"><?= Html::encode($row['label']) ?></div>
                        <div class="fw-semibold">
                            <?= Html::encode($row['value']) ?>
                            <?php if ($row['unit'] !== ''): ?>
                                <span class="small text-muted"><?= Html::encode($row['unit']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>