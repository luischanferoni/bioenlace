<?php

use yii\helpers\Html;

/**
 * Sesiones de salud mental: escalas con su interpretación y notas de la sesión.
 *
 * @var yii\web\View $this
 * @var array $saludMental view model de {@see \frontend\components\Clinical\PanelSaludMentalViewBuilder::build()}
 */
?>
<div class="mb-3 pb-2 border-bottom border-2" id="tl_salud_mental_section">
    <h6 class="mb-2 text-primary"><b>SALUD MENTAL</b></h6>
    <p class="small text-muted mb-2">Últimas sesiones registradas y escalas de screening.</p>
    <?php foreach ($saludMental['sesiones'] as $sesion): ?>
        <div class="mb-3">
            <?php if ($sesion['fecha'] !== ''): ?>
                <div class="small text-uppercase text-muted mb-1"><?= Html::encode($sesion['fecha']) ?></div>
            <?php endif; ?>

            <?php if ($sesion['scores'] !== []): ?>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <?php foreach ($sesion['scores'] as $score): ?>
                        <div class="border rounded px-2 py-1">
                            <div class="small text-muted"><?= Html::encode($score['label']) ?></div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-semibold">
                                    <?= Html::encode($score['value']) ?>
                                    <span class="small text-muted"><?= Html::encode($score['unit']) ?></span>
                                </span>
                                <?php if ($score['severityLabel'] !== ''): ?>
                                    <span class="badge <?= Html::encode($score['severityClass']) ?>">
                                        <?= Html::encode($score['severityLabel']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($sesion['texts'] !== []): ?>
                <dl class="row mb-0 small">
                    <?php foreach ($sesion['texts'] as $text): ?>
                        <dt class="col-sm-3 text-muted fw-normal"><?= Html::encode($text['label']) ?></dt>
                        <dd class="col-sm-9 mb-1"><?= Html::encode($text['value']) ?></dd>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
