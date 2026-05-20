<?php

namespace common\components\Services\Assistant;

use Symfony\Component\Yaml\Yaml;

/**
 * Resuelve hints (id + value) desde extracciones del preprocess y bloques hint en subintents YAML.
 */
final class FlowHintService
{
    private const INTENTS_DIR = __DIR__ . '/../../Assistant/SubIntentEngine/schemas/intents';

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

        $subintents = isset($intent['subintents']) && is_array($intent['subintents']) ? $intent['subintents'] : [];
        $hints = [];
        $workingDraft = $draft;

        foreach ($subintents as $sub) {
            if (!is_array($sub) || empty($sub['hint']) || !is_array($sub['hint'])) {
                continue;
            }
            $hintCfg = $sub['hint'];
            $entity = trim((string) ($hintCfg['entity'] ?? ''));
            $matchProperty = trim((string) ($hintCfg['match_property'] ?? 'nombre'));
            if ($entity === '') {
                continue;
            }

            $extraction = self::findExtractionForEntity($extractions, $entity);
            if ($extraction === null) {
                continue;
            }

            $terms = self::termsFromExtraction($extraction);
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
     * @param list<array<string, mixed>> $extractions
     * @return array<string, mixed>|null
     */
    private static function findExtractionForEntity(array $extractions, string $entity): ?array
    {
        foreach ($extractions as $ex) {
            if (!is_array($ex)) {
                continue;
            }
            if (trim((string) ($ex['category'] ?? '')) === $entity) {
                return $ex;
            }
        }

        return null;
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
        $path = self::INTENTS_DIR . '/' . $intentId . '.yaml';
        if (!is_file($path)) {
            $path = self::INTENTS_DIR . '/' . $intentId . '.yml';
        }
        if (!is_file($path)) {
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
