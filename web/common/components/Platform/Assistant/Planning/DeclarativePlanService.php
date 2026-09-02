<?php

namespace common\components\Platform\Assistant\Planning;

use common\components\Platform\Assistant\Catalog\SmartCatalogEntry;
use common\components\Platform\Assistant\Catalog\SmartCatalogMatchResult;
use common\components\Platform\Assistant\Context\AssistantContextAnchorBag;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectResolver;
use common\components\Platform\Assistant\Context\AssistantContextHISAreaAspect;
use Yii;

/**
 * Plan área→aspecto + tools sugeridos por match del catálogo.
 */
final class DeclarativePlanService
{
    /**
     * @param list<string> $contextAreas
     * @param list<array{span: string, category: string, synonyms: list<string>}> $extractions
     */
    public static function plan(
        array $contextAreas,
        array $extractions,
        AssistantContextAnchorBag $anchors,
        ?SmartCatalogMatchResult $match = null
    ): DeclarativePlanResult {
        $toolIds = [];
        $reasons = [];

        $loadPlan = AssistantContextAreaAspectResolver::plan(
            $contextAreas,
            $extractions,
            'smart-catalog',
            $anchors
        );
        foreach ($loadPlan->aspectKeys as $aspectKey) {
            $toolIds[] = self::aspectToolId($aspectKey);
        }
        if ($loadPlan->aspectKeys !== []) {
            $reasons[] = 'areas:' . implode(',', $contextAreas);
        }

        if ($match !== null) {
            foreach ($match->ranked as $row) {
                $toolId = trim((string) ($row['tool_id'] ?? ''));
                if ($toolId === '') {
                    continue;
                }
                if (!str_starts_with($toolId, 'aspect:') && !str_starts_with($toolId, 'article:')) {
                    continue;
                }
                if (!in_array($toolId, $toolIds, true)) {
                    $toolIds[] = $toolId;
                    $reasons[] = 'catalog:' . ($row['catalog_id'] ?? '');
                }
            }
        }

        $toolIds = array_values(array_unique($toolIds));
        $needsPlanner = false;
        $plannerReason = null;

        if ($toolIds === []) {
            $needsPlanner = true;
            $plannerReason = 'empty_plan';
        } elseif (count($toolIds) > self::maxTools()) {
            $needsPlanner = true;
            $plannerReason = 'too_many_tools';
        }

        return new DeclarativePlanResult(
            $toolIds,
            implode('; ', $reasons),
            $needsPlanner,
            $plannerReason,
        );
    }

    public static function aspectToolId(string $aspectKey): string
    {
        return 'aspect:' . AssistantContextHISAreaAspect::aspectKey($aspectKey);
    }

    public static function toolIdFromCatalogEntry(SmartCatalogEntry $entry): string
    {
        if ($entry->toolId !== '') {
            return $entry->toolId;
        }

        return '';
    }

    public static function maxTools(): int
    {
        return max(1, (int) (Yii::$app->params['asistente_plan_max_tools'] ?? 6));
    }
}
