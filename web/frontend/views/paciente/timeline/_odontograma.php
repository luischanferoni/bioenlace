<?php

use yii\helpers\Html;

/**
 * Odontograma del paciente en la historia clínica.
 *
 * @var yii\web\View $this
 * @var array $odontograma view model de {@see \frontend\components\Clinical\OdontogramaViewBuilder::build()}
 */
?>
<div class="mb-3 pb-2 border-bottom border-2" id="tl_odontograma_section">
    <h6 class="mb-2 text-primary"><b>ODONTOGRAMA</b></h6>
    <p class="small text-muted mb-2">
        Piezas con registro odontológico. Las piezas sin registro no se muestran.
    </p>
    <?php foreach ($odontograma['rows'] as $row): ?>
        <div class="mb-3">
            <div class="small text-uppercase text-muted mb-1"><?= Html::encode($row['title']) ?></div>
            <div class="d-flex flex-wrap gap-1">
                <?php foreach ($row['teeth'] as $tooth): ?>
                    <div class="border rounded p-1" style="min-width:6.5rem;">
                        <button class="btn btn-sm btn-outline-secondary w-100 text-start" type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#<?= Html::encode($tooth['target']) ?>"
                                aria-expanded="false"
                                aria-controls="<?= Html::encode($tooth['target']) ?>"
                                title="Pieza <?= Html::encode($tooth['number']) ?>">
                            <span class="fw-bold d-block"><?= Html::encode($tooth['number']) ?></span>
                            <span class="d-block small text-truncate" style="max-width:6rem;"><?= Html::encode($tooth['label']) ?></span>
                            <span class="badge <?= Html::encode($tooth['badgeClass']) ?>"><?= Html::encode($tooth['badgeLabel']) ?></span>
                        </button>
                        <div class="collapse mt-1" id="<?= Html::encode($tooth['target']) ?>">
                            <table class="table table-sm table-borderless mb-0">
                                <tbody>
                                <?php foreach ($tooth['history'] as $state): ?>
                                    <tr>
                                        <td class="small text-muted pe-1"><?= Html::encode($state['when']) ?></td>
                                        <td class="small">
                                            <?= Html::encode($state['label']) ?>
                                            <?php if ($state['surfaces'] !== ''): ?>
                                                <span class="text-muted">· caras <?= Html::encode($state['surfaces']) ?></span>
                                            <?php endif; ?>
                                            <span class="badge <?= Html::encode($state['badgeClass']) ?>"><?= Html::encode($state['badgeLabel']) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>