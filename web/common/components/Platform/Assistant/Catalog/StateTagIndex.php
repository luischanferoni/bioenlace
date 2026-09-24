<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Assistant\Chat\Preprocess\ChatChannelPolicy;
use Symfony\Component\Yaml\Yaml;

/**
 * Cruza los tags que inventa el preprocess con `meta.tags` de cada estado.
 * El tag del estado es una palabra (sintomas, condiciones, estudio, turno). No es una frase.
 */
final class StateTagIndex
{
    /** @var list<array{intent_id: string, states: list<array{id: string, description: string, tags: list<string>}>}>|null */
    private static $index = null;

    /** @var list<string>|null */
    private static $vocabulary = null;

    public static function resetCacheForTests(): void
    {
        self::$index = null;
        self::$vocabulary = null;
    }

    public static function hasTag(string $tag): bool
    {
        $folded = ChatChannelPolicy::fold(trim($tag));
        if ($folded === '') {
            return false;
        }
        foreach (self::index() as $intent) {
            foreach ($intent['states'] as $state) {
                if (in_array($folded, $state['tags'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Categorías declaradas en los estados, para el prompt del preprocess.
     */
    public static function listForPrompt(): string
    {
        self::index();
        $lines = [];
        foreach (self::$vocabulary ?? [] as $tag) {
            $lines[] = '- ' . $tag;
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $firstIa
     * @return list<string>
     */
    public static function needles(array $firstIa, string $message = ''): array
    {
        $parts = [];
        $tags = $firstIa['tags'] ?? [];
        if (is_array($tags)) {
            foreach ($tags as $tag) {
                if (is_string($tag) && trim($tag) !== '') {
                    $parts[] = trim($tag);
                }
            }
        }

        return $parts;
    }

    /**
     * @param list<string> $needles
     * @return list<array{intent_id: string, score: int, states: list<array{id: string, description: string}>}>
     */
    public static function match(array $needles): array
    {
        $foldedNeedles = [];
        foreach ($needles as $needle) {
            $folded = ChatChannelPolicy::fold($needle);
            if (strlen($folded) >= 4) {
                $foldedNeedles[] = $folded;
            }
        }
        if ($foldedNeedles === []) {
            return [];
        }

        $ranked = [];
        foreach (self::index() as $intent) {
            $states = [];
            $score = 0;
            foreach ($intent['states'] as $state) {
                $hits = 0;
                foreach ($state['tags'] as $tag) {
                    foreach ($foldedNeedles as $needle) {
                        if (self::sameConcept($needle, $tag)) {
                            $hits++;
                            break;
                        }
                    }
                }
                if ($hits === 0) {
                    continue;
                }
                $score += $hits;
                $states[] = [
                    'id' => $state['id'],
                    'description' => $state['description'],
                ];
            }
            if ($score === 0) {
                continue;
            }
            $ranked[] = [
                'intent_id' => $intent['intent_id'],
                'score' => $score,
                'states' => $states,
            ];
        }

        if ($ranked === []) {
            return [];
        }

        usort($ranked, static function (array $a, array $b): int {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }

            return strcmp($a['intent_id'], $b['intent_id']);
        });

        $best = $ranked[0]['score'];
        $top = [];
        foreach ($ranked as $row) {
            if ($row['score'] !== $best) {
                break;
            }
            $top[] = $row;
            if (count($top) >= 4) {
                break;
            }
        }

        return $top;
    }

    /**
     * @return list<array{intent_id: string, states: list<array{id: string, description: string, tags: list<string>}>}>
     */
    private static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }

        $out = [];
        $vocabulary = [];
        foreach (IntentSchemaPaths::discoverYamlFiles() as $path) {
            try {
                $doc = Yaml::parseFile($path);
            } catch (\Throwable $e) {
                continue;
            }
            if (!is_array($doc)) {
                continue;
            }
            $intentId = trim((string) ($doc['intent_id'] ?? ''));
            $states = $doc['states'] ?? null;
            if ($intentId === '' || !is_array($states)) {
                continue;
            }
            $parsed = [];
            foreach ($states as $stateId => $state) {
                if (!is_string($stateId) || !is_array($state)) {
                    continue;
                }
                $meta = $state['meta'] ?? null;
                $rawTags = is_array($meta) ? ($meta['tags'] ?? null) : null;
                if (!is_array($rawTags)) {
                    continue;
                }
                $tags = [];
                foreach ($rawTags as $tag) {
                    if (!is_string($tag)) {
                        continue;
                    }
                    $raw = trim($tag);
                    $folded = ChatChannelPolicy::fold($raw);
                    if ($folded === '' || in_array($folded, $tags, true)) {
                        continue;
                    }
                    $tags[] = $folded;
                    if (!in_array($raw, $vocabulary, true)) {
                        $vocabulary[] = $raw;
                    }
                }
                if ($tags === []) {
                    continue;
                }
                $description = trim((string) ($state['description'] ?? ''));
                $parsed[] = [
                    'id' => $stateId,
                    'description' => $description !== '' ? $description : $stateId,
                    'tags' => $tags,
                ];
            }
            if ($parsed !== []) {
                $out[] = [
                    'intent_id' => $intentId,
                    'states' => $parsed,
                ];
            }
        }

        sort($vocabulary);
        self::$vocabulary = $vocabulary;

        return self::$index = $out;
    }

    private static function sameConcept(string $needle, string $tag): bool
    {
        if ($needle === $tag) {
            return true;
        }

        return $needle . 's' === $tag
            || $tag . 's' === $needle
            || $needle . 'es' === $tag
            || $tag . 'es' === $needle;
    }
}
