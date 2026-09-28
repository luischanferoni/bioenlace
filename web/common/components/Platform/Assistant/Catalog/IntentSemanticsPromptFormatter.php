<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;

/**
 * Arma el recorrido de un flow para el prompt de la guía.
 *
 * La primera línea es el botón. La explanation del estado inicial es la pantalla
 * que se abre al presionarlo. Las opciones de `meta.guide_options` son de esa
 * pantalla, no del chat. Lo que sigue es una secuencia de pasos, no un menú.
 * Una rama que termina no continúa en otra pantalla.
 * No adjunta `objective` ni ids.
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
        $lines = ['- Botón: ' . $label];
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

        $initialState = $states[$initial];
        $opened = self::lowerFirst(self::explanation($initialState));
        if ($opened !== '') {
            $lines[] = '  Al presionarlo se abre una pantalla donde ' . $opened;
        }
        foreach (self::optionLines(self::options($initialState), '  ') as $optionLine) {
            $lines[] = $optionLine;
        }
        $rows = self::pickBranches(self::alwaysRows($initialState['always'] ?? null));
        $menuChoices = self::rowsAreMenuChoices($initialState, $rows);
        foreach ($rows as $row) {
            if ($menuChoices && self::optionLabelFromGuard($initialState, $row['guard']) === '') {
                continue;
            }
            foreach (self::branchLines($initialState, $row, $states) as $branchLine) {
                $lines[] = $branchLine;
            }
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
     * @param array<string, mixed> $sourceState
     * @param array{guard: string, target: string} $row
     * @param array<string, array<string, mixed>> $states
     * @return list<string>
     */
    private static function branchLines(array $sourceState, array $row, array $states): array
    {
        $chain = self::chainStates($states, $row['target']);
        $steps = $chain['states'];
        if ($steps === []) {
            return [];
        }

        $first = $steps[0];
        $option = self::optionLabelFromGuard($sourceState, $row['guard']);
        if ($option === '') {
            return self::orderedStepLines($steps, $chain['cut'], '  ', self::chainStops($steps, $chain['cut']));
        }

        $screen = self::explanation($first);
        $head = 'Si elige ' . $option . ', se abre otra pantalla';
        if ($screen !== '') {
            $head .= ': ' . $screen;
        } else {
            $head .= '.';
        }
        $rest = array_slice($steps, 1);
        $stops = self::isFinal($first) && $rest === [] && !$chain['cut'];
        if ($stops) {
            $head .= ' El recorrido se detiene.';
        }

        $lines = ['  ' . $head];
        foreach (self::optionLines(self::options($first), '    ') as $optionLine) {
            $lines[] = $optionLine;
        }
        foreach (self::orderedStepLines($rest, $chain['cut'], '    ', self::chainStops($steps, $chain['cut'])) as $stepLine) {
            $lines[] = $stepLine;
        }

        return $lines;
    }

    /**
     * Un guard que no es una opción de la pantalla es un paso del sistema, no una elección.
     *
     * @param array<string, mixed> $sourceState
     * @param list<array{guard: string, target: string}> $rows
     */
    private static function rowsAreMenuChoices(array $sourceState, array $rows): bool
    {
        foreach ($rows as $row) {
            if (self::optionLabelFromGuard($sourceState, $row['guard']) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $steps
     * @return list<string>
     */
    private static function orderedStepLines(array $steps, bool $cut, string $indent, bool $stops = false): array
    {
        $bits = [];
        foreach ($steps as $step) {
            $name = self::shortName($step);
            if ($name !== '') {
                $bits[] = $name;
            }
        }
        if ($cut) {
            $bits[] = '…';
        }
        if ($bits === []) {
            return [];
        }

        $line = $indent . 'Después, en este orden: ' . implode(', ', $bits) . '.';
        if ($stops) {
            $line .= ' Ahí el recorrido se detiene.';
        }

        return [$line];
    }

    /**
     * @param list<array<string, mixed>> $steps
     */
    private static function chainStops(array $steps, bool $cut): bool
    {
        if ($cut || $steps === []) {
            return false;
        }

        return self::isFinal($steps[count($steps) - 1]);
    }

    /**
     * @param array<string, array<string, mixed>> $states
     * @return array{states: list<array<string, mixed>>, cut: bool}
     */
    private static function chainStates(array $states, string $startId): array
    {
        $steps = [];
        $id = $startId;
        $seen = [];
        $cut = false;

        for ($n = 0; $n < self::MAX_CHAIN; $n++) {
            if ($id === '' || isset($seen[$id]) || !isset($states[$id]) || !is_array($states[$id])) {
                break;
            }
            $seen[$id] = true;
            $state = $states[$id];
            $steps[] = $state;
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

        return ['states' => $steps, 'cut' => $cut];
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
    private static function explanation(array $state): string
    {
        $text = trim((string) ($state['explanation'] ?? ''));
        if ($text === '') {
            $text = trim((string) ($state['description'] ?? ''));
        }
        if ($text === '') {
            $text = trim((string) ($state['label'] ?? ''));
        }

        return $text;
    }

    private static function lowerFirst(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        $first = function_exists('mb_substr') ? mb_substr($text, 0, 1, 'UTF-8') : substr($text, 0, 1);
        $rest = function_exists('mb_substr') ? mb_substr($text, 1, $len, 'UTF-8') : substr($text, 1);
        $lower = function_exists('mb_strtolower') ? mb_strtolower($first, 'UTF-8') : strtolower($first);

        return $lower . $rest;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function shortName(array $state): string
    {
        $text = trim((string) ($state['description'] ?? ''));
        if ($text === '') {
            $text = trim((string) ($state['label'] ?? ''));
        }
        if ($text === '') {
            return '';
        }
        $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        $first = function_exists('mb_substr') ? mb_substr($text, 0, 1, 'UTF-8') : substr($text, 0, 1);
        $rest = function_exists('mb_substr') ? mb_substr($text, 1, $len, 'UTF-8') : substr($text, 1);
        $lower = function_exists('mb_strtolower') ? mb_strtolower($first, 'UTF-8') : strtolower($first);

        return $lower . $rest;
    }

    /**
     * @param array<string, mixed> $state
     * @return list<string>
     */
    private static function options(array $state): array
    {
        $meta = isset($state['meta']) && is_array($state['meta']) ? $state['meta'] : [];
        $ref = trim((string) ($meta['guide_options'] ?? ''));
        if ($ref === '') {
            return [];
        }

        return GuideStepOptionCatalog::labels($ref);
    }

    /**
     * @param list<string> $labels
     * @return list<string>
     */
    private static function optionLines(array $labels, string $indent): array
    {
        if ($labels === []) {
            return [];
        }

        return [$indent . 'En esa pantalla: ' . implode(', ', $labels) . '.'];
    }

    /**
     * @param array<string, mixed> $sourceState
     */
    private static function optionLabelFromGuard(array $sourceState, string $guard): string
    {
        $guard = trim($guard);
        $meta = isset($sourceState['meta']) && is_array($sourceState['meta']) ? $sourceState['meta'] : [];
        $ref = trim((string) ($meta['guide_options'] ?? ''));
        if ($guard === '' || $ref === '') {
            return '';
        }

        foreach (explode(', ', $guard) as $part) {
            $eq = strpos($part, '=');
            if ($eq === false) {
                continue;
            }
            $label = GuideStepOptionCatalog::labelForCode($ref, trim(substr($part, $eq + 1)));
            if ($label !== '') {
                return $label;
            }
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
