<?php

namespace common\components\Platform\Assistant\Chat\Channels\Guide;

use common\components\Platform\Assistant\Catalog\IntentSemanticsPromptFormatter;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;
use Yii;

/**
 * Subset de semántica de intents para el prompt guide según áreas HIS activas.
 */
final class GuideIntentSemanticsFilter
{
    /**
     * @param list<string> $activeAreas
     */
    public static function formatPromptSection(UiActionCatalog $catalog, array $activeAreas): string
    {
        $max = max(1, (int) (Yii::$app->params['asistente_guide_max_intent_semantics'] ?? 6));
        $items = self::forFocus($activeAreas, $catalog, $max);
        if ($items === []) {
            return '';
        }

        $ids = [];
        foreach ($items as $item) {
            if ($item instanceof UiActionCatalogItem) {
                $ids[] = $item->action_id;
            }
        }

        return IntentSemanticsPromptFormatter::formatForIntentIds($ids, $max);
    }

    /**
     * @param list<string> $activeAreas
     * @return list<UiActionCatalogItem>
     */
    public static function forFocus(array $activeAreas, UiActionCatalog $catalog, int $max): array
    {
        if ($activeAreas === []) {
            return [];
        }

        $areaSet = [];
        foreach ($activeAreas as $area) {
            if (is_string($area) && trim($area) !== '') {
                $areaSet[trim($area)] = true;
            }
        }
        if ($areaSet === []) {
            return [];
        }

        $out = [];
        foreach ($catalog->items as $item) {
            if ($item->his_areas === []) {
                continue;
            }
            if (!self::intersectsAreas($areaSet, $item->his_areas)) {
                continue;
            }
            $out[] = $item;
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param array<string, true> $activeSet
     * @param list<string> $hisAreas
     */
    private static function intersectsAreas(array $activeSet, array $hisAreas): bool
    {
        foreach ($hisAreas as $area) {
            if (!is_string($area)) {
                continue;
            }
            $area = trim($area);
            if ($area !== '' && isset($activeSet[$area])) {
                return true;
            }
        }

        return false;
    }
}
