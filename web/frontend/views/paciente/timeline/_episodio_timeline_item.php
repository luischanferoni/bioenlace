<?php

/**
 * Ítem agrupado del registro de episodio (HC).
 *
 * @var array{
 *   type: string,
 *   type_label: string,
 *   badge_class: string,
 *   occurred_at: string,
 *   actor: string,
 *   parts: list<array{text: string, status: string}>
 * } $group
 */

use yii\helpers\Html;

$type = (string) ($group['type'] ?? '');
$typeLabel = (string) ($group['type_label'] ?? 'Hito');
$badgeClass = (string) ($group['badge_class'] ?? 'text-bg-light border text-dark');
$occurredAt = (string) ($group['occurred_at'] ?? '');
$actor = (string) ($group['actor'] ?? '');
$parts = is_array($group['parts'] ?? null) ? $group['parts'] : [];
$count = count($parts);
$badgeText = $typeLabel . ($count > 1 ? ' · ' . $count : '');
?>
<li class="tl-episodio-timeline__item py-2" data-tl-type="<?= Html::encode($type) ?>">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
        <span class="badge <?= Html::encode($badgeClass) ?>"><?= Html::encode($badgeText) ?></span>
        <?php if ($occurredAt !== ''): ?>
            <span class="text-muted small"><?= Html::encode($occurredAt) ?></span>
        <?php endif; ?>
        <?php if ($actor !== ''): ?>
            <span class="text-muted small"><?= Html::encode($actor) ?></span>
        <?php endif; ?>
    </div>
    <?php if ($count === 1): ?>
        <?php
        $one = $parts[0];
        $text = (string) ($one['text'] ?? '');
        $status = (string) ($one['status'] ?? '');
        ?>
        <div class="small text-break">
            <?= Html::encode($text) ?>
            <?php if ($status !== ''): ?>
                <span class="text-muted">(<?= Html::encode($status) ?>)</span>
            <?php endif; ?>
        </div>
    <?php elseif ($count > 1): ?>
        <ul class="mb-0 ps-3 small">
            <?php foreach ($parts as $part): ?>
                <?php
                $text = (string) ($part['text'] ?? '');
                $status = (string) ($part['status'] ?? '');
                ?>
                <li class="mb-1">
                    <?= Html::encode($text) ?>
                    <?php if ($status !== ''): ?>
                        <span class="text-muted">(<?= Html::encode($status) ?>)</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</li>
