<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;

/**
 * Arma el recorrido de un flow en formato HTA para el prompt de la guía.
 *
 * Cada botón del chat es una tarea raíz (`1`, `2`, …). Sus subtareas (`1.n`, `2.n`)
 * existen solo después de presionar ese botón.
 * Si un paso declara `meta.guide_options`, lista esas opciones.
 * Si la opción frena la reserva, resume la ayuda sin pegar el texto al usuario.
 * Si el paso declara `flow_actions`, lista lo que ofrece.
 * No adjunta ids técnicos.
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
        $root = 0;
        foreach ($intentIds as $intentId) {
            if (!is_string($intentId)) {
                continue;
            }
            $intentId = trim($intentId);
            if ($intentId === '' || isset($seen[$intentId])) {
                continue;
            }
            $seen[$intentId] = true;
            $root++;
            $block = self::formatIntentId($intentId, null, $root);
            if ($block !== '') {
                $blocks[] = $block;
            } else {
                $root--;
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
        $root = 0;
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
            $root++;
            $block = self::formatManifest($manifest, self::label($manifest, null, $intentId), $root);
            if ($block === '') {
                $root--;
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

    public static function formatIntentId(string $intentId, ?UiActionCatalogItem $item = null, int $root = 1): string
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return '';
        }

        $manifest = YamlIntentManifestLoader::load($intentId);

        return self::formatManifest($manifest, self::label($manifest, $item, $intentId), $root);
    }

    /**
     * @param array<string, mixed>|null $manifest
     */
    private static function formatManifest(?array $manifest, string $label, int $root = 1): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }
        $root = max(1, $root);

        return implode("\n", self::recorridoLines($manifest, $label, $root));
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
    private static function recorridoLines(?array $manifest, string $label, int $root): array
    {
        $rootId = (string) $root;
        $lines = [$rootId . '. Botón del chat: ' . $label];
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
        $child = 0;
        $intro = self::explanation($initialState);
        if ($intro !== '') {
            $child++;
            $lines[] = $rootId . '.' . $child . ' Al presionarlo: ' . $intro;
        }
        $rows = self::pickBranches(self::alwaysRows($initialState['always'] ?? null));
        $menuChoices = self::rowsAreMenuChoices($initialState, $rows);
        foreach ($rows as $row) {
            if ($menuChoices && self::optionLabelFromGuard($initialState, $row['guard']) === '') {
                continue;
            }
            $child++;
            $nodeId = $rootId . '.' . $child;
            $case = self::branchLines($initialState, $row, $states, $nodeId);
            if ($case === []) {
                $child--;
                continue;
            }
            $lines[] = '';
            foreach ($case as $caseLine) {
                $lines[] = $caseLine;
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
    private static function branchLines(array $sourceState, array $row, array $states, string $nodeId): array
    {
        $chain = self::chainStates($states, $row['target']);
        $steps = $chain['states'];
        if ($steps === []) {
            return [];
        }

        $option = self::optionLabelFromGuard($sourceState, $row['guard']);
        $help = self::helpFromGuard($sourceState, $row['guard']);
        $last = $steps[count($steps) - 1];
        $stops = self::chainStops($steps, $chain['cut']);
        $goal = self::objectiveClause($last);

        if ($option !== '') {
            return self::htaNamedPath($nodeId, $option, $goal, $steps, $help, $chain['cut'], $stops, $last);
        }

        return self::htaFlatPath($nodeId, $goal, $steps, $help, $chain['cut'], $stops, $last);
    }

    /**
     * @param list<array<string, mixed>> $steps
     * @param array<string, mixed> $last
     * @return list<string>
     */
    private static function htaNamedPath(
        string $nodeId,
        string $option,
        string $goal,
        array $steps,
        string $help,
        bool $cut,
        bool $stops,
        array $last
    ): array {
        $lines = [$nodeId . ' ' . $option];
        $sub = 0;
        $add = static function (string $text) use (&$lines, &$sub, $nodeId): string {
            $sub++;
            $id = $nodeId . '.' . $sub;
            $lines[] = $id . ' ' . $text;

            return $id;
        };
        if ($goal !== '') {
            $add('Objetivo: ' . $goal . '.');
        }
        self::htaAppendSteps($add, $lines, $steps, $help, $cut);
        if ($stops && self::outcomeText($last) !== '') {
            $add('Éxito: ' . self::closingSentence($last));
        }

        return $lines;
    }

    /**
     * Camino único (sin menú): hermanos bajo el botón, a partir de `$nodeId`.
     *
     * @param list<array<string, mixed>> $steps
     * @param array<string, mixed> $last
     * @return list<string>
     */
    private static function htaFlatPath(
        string $nodeId,
        string $goal,
        array $steps,
        string $help,
        bool $cut,
        bool $stops,
        array $last
    ): array {
        $parts = explode('.', $nodeId);
        $seq = (int) array_pop($parts) - 1;
        $parent = implode('.', $parts);
        $lines = [];
        $add = static function (string $text) use (&$lines, &$seq, $parent): string {
            $seq++;
            $id = $parent === '' ? (string) $seq : $parent . '.' . $seq;
            $lines[] = $id . ' ' . $text;

            return $id;
        };
        if ($goal !== '') {
            $add('Objetivo: ' . $goal . '.');
        }
        self::htaAppendSteps($add, $lines, $steps, $help, $cut);
        if ($stops && self::outcomeText($last) !== '') {
            $add('Éxito: ' . self::closingSentence($last));
        }

        return $lines;
    }

    /**
     * @param callable(string): string $add
     * @param list<string> $lines
     * @param list<array<string, mixed>> $steps
     */
    private static function htaAppendSteps(callable $add, array &$lines, array $steps, string $help, bool $cut): void
    {
        $lastIndex = count($steps) - 1;
        foreach ($steps as $index => $step) {
            $text = self::explanation($step);
            if ($help !== '' && $index === 0 && $lastIndex === 0) {
                $text = 'El sistema muestra orientación por urgencia y frena la reserva en la app.';
            }
            if ($text === '') {
                continue;
            }
            $stepId = $add($text);
            $nest = 0;
            $labels = self::options($step);
            if ($labels !== []) {
                $nest++;
                $lines[] = $stepId . '.' . $nest . ' Opciones: ' . implode(', ', $labels) . '.';
            }
            $offers = self::actionLabels($step);
            if ($offers !== []) {
                $nest++;
                $lines[] = $stepId . '.' . $nest . ' Ofrece: ' . implode(', ', $offers) . '.';
            }
        }
        if ($cut) {
            $add('…');
        }
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
     */
    private static function chainStops(array $steps, bool $cut): bool
    {
        if ($cut || $steps === []) {
            return false;
        }

        return self::isFinal($steps[count($steps) - 1]);
    }

    /**
     * Frase de resultado del estado que cierra el camino (`outcome` en el YAML).
     *
     * @param array<string, mixed> $state
     */
    private static function closingSentence(array $state): string
    {
        $text = trim((string) ($state['outcome'] ?? ''));
        if ($text === '') {
            return 'Ahí el recorrido se detiene.';
        }

        return rtrim($text, '.') . '.';
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function outcomeText(array $state): string
    {
        return trim((string) ($state['outcome'] ?? ''));
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function objectiveClause(array $state): string
    {
        $text = self::outcomeText($state);
        $prefix = 'Termina cuando ';
        if ($text !== '' && strncasecmp($text, $prefix, strlen($prefix)) === 0) {
            $text = substr($text, strlen($prefix));
        }

        return rtrim($text, '.');
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
     * @param array<string, mixed> $sourceState
     */
    private static function helpFromGuard(array $sourceState, string $guard): string
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
            $help = GuideStepOptionCatalog::helpForCode($ref, trim(substr($part, $eq + 1)));
            if ($help !== '') {
                return $help;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $state
     * @return list<string>
     */
    private static function actionLabels(array $state): array
    {
        $meta = isset($state['meta']) && is_array($state['meta']) ? $state['meta'] : [];
        $actions = $meta['flow_actions'] ?? null;
        if (!is_array($actions)) {
            return [];
        }
        $labels = [];
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $label = trim((string) ($action['label'] ?? ''));
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
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
