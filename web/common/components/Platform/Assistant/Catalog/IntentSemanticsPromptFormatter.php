<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;

/**
 * Arma el recorrido de un flow para el prompt de la guía.
 *
 * Parte del estado inicial y sigue `always` por las descripciones hasta un
 * cierre. Cada paso indica si es un campo de texto o una lista de opciones.
 * No adjunta `objective` ni guards.
 */
final class IntentSemanticsPromptFormatter
{
    private const MAX_CHAIN = 8;

    private const MAX_BRANCHES = 6;

    /**
     * @param list<string> $intentIds
     */
    public static function formatForIntentIds(array $intentIds, int $maxIntents = 4): string
    {
        $blocks = [];
        $seen = [];
        foreach ($intentIds as $intentId) {
            if (!is_string($intentId)) {
                continue;
            }
            $intentId = trim($intentId);
            if ($intentId === '' || isset($seen[$intentId])) {
                continue;
            }
            $seen[$intentId] = true;
            $block = self::formatIntentId($intentId);
            if ($block !== '') {
                $blocks[] = $block;
            }
            if (count($blocks) >= max(1, $maxIntents)) {
                break;
            }
        }

        if ($blocks === []) {
            return '';
        }

        return implode("\n\n", $blocks);
    }

    /**
     * Intents cuyo tag cruzó. El bloque es el recorrido del flow, no la lista de estados que matchearon.
     *
     * @param list<array{intent_id: string, score: int, states: list<array{id: string, description: string}>}> $hits
     */
    public static function formatStateHits(array $hits, int $maxIntents = 4): string
    {
        $blocks = [];
        foreach ($hits as $hit) {
            if (!is_array($hit)) {
                continue;
            }
            $intentId = trim((string) ($hit['intent_id'] ?? ''));
            $states = $hit['states'] ?? [];
            if ($intentId === '' || !is_array($states) || $states === []) {
                continue;
            }
            $manifest = YamlIntentManifestLoader::load($intentId);
            $block = self::formatManifest($manifest, self::label($manifest, null, $intentId));
            if ($block === '') {
                continue;
            }
            $blocks[] = $block;
            if (count($blocks) >= max(1, $maxIntents)) {
                break;
            }
        }

        return implode("\n\n", $blocks);
    }

    public static function formatCatalogItem(UiActionCatalogItem $item): string
    {
        return self::formatIntentId($item->action_id, $item);
    }

    public static function formatIntentId(string $intentId, ?UiActionCatalogItem $item = null): string
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return '';
        }

        $manifest = YamlIntentManifestLoader::load($intentId);

        return self::formatManifest($manifest, self::label($manifest, $item, $intentId));
    }

    /**
     * @param array<string, mixed>|null $manifest
     */
    private static function formatManifest(?array $manifest, string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }

        return implode("\n", self::recorridoLines($manifest, $label));
    }

    /**
     * @param array<string, mixed>|null $manifest
     */
    private static function label(?array $manifest, ?UiActionCatalogItem $item, string $intentId): string
    {
        if ($item !== null && $item->display_name !== '') {
            return $item->display_name;
        }
        if ($manifest !== null) {
            $name = trim((string) ($manifest['action_name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return $intentId;
    }

    /**
     * @param array<string, mixed>|null $manifest
     * @return list<string>
     */
    private static function recorridoLines(?array $manifest, string $label): array
    {
        $lines = ['- ' . $label];
        if ($manifest === null) {
            return $lines;
        }
        $states = $manifest['states'] ?? null;
        if (!is_array($states) || $states === []) {
            return $lines;
        }
        $initial = self::initialId($manifest, $states);
        if ($initial === '' || !isset($states[$initial]) || !is_array($states[$initial])) {
            return $lines;
        }

        $lines[] = '  ' . self::stateLabel($initial, $states[$initial]);
        $rows = self::pickBranches(self::alwaysRows($states[$initial]['always'] ?? null));
        foreach ($rows as $row) {
            $chain = self::chainFrom($states, $row['target']);
            if ($chain['labels'] === []) {
                continue;
            }
            $text = '    → ' . implode(' → ', $chain['labels']);
            if ($chain['cut']) {
                $text .= ' → …';
            }
            $lines[] = $text;
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<string, mixed> $states
     */
    private static function initialId(array $manifest, array $states): string
    {
        $initial = trim((string) ($manifest['initial'] ?? ''));
        if ($initial !== '' && isset($states[$initial]) && is_array($states[$initial])) {
            return $initial;
        }
        foreach ($states as $id => $state) {
            if (is_string($id) && is_array($state)) {
                return $id;
            }
        }

        return '';
    }

    /**
     * @param array<string, array<string, mixed>> $states
     * @return array{labels: list<string>, cut: bool}
     */
    private static function chainFrom(array $states, string $startId): array
    {
        $labels = [];
        $id = $startId;
        $seen = [];
        $cut = false;

        for ($n = 0; $n < self::MAX_CHAIN; $n++) {
            if ($id === '' || isset($seen[$id]) || !isset($states[$id]) || !is_array($states[$id])) {
                break;
            }
            $seen[$id] = true;
            $state = $states[$id];
            $labels[] = self::stateLabel($id, $state);
            if (self::isFinal($state)) {
                break;
            }
            $next = self::defaultTarget(self::alwaysRows($state['always'] ?? null));
            if ($next === '' || $next === $id) {
                break;
            }
            if ($n === self::MAX_CHAIN - 1) {
                $cut = true;
                break;
            }
            $id = $next;
        }

        return ['labels' => $labels, 'cut' => $cut];
    }

    /**
     * @param list<array{guard: string, target: string}> $rows
     * @return list<array{guard: string, target: string}>
     */
    private static function pickBranches(array $rows): array
    {
        if (count($rows) <= self::MAX_BRANCHES) {
            return $rows;
        }

        $defaultAt = count($rows) - 1;
        foreach ($rows as $i => $row) {
            if ($row['guard'] === '' && $row['target'] !== '') {
                $defaultAt = $i;
                break;
            }
        }

        $out = [];
        foreach ($rows as $i => $row) {
            if ($i === $defaultAt) {
                continue;
            }
            if (count($out) >= self::MAX_BRANCHES - 1) {
                break;
            }
            $out[] = $row;
        }
        $out[] = $rows[$defaultAt];

        return $out;
    }

    /**
     * @param list<array{guard: string, target: string}> $rows
     */
    private static function defaultTarget(array $rows): string
    {
        $fallback = '';
        foreach ($rows as $row) {
            if ($row['target'] !== '') {
                $fallback = $row['target'];
            }
            if ($row['guard'] === '' && $row['target'] !== '') {
                return $row['target'];
            }
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function inputKind(array $state): string
    {
        $meta = isset($state['meta']) && is_array($state['meta']) ? $state['meta'] : [];
        if (isset($meta['composer_capture'])) {
            return 'campo de texto';
        }
        if (isset($meta['open_ui']) || isset($meta['chooser'])) {
            return 'opciones';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function isFinal(array $state): bool
    {
        if (trim((string) ($state['type'] ?? '')) === 'final') {
            return true;
        }
        $always = $state['always'] ?? null;

        return $always === null || $always === [] || $always === '';
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function stateLabel(string $id, array $state): string
    {
        $description = trim((string) ($state['description'] ?? ''));
        if ($description === '') {
            $description = trim((string) ($state['label'] ?? ''));
        }
        if ($description === '') {
            $description = $id;
        }
        $kind = self::inputKind($state);
        if ($kind !== '') {
            $description .= ' (' . $kind . ')';
        }

        return $description;
    }

    /**
     * @param mixed $always
     * @return list<array{guard: string, target: string}>
     */
    private static function alwaysRows($always): array
    {
        if (is_string($always)) {
            $target = trim($always);

            return $target === '' ? [] : [['guard' => '', 'target' => $target]];
        }
        if (!is_array($always)) {
            return [];
        }
        if (array_key_exists('target', $always) || array_key_exists('guard', $always)) {
            $always = [$always];
        }
        $rows = [];
        foreach ($always as $row) {
            if (!is_array($row)) {
                continue;
            }
            $target = array_key_exists('target', $row) ? trim((string) $row['target']) : '';
            if ($target === '') {
                continue;
            }
            $guard = isset($row['guard']) && is_array($row['guard']) ? $row['guard'] : [];
            $parts = [];
            foreach ($guard as $field => $value) {
                $parts[] = trim((string) $field) . '=' . trim((string) $value);
            }
            $rows[] = [
                'guard' => implode(', ', $parts),
                'target' => $target,
            ];
        }

        return $rows;
    }
}
