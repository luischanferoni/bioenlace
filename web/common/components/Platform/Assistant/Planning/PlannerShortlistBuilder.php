<?php

namespace common\components\Platform\Assistant\Planning;

use common\components\Platform\Assistant\Catalog\SmartCatalogEntry;
use common\components\Platform\Assistant\Catalog\SmartCatalogMatchResult;
use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectCatalog;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;
use common\components\Platform\Assistant\Context\AssistantContextHISAreaAspect;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;
use Yii;

/**
 * Shortlist RBAC para la IA planificadora.
 */
final class PlannerShortlistBuilder
{
    private const MIN_MATCH_SCORE = 30;

    /**
     * @param array<string, mixed> $firstIa
     * @return list<array{tool_id: string, tool_type: string, description: string, param_schema: \stdClass}>
     */
    public static function build(array $firstIa, SmartCatalogMatchResult $match, int $userId): array
    {
        $areas = self::expandedAreas($firstIa, $match);
        $tags = self::normalizeTags($firstIa['tags'] ?? []);
        $extractions = is_array($firstIa['extractions'] ?? null) ? $firstIa['extractions'] : [];
        $allowedIntents = self::allowedIntentIdsForUser($userId);

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

        foreach (SmartCatalogRegistry::entries() as $entry) {
            if ($entry->matchOnly || !$entry->isExecutable() || $entry->toolId === '') {
                continue;
            }
            if (!self::entryRelevant($entry, $areas, $tags, $match)) {
                continue;
            }
            if ($entry->toolType === 'intent') {
                if (!isset($allowedIntents[$entry->toolRef])) {
                    continue;
                }
                $add($entry->toolId, 'intent', self::describeIntent($entry, $userId));
                continue;
            }
            $add($entry->toolId, $entry->toolType, self::describeCatalogEntry($entry));
        }

        $max = self::maxShortlist();

        return array_slice($items, 0, $max);
    }

    /**
     * @param array<string, mixed> $firstIa
     * @return list<string>
     */
    private static function expandedAreas(array $firstIa, SmartCatalogMatchResult $match): array
    {
        $raw = is_array($firstIa['context_areas'] ?? null) ? $firstIa['context_areas'] : [];
        $areas = [];
        foreach ($raw as $area) {
            if (is_string($area) && AssistantContextHISArea::isValid(trim($area))) {
                $areas[] = trim($area);
            }
        }

        foreach ($match->ranked as $row) {
            $catalogId = trim((string) ($row['catalog_id'] ?? ''));
            if ($catalogId === '') {
                continue;
            }
            $entry = SmartCatalogRegistry::findById($catalogId);
            if ($entry === null) {
                continue;
            }
            foreach ($entry->triggerContextAreas as $triggerArea) {
                if (AssistantContextHISArea::isValid($triggerArea)) {
                    $areas[] = $triggerArea;
                }
            }
        }

        return AssistantContextHISArea::sortByProductPriority(array_values(array_unique($areas)));
    }

    /**
     * @param list<string> $areas
     * @param list<string> $tags
     */
    private static function entryRelevant(
        SmartCatalogEntry $entry,
        array $areas,
        array $tags,
        SmartCatalogMatchResult $match
    ): bool {
        foreach ($match->ranked as $row) {
            if (($row['catalog_id'] ?? '') === $entry->id && (int) ($row['score'] ?? 0) >= self::MIN_MATCH_SCORE) {
                return true;
            }
        }

        if ($areas !== [] && $entry->triggerContextAreas !== []) {
            foreach ($entry->triggerContextAreas as $triggerArea) {
                if (in_array($triggerArea, $areas, true)) {
                    return true;
                }
            }
        }

        if ($tags !== [] && $entry->triggerTags !== []) {
            foreach ($entry->triggerTags as $triggerTag) {
                if (in_array($triggerTag, $tags, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function describeCatalogEntry(SmartCatalogEntry $entry): string
    {
        if ($entry->toolType === 'article') {
            return 'Artículo informativo: ' . $entry->toolRef;
        }
        if ($entry->toolType === 'aspect') {
            return 'Aspecto HIS: ' . $entry->toolRef;
        }

        return 'Tool catálogo ' . $entry->id;
    }

    private static function describeIntent(SmartCatalogEntry $entry, int $userId): string
    {
        if ($userId <= 0) {
            return 'Intent trámite: ' . $entry->toolRef;
        }

        $catalog = UiActionCatalog::forUser($userId);
        $item = $catalog->byActionId[$entry->toolRef] ?? null;
        if ($item instanceof UiActionCatalogItem) {
            $name = $item->display_name !== '' ? $item->display_name : $entry->toolRef;
            $desc = trim((string) ($item->description ?? ''));

            return $desc !== '' ? $name . ' — ' . $desc : $name;
        }

        return 'Intent trámite: ' . $entry->toolRef;
    }

    /**
     * @return array<string, true>
     */
    private static function allowedIntentIdsForUser(int $userId): array
    {
        $catalog = UiActionCatalog::forUser($userId);
        $out = [];
        foreach ($catalog->items as $item) {
            $id = trim($item->action_id);
            if ($id !== '') {
                $out[$id] = true;
            }
        }

        return $out;
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private static function normalizeTags(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $tag) {
            if (!is_string($tag)) {
                continue;
            }
            $tag = mb_strtolower(trim($tag), 'UTF-8');
            if ($tag !== '' && !in_array($tag, $out, true)) {
                $out[] = $tag;
            }
        }

        return $out;
    }

    public static function maxShortlist(): int
    {
        return max(4, (int) (Yii::$app->params['asistente_planner_max_shortlist'] ?? 12));
    }
}
