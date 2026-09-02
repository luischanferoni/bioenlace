<?php

namespace common\components\Platform\Assistant\Planning;

use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;
use common\components\Platform\Core\Permission\IntentAccessService;

/**
 * Resuelve CTA post-síntesis desde catálogo inteligente (sin regex).
 */
final class SynthesisCtaResolver
{
    /**
     * @return array{label: string, intent_id: string}|null
     */
    public static function resolve(SmartCatalogRoutingEvaluation $evaluation, int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $intentId = self::resolveIntentId($evaluation);
        if ($intentId === '' || !IntentAccessService::userCanExecuteIntent($userId, $intentId)) {
            return null;
        }

        $catalog = UiActionCatalog::forUser($userId);
        $item = $catalog->byActionId[$intentId] ?? null;
        $label = $item instanceof UiActionCatalogItem && $item->display_name !== ''
            ? $item->display_name
            : $intentId;

        return [
            'label' => $label,
            'intent_id' => $intentId,
        ];
    }

    private static function resolveIntentId(SmartCatalogRoutingEvaluation $evaluation): string
    {
        $fromEntry = trim((string) ($evaluation->decision->catalogEntry?->ctaIntentId ?? ''));
        if ($fromEntry !== '') {
            return $fromEntry;
        }

        foreach ($evaluation->match->ranked as $row) {
            $catalogId = trim((string) ($row['catalog_id'] ?? ''));
            if ($catalogId === '') {
                continue;
            }
            $entry = SmartCatalogRegistry::findById($catalogId);
            if ($entry !== null && $entry->ctaIntentId !== '') {
                return $entry->ctaIntentId;
            }
        }

        return '';
    }
}
