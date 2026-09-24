<?php

namespace common\components\Platform\Assistant\Service;

use common\components\Platform\Assistant\Catalog\IntentSchemaPaths;
use common\components\Platform\Assistant\Catalog\StateTagIndex;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\SubIntentEngine\FlowStatechart;
use Symfony\Component\Yaml\Yaml;

/**
 * Resuelve hints (id + value) desde extracciones del preprocess y `meta.hint` de cada estado.
 */
final class FlowHintService
{
    /**
     * @param list<array<string, mixed>> $extractions
     * @return list<array{entity: string, id: string, value: string, draft_field: string}>
     */
    public static function resolveForIntent(string $intentId, array $extractions, int $userId, array $draft = []): array
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return [];
        }

        $intent = self::loadIntentYaml($intentId);
        if ($intent === null) {
            return [];
        }

        $matchedStateIds = self::matchedStateIds($intentId);
        if ($matchedStateIds === []) {
            return [];
        }

        $terms = self::termsFromExtractions($extractions);
        if ($terms === []) {
            return [];
        }

        $hints = [];
        $workingDraft = $draft;
        foreach (FlowStatechart::ordered($intent) as $sub) {
            if (!is_array($sub) || empty($sub['hint']) || !is_array($sub['hint'])) {
                continue;
            }
            $stateId = trim((string) ($sub['id'] ?? ''));
            if (!in_array($stateId, $matchedStateIds, true)) {
                continue;
            }
            $hintCfg = $sub['hint'];
            $entity = trim((string) ($hintCfg['entity'] ?? ''));
            $matchProperty = trim((string) ($hintCfg['match_property'] ?? 'nombre'));
            if ($entity === '') {
                continue;
            }
            $ctx = new HintResolutionContext($intentId, $userId, $workingDraft);
            $match = HintResolutionService::resolve($entity, $matchProperty, $terms, $ctx);
            if ($match === null) {
                continue;
            }

            $draftField = self::draftFieldFromProvides($sub['provides'] ?? []);
            if ($draftField === '') {
                continue;
            }

            $hints[] = [
                'entity' => $entity,
                'id' => (string) $match['id'],
                'value' => (string) $match['value'],
                'draft_field' => $draftField,
            ];
            $workingDraft[$draftField] = $match['id'];
        }

        return $hints;
    }

    /**
     * Query params para open_ui a partir de hints resueltos (id por draft_field).
     *
     * @param list<array<string, mixed>> $hints
     * @return array<string, string>
     */
    public static function queryParamsFromHints(array $hints): array
    {
        $q = [];
        foreach ($hints as $h) {
            if (!is_array($h)) {
                continue;
            }
            $field = trim((string) ($h['draft_field'] ?? ''));
            $id = trim((string) ($h['id'] ?? ''));
            if ($field === '' || $id === '') {
                continue;
            }
            $q[$field] = $id;
        }

        return $q;
    }

    /**
     * @param list<array<string, mixed>> $hints
     */
    public static function findHintForEntity(array $hints, string $entity): ?array
    {
        $entity = trim($entity);
        foreach ($hints as $h) {
            if (!is_array($h)) {
                continue;
            }
            if (trim((string) ($h['entity'] ?? '')) === $entity) {
                return $h;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $subintent
     */
    public static function resolveActionIdFromSubintent(array $subintent): string
    {
        $open = isset($subintent['open_ui']) && is_array($subintent['open_ui']) ? $subintent['open_ui'] : null;
        if ($open !== null && !empty($open['action_id'])) {
            return trim((string) $open['action_id']);
        }
        $chooser = isset($subintent['chooser']) && is_array($subintent['chooser']) ? $subintent['chooser'] : null;
        if ($chooser !== null) {
            foreach (['otherwise', 'when_user_says_nearby'] as $k) {
                $branch = isset($chooser[$k]) && is_array($chooser[$k]) ? $chooser[$k] : null;
                if ($branch === null) {
                    continue;
                }
                $ou = isset($branch['open_ui']) && is_array($branch['open_ui']) ? $branch['open_ui'] : null;
                if ($ou !== null && !empty($ou['action_id'])) {
                    return trim((string) $ou['action_id']);
                }
            }
        }

        return '';
    }

    /**
     * @param mixed $provides
     */
    public static function draftFieldFromProvides($provides): string
    {
        if (!is_array($provides)) {
            return '';
        }
        foreach ($provides as $p) {
            $p = is_string($p) ? trim($p) : '';
            if ($p === '' || strncmp($p, 'draft.', 6) !== 0) {
                continue;
            }

            return substr($p, 6);
        }

        return '';
    }

    /**
     * Estados de este intent cuyo meta.tags cruzó con los tags del preprocess.
     *
     * @return list<string>
     */
    private static function matchedStateIds(string $intentId): array
    {
        $hits = StateTagIndex::match(StateTagIndex::needles([
            'tags' => ChatPreprocessContext::tags(),
        ]));
        $ids = [];
        foreach ($hits as $hit) {
            if (trim((string) ($hit['intent_id'] ?? '')) !== $intentId) {
                continue;
            }
            foreach ($hit['states'] as $state) {
                $id = trim((string) ($state['id'] ?? ''));
                if ($id !== '' && !in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    /**
     * @param list<array<string, mixed>> $extractions
     * @return list<string>
     */
    private static function termsFromExtractions(array $extractions): array
    {
        $terms = [];
        foreach ($extractions as $extraction) {
            if (!is_array($extraction)) {
                continue;
            }
            foreach (self::termsFromExtraction($extraction) as $term) {
                if (!in_array($term, $terms, true)) {
                    $terms[] = $term;
                }
            }
        }

        return $terms;
    }

    /**
     * @param array<string, mixed> $extraction
     * @return list<string>
     */
    private static function termsFromExtraction(array $extraction): array
    {
        $terms = [];
        $span = isset($extraction['span']) ? trim((string) $extraction['span']) : '';
        if ($span !== '') {
            $terms[] = $span;
        }
        if (isset($extraction['synonyms']) && is_array($extraction['synonyms'])) {
            foreach ($extraction['synonyms'] as $s) {
                if (is_string($s) && trim($s) !== '') {
                    $terms[] = trim($s);
                }
            }
        }

        return $terms;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function loadIntentYaml(string $intentId): ?array
    {
        $path = IntentSchemaPaths::resolveFileForIntentId($intentId);
        if ($path === null || !is_file($path)) {
            return null;
        }

        try {
            $data = Yaml::parseFile($path);
            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
