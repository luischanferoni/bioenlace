<?php

namespace common\components\Platform\Assistant\Chat\Channels\Operational;

use common\components\Platform\Assistant\IntentEngine\IntentClassifier;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;

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

        return $out;
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
