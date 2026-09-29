<?php

namespace common\components\Platform\Assistant\Planning;

use common\components\Platform\Assistant\Catalog\SmartCatalogMatchResult;
use common\components\Platform\Assistant\Catalog\YamlIntentManifestLoader;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectCatalog;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;
use common\components\Platform\Assistant\Context\AssistantContextHISAreaAspect;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;
use Yii;

/**
 * Shortlist RBAC para la IA planificadora (aspectos por área + intents del usuario).
 */
final class PlannerShortlistBuilder
{
    /**
     * @param array<string, mixed> $firstIa
     * @return list<array{tool_id: string, tool_type: string, description: string, param_schema: \stdClass}>
     */
    public static function build(array $firstIa, SmartCatalogMatchResult $match, int $userId): array
    {
        unset($match);
        $areas = self::expandedAreas($firstIa);
        $extractions = is_array($firstIa['extractions'] ?? null) ? $firstIa['extractions'] : [];

        $items = [];
        $seen = [];

        $add = static function (
            string $toolId,
            string $toolType,
            string $description
        ) use (&$items, &$seen): void {
            $toolId = trim($toolId);
            if ($toolId === '' || isset($seen[$toolId])) {
                return;
            }
            $seen[$toolId] = true;
            $items[] = [
                'tool_id' => $toolId,
                'tool_type' => $toolType,
                'description' => $description !== '' ? $description : $toolId,
                'param_schema' => new \stdClass(),
            ];
        };

        foreach ($areas as $areaId) {
            foreach (AssistantContextAreaAspectCatalog::aspectsForArea($areaId, $extractions) as $aspectKey) {
                if (!AssistantContextHISAreaAspect::isImplemented($aspectKey)) {
                    continue;
                }
                $add(
                    DeclarativePlanService::aspectToolId($aspectKey),
                    'aspect',
                    'Aspecto HIS ' . $aspectKey . ' (área ' . $areaId . ')'
                );
            }
        }

        if ($userId > 0) {
            $catalog = UiActionCatalog::forUser($userId);
            foreach ($catalog->items as $item) {
                $intentId = trim($item->action_id);
                if ($intentId === '') {
                    continue;
                }
                $add(
                    'intent:' . $intentId,
                    'intent',
                    self::describeIntentItem($item)
                );
            }
        }

        return array_slice($items, 0, self::maxShortlist());
    }

    /**
     * @param array<string, mixed> $firstIa
     * @return list<string>
     */
    private static function expandedAreas(array $firstIa): array
    {
        $raw = is_array($firstIa['context_areas'] ?? null) ? $firstIa['context_areas'] : [];
        $areas = [];
        foreach ($raw as $area) {
            if (is_string($area) && AssistantContextHISArea::isValid(trim($area))) {
                $areas[] = trim($area);
            }
        }

        return AssistantContextHISArea::sortByProductPriority(array_values(array_unique($areas)));
    }

    private static function describeIntentItem(UiActionCatalogItem $item): string
    {
        $name = $item->display_name !== '' ? $item->display_name : $item->action_id;
        $sem = is_array($item->intent_semantics) ? $item->intent_semantics : [];
        $objective = trim((string) ($sem['objective'] ?? ''));
        if ($objective !== '') {
            return $name . ' — ' . $objective;
        }
        $manifest = YamlIntentManifestLoader::load($item->action_id);
        if (is_array($manifest)) {
            $actionName = trim((string) ($manifest['action_name'] ?? ''));
            if ($actionName !== '') {
                return $actionName;
            }
        }

        return 'Intent trámite: ' . $item->action_id;
    }

    public static function maxShortlist(): int
    {
        return max(4, (int) (Yii::$app->params['asistente_planner_max_shortlist'] ?? 12));
    }
}
