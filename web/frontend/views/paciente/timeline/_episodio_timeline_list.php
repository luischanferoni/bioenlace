<?php

/**
 * Lista completa del registro de episodio (todos los hitos).
 * El JS filtra con show/hide sobre [data-tl-type].
 *
 * @var list<array<string, mixed>> $groups
 * @var int $itemCount
 */

use yii\helpers\Html;

$groups = is_array($groups ?? null) ? $groups : [];
$itemCount = (int) ($itemCount ?? 0);
?>
<?php if ($groups === []): ?>
    <p class="text-muted mb-0 small" data-tl-empty-all>Sin hitos registrados en este episodio.</p>
<?php else: ?>
    <ul class="tl-episodio-timeline list-unstyled mb-0" data-tl-timeline-ul data-tl-item-count="<?= (int) $itemCount ?>">
        <?php foreach ($groups as $group): ?>
            <?= $this->render('_episodio_timeline_item', ['group' => $group]) ?>
        <?php endforeach; ?>
    </ul>
    <p class="text-muted mb-0 small d-none" data-tl-empty-filter>Sin hitos para este filtro.</p>
<?php endif; ?>
<?php if ($itemCount > 0): ?>
    <span class="d-none" data-tl-count-source><?= Html::encode($itemCount . ' hito' . ($itemCount === 1 ? '' : 's')) ?></span>
<?php endif; ?>
