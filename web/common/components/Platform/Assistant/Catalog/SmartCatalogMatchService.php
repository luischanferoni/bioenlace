<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Assistant\Context\AssistantContextHISArea;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;

/**
 * Match de etiquetas 1ª IA contra {@see SmartCatalogRegistry}.
 */
final class SmartCatalogMatchService
{
    private const MIN_SCORE = 30;

    private const CLEAR_MARGIN = 15;

    private const SCORE_TAG = 25;

    private const SCORE_CONTEXT_AREA = 20;

    private const SCORE_PHRASE = 35;

    private const SCORE_KEYWORD = 15;

    private const SCORE_INTENT_HINT = 40;

    /**
     * @param array{
     *   normalized_text?: string,
     *   tags?: list<string>,
     *   context_areas?: list<string>,
     *   intent_ids_hint?: list<string>,
     *   routing_hint?: string
     * } $firstIa
     */
    public static function match(array $firstIa, int $userId): SmartCatalogMatchResult
    {
        $normalized = self::fold(trim((string) ($firstIa['normalized_text'] ?? '')));
        $tags = self::foldList($firstIa['tags'] ?? []);
        $areas = self::normalizeAreas($firstIa['context_areas'] ?? []);
        $intentHints = self::normalizeIntentHints($firstIa['intent_ids_hint'] ?? []);

        $allowedIntentIds = self::allowedIntentIdsForUser($userId);

        $ranked = [];
        foreach (SmartCatalogRegistry::entries() as $entry) {
            if (!$entry->matchOnly && $entry->toolType === 'intent' && $entry->toolRef !== '') {
                if (!isset($allowedIntentIds[$entry->toolRef])) {
                    continue;
                }
            }

            $score = self::scoreEntry($entry, $normalized, $tags, $areas, $intentHints);
            if ($score <= 0) {
                continue;
            }

            $ranked[] = [
                'catalog_id' => $entry->id,
                'tool_id' => $entry->toolId,
                'score' => $score,
                'routing_result' => $entry->routingResult,
                '_entry' => $entry,
            ];
        }

        usort(
            $ranked,
            static function (array $a, array $b): int {
                $scoreCmp = (int) $b['score'] <=> (int) $a['score'];
                if ($scoreCmp !== 0) {
                    return $scoreCmp;
                }
                /** @var SmartCatalogEntry $entryA */
                $entryA = $a['_entry'];
                /** @var SmartCatalogEntry $entryB */
                $entryB = $b['_entry'];

                return $entryB->priority <=> $entryA->priority;
            }
        );

        $publicRanked = [];
        foreach ($ranked as $row) {
            $publicRanked[] = [
                'catalog_id' => $row['catalog_id'],
                'tool_id' => $row['tool_id'],
                'score' => (int) $row['score'],
                'routing_result' => $row['routing_result'],
            ];
        }

        $best = $ranked[0] ?? null;
        $second = $ranked[1] ?? null;
        $bestScore = $best !== null ? (int) $best['score'] : 0;
        $bestEntry = $best !== null ? $best['_entry'] : null;

        $isClearWinner = $best !== null
            && $bestScore >= self::MIN_SCORE
            && (
                $second === null
                || $bestScore - (int) $second['score'] >= self::CLEAR_MARGIN
            );

        return new SmartCatalogMatchResult($publicRanked, $bestEntry, $bestScore, $isClearWinner);
    }

    /**
     * @param list<string> $tags
     * @param list<string> $areas
     * @param array<string, true> $intentHints
     */
    private static function scoreEntry(
        SmartCatalogEntry $entry,
        string $normalized,
        array $tags,
        array $areas,
        array $intentHints
    ): int {
        if (
            $entry->triggerTags === []
            && $entry->triggerContextAreas === []
            && $entry->triggerPhrases === []
            && $entry->triggerKeywords === []
        ) {
            return 0;
        }

        $score = (int) floor($entry->priority / 10);

        foreach ($entry->triggerTags as $triggerTag) {
            // El preprocess copia cada context_area a tags; no puntuar dos veces el mismo eje.
            if (in_array($triggerTag, $entry->triggerContextAreas, true)) {
                continue;
            }
            if (in_array($triggerTag, $tags, true)) {
                $score += self::SCORE_TAG;
            }
        }

        foreach ($entry->triggerContextAreas as $triggerArea) {
            if (in_array($triggerArea, $areas, true)) {
                $score += self::SCORE_CONTEXT_AREA;
            }
        }

        if ($normalized !== '') {
            foreach ($entry->triggerPhrases as $phrase) {
                if ($phrase !== '' && str_contains($normalized, $phrase)) {
                    $score += self::SCORE_PHRASE;
                }
            }
            foreach ($entry->triggerKeywords as $keyword) {
                if ($keyword !== '' && str_contains($normalized, $keyword)) {
                    $score += self::SCORE_KEYWORD;
                }
            }
        }

        if ($entry->toolType === 'intent' && $entry->toolRef !== '' && isset($intentHints[$entry->toolRef])) {
            $score += self::SCORE_INTENT_HINT;
        }

        return $score;
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
    private static function foldList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = self::fold(trim($item));
            if ($item !== '' && !in_array($item, $out, true)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private static function normalizeAreas(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        return AssistantContextHISArea::sortByProductPriority(
            array_values(array_filter($raw, static fn ($v): bool => is_string($v)))
        );
    }

    /**
     * @param mixed $raw
     * @return array<string, true>
     */
    private static function normalizeIntentHints(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item !== '') {
                $out[$item] = true;
            }
        }

        return $out;
    }

    private static function fold(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }
}
