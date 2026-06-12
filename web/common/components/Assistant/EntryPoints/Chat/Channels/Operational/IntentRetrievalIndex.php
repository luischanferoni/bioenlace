<?php

namespace common\components\Assistant\EntryPoints\Chat\Channels\Operational;

use common\components\Assistant\EntryPoints\Chat\Preprocess\ChatPreprocessService;
use common\components\Assistant\IntentEngine\IntentClassificationRulesService;
use common\components\Assistant\IntentEngine\IntentClassifier;
use common\components\Assistant\IntentEngine\UiActionCatalog;
use common\components\Assistant\IntentEngine\UiActionCatalogItem;

/**
 * Top-K de intents por reglas (keywords / semantics en catálogo), sin IA.
 */
final class IntentRetrievalIndex
{
    /**
     * @return UiActionCatalogItem[]
     */
    public static function topK(string $message, UiActionCatalog $catalog, int $k = 8): array
    {
        $messageLower = mb_strtolower(trim($message), 'UTF-8');
        if ($messageLower === '' || $catalog->items === []) {
            return [];
        }

        $scored = [];
        foreach ($catalog->items as $it) {
            $score = self::scoreItem($messageLower, $it);
            if ($score > 0) {
                $scored[] = ['s' => $score, 'it' => $it];
            }
        }
        usort($scored, static function ($a, $b) {
            return (int) $b['s'] <=> (int) $a['s'];
        });

        $out = [];
        foreach (array_slice($scored, 0, max(1, $k)) as $row) {
            $out[] = $row['it'];
        }

        if ($out === []) {
            $out = array_slice($catalog->items, 0, min($k, count($catalog->items)));
        }

        return self::ensureDeclarativeFallbackInTopK($message, $catalog, $out, $k);
    }

    /**
     * Si hay fallback operativo declarativo, el intent debe entrar en top-K aunque el score base sea bajo.
     *
     * @param UiActionCatalogItem[] $items
     * @return UiActionCatalogItem[]
     */
    private static function ensureDeclarativeFallbackInTopK(
        string $message,
        UiActionCatalog $catalog,
        array $items,
        int $k
    ): array {
        if (!ChatPreprocessService::isStaffDataAccessOperationalQuery($message)) {
            return $items;
        }
        $fallback = IntentClassificationRulesService::resolveOperationalFallback($message, $catalog);
        if ($fallback === null || !$fallback['item'] instanceof UiActionCatalogItem) {
            return $items;
        }
        $target = $fallback['item'];
        foreach ($items as $it) {
            if ($it->action_id === $target->action_id) {
                return $items;
            }
        }
        array_unshift($items, $target);
        if (count($items) > $k) {
            $items = array_slice($items, 0, $k);
        }

        return $items;
    }

    private static function scoreItem(string $messageLower, UiActionCatalogItem $item): int
    {
        $score = IntentClassifier::scoreItemPublic($messageLower, $item);
        if ($score > 0) {
            return $score;
        }

        return 0;
    }
}
