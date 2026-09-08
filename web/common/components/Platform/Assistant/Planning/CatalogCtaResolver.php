<?php

namespace common\components\Platform\Assistant\Planning;

use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;
use common\components\Platform\Assistant\Catalog\YamlIntentManifestLoader;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;

/**
 * CTA(s) post-2ª IA (incompletas) desde catálogo inteligente (sin regex).
 *
 * El smart-catalog declara qué ofrecer; el label sale de action_name / catálogo.
 * La autorización de ejecución sigue en ChatOrchestrator al lanzar el intent.
 */
final class CatalogCtaResolver
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

        $intentIds = self::declaredIntentIds($evaluation);
        if ($intentIds === []) {
            return [];
        }

        $catalog = UiActionCatalog::forUser($userId);
        $out = [];
        foreach ($intentIds as $intentId) {
            $label = self::labelForIntent($intentId, $catalog);
            if ($label === '') {
                continue;
            }
            $out[] = [
                'label' => $label,
                'intent_id' => $intentId,
            ];
        }

        return $out;
    }

    /**
     * Intent ids declarados por el match (sin filtrar por usuario).
     *
     * @return list<string>
     */
    public static function declaredIntentIds(SmartCatalogRoutingEvaluation $evaluation): array
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

    private static function labelForIntent(string $intentId, UiActionCatalog $catalog): string
    {
        $item = $catalog->byActionId[$intentId] ?? null;
        if ($item instanceof UiActionCatalogItem && $item->display_name !== '') {
            return $item->display_name;
        }

        $manifest = YamlIntentManifestLoader::load($intentId);
        if ($manifest === null) {
            return '';
        }
        $name = trim((string) ($manifest['action_name'] ?? ''));

        return $name !== '' ? $name : $intentId;
    }
}
