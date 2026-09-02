<?php

namespace common\components\Platform\Assistant\Context;

use Yii;

final class AssistantContextAreaAspectResolver
{
    /**
     * @param list<string> $areaIds
     * @param list<array{span: string, category: string, synonyms: list<string>}> $extractions
     */
    public static function plan(
        array $areaIds,
        array $extractions,
        string $channel,
        AssistantContextAnchorBag $anchors
    ): AssistantContextLoadPlan {
        $aspectKeys = [];
        foreach ($areaIds as $areaId) {
            if (!AssistantContextHISArea::isValid($areaId)) {
                continue;
            }
            foreach (AssistantContextAreaAspectCatalog::aspectsForArea($areaId, $extractions) as $aspect) {
                $aspectKeys[] = $aspect;
            }
        }

        $aspectKeys = self::dedupeSortByPriority($aspectKeys);
        $max = max(1, (int) (Yii::$app->params['asistente_context_max_aspects'] ?? 6));
        if (count($aspectKeys) > $max) {
            $aspectKeys = array_slice($aspectKeys, 0, $max);
        }

        return new AssistantContextLoadPlan($aspectKeys, $anchors);
    }

    /**
     * @param list<string> $aspectKeys
     * @return list<string>
     */
    private static function dedupeSortByPriority(array $aspectKeys): array
    {
        $unique = array_values(array_unique($aspectKeys));
        usort($unique, static function (string $a, string $b): int {
            return AssistantContextHISAreaAspect::priority($a) <=> AssistantContextHISAreaAspect::priority($b);
        });

        return $unique;
    }
}
