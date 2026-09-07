<?php

namespace common\components\Platform\Assistant\Planning;

use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;
use common\components\Platform\Core\Permission\IntentAccessService;

/**
 * Resuelve CTA(s) post-síntesis desde catálogo inteligente (sin regex).
 */
final class SynthesisCtaResolver
{
    /**
     * @return array{label: string, intent_id: string}|null Primer CTA (compat).
     */
    public static function resolve(SmartCatalogRoutingEvaluation $evaluation, int $userId): ?array
    {
        $all = self::resolveAll($evaluation, $userId);

        return $all[0] ?? null;
    }

    /**
     * @return list<array{label: string, intent_id: string}>
     */
    public static function resolveAll(SmartCatalogRoutingEvaluation $evaluation, int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $intentIds = self::resolveIntentIds($evaluation);
        if ($intentIds === []) {
            return [];
        }

        $catalog = UiActionCatalog::forUser($userId);
        $out = [];
        foreach ($intentIds as $intentId) {
            if (!IntentAccessService::userCanExecuteIntent($userId, $intentId)) {
                continue;
            }
            $item = $catalog->byActionId[$intentId] ?? null;
            $label = $item instanceof UiActionCatalogItem && $item->display_name !== ''
                ? $item->display_name
                : $intentId;
            $out[] = [
                'label' => $label,
                'intent_id' => $intentId,
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function resolveIntentIds(SmartCatalogRoutingEvaluation $evaluation): array
    {
        $fromEntry = $evaluation->decision->catalogEntry?->ctaIntentIds ?? [];
        if ($fromEntry !== []) {
            return $fromEntry;
        }

        foreach ($evaluation->match->ranked as $row) {
            $catalogId = trim((string) ($row['catalog_id'] ?? ''));
            if ($catalogId === '') {
                continue;
            }
            $entry = SmartCatalogRegistry::findById($catalogId);
            if ($entry !== null && $entry->ctaIntentIds !== []) {
                return $entry->ctaIntentIds;
            }
        }

        return [];
    }
}
