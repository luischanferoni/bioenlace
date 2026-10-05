<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;

/**
 * Arma el recorrido de un flow como árbol, para el prompt de la guía.
 *
 * Cada sección es Sección "explanation". Cada elección es Opción "…".
 * Para quién es el camino está en la hoja (fin · …). No adjunta ids ni labels de pantalla.
 */
final class IntentSemanticsPromptFormatter
{
    private const MAX_SCREENS = 24;

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
            $block = self::renderIntent($intentId);
            if ($block !== '') {
                $blocks[] = $block;
            }
            if (count($blocks) >= max(1, $maxIntents)) {
                break;
            }
        }

        return implode("\n\n", $blocks);
    }

    /**
     * Intents cuyo tag cruzó. El bloque es el mapa del flow, no la lista de estados que matchearon.
     *
     * @param list<array{intent_id: string, score: int, states: list<array{id: string, description: string}>}> $hits
     */
    public static function formatStateHits(array $hits, int $maxIntents = 4): string
    {
        $ids = [];
        foreach ($hits as $hit) {
            if (!is_array($hit)) {
                continue;
            }
            $intentId = trim((string) ($hit['intent_id'] ?? ''));
            $states = $hit['states'] ?? [];
            if ($intentId === '' || !is_array($states) || $states === []) {
                continue;
            }
            $ids[] = $intentId;
        }

        return self::formatForIntentIds($ids, $maxIntents);
    }

    public static function formatCatalogItem(UiActionCatalogItem $item): string
    {
        return self::formatIntentId($item->action_id, $item);
    }

    public static function formatIntentId(string $intentId, ?UiActionCatalogItem $item = null, int $root = 1): string
    {
        unset($root);

        return self::renderIntent($intentId, $item);
    }

    /**
     * @param array<string, array<string, mixed>> $states
     */
    private static function renderIntent(string $intentId, ?UiActionCatalogItem $item = null): string
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return '';
        }
        $manifest = YamlIntentManifestLoader::load($intentId);
        $button = self::label($manifest, $item, $intentId);
        if ($button === '') {
            return '';
        }
        $heading = 'Botón "' . $button . '"';
        if ($manifest === null) {
            return $heading;
        }
        $states = $manifest['states'] ?? null;
        if (!is_array($states) || $states === []) {
            return $heading;
        }
        $initial = self::initialId($manifest, $states);
        if ($initial === '' || !isset($states[$initial]) || !is_array($states[$initial])) {
            return $heading;
        }

        $root = self::sectionNode($initial, $states, [], 0);
        if ($root === null) {
            return $heading;
        }

        return $heading . "\n" . implode("\n", self::drawNode($root, '', true));
    }

    /**
     * @param array<string, array<string, mixed>> $states
     * @param array<string, true> $seen
     * @return array{title: string, children: list<array{title: string, children: list<array<string, mixed>>}>}|null
     */
    private static function sectionNode(string $id, array $states, array $seen, int $depth): ?array
    {
        if ($depth > self::MAX_SCREENS || isset($seen[$id]) || !isset($states[$id]) || !is_array($states[$id])) {
            return null;
        }
        $seen[$id] = true;
        $state = $states[$id];
        $title = self::quoted('Sección', self::period(self::explanation($state)));
        if ($title === 'Sección ""') {
            return null;
        }

        $children = [];
        $submit = self::submitLabel($state);
        if ($submit !== '' && !self::isFinal($state)) {
            $children[] = [
                'title' => self::quoted('Opción', $submit),
                'children' => [self::finNode($state)],
            ];
        }
        foreach (self::actionLabels($state) as $label) {
            $children[] = ['title' => self::quoted('Opción', $label), 'children' => []];
        }

        if (self::isFinal($state)) {
            $children[] = self::finNode($state);

            return ['title' => $title, 'children' => $children];
        }

        $edges = self::continuations($id, $states);
        $named = [];
        foreach ($edges as $edge) {
            if (is_string($edge['option']) && $edge['option'] !== '') {
                $named[] = $edge;
            }
        }
        if (count($named) > 1) {
            foreach ($named as $edge) {
                $next = self::sectionNode($edge['to'], $states, $seen, $depth + 1);
                $children[] = [
                    'title' => self::quoted('Opción', $edge['option']),
                    'children' => $next === null ? [] : [$next],
                ];
            }

            return ['title' => $title, 'children' => $children];
        }

        $options = self::options($state);
        if ($options !== [] && count($edges) === 1) {
            $next = self::sectionNode((string) $edges[0]['to'], $states, $seen, $depth + 1);
            $last = count($options) - 1;
            foreach ($options as $index => $option) {
                $optionChildren = [];
                if ($index === $last && $next !== null) {
                    $optionChildren[] = $next;
                }
                $children[] = ['title' => self::quoted('Opción', $option), 'children' => $optionChildren];
            }
            if ($children === [] && $next !== null) {
                $children[] = $next;
            }

            return ['title' => $title, 'children' => $children];
        }

        if (count($edges) > 1) {
            foreach ($edges as $edge) {
                $next = self::sectionNode($edge['to'], $states, $seen, $depth + 1);
                if ($next !== null) {
                    $children[] = $next;
                }
            }

            return ['title' => $title, 'children' => $children];
        }

        if ($edges === []) {
            $children[] = self::finNode($state);

            return ['title' => $title, 'children' => $children];
        }

        $next = self::sectionNode((string) $edges[0]['to'], $states, $seen, $depth + 1);
        if ($next !== null) {
            $children[] = $next;
        }

        return ['title' => $title, 'children' => $children];
    }

    private static function quoted(string $kind, string $text): string
    {
        return $kind . ' "' . trim($text) . '"';
    }

    /**
     * @param array<string, mixed> $state
     * @return array{title: string, children: list<empty>}
     */
    private static function finNode(array $state): array
    {
        return [
            'title' => 'fin · ' . self::paraDesdeOutcome(self::outcomeText($state)),
            'children' => [],
        ];
    }

    /**
     * @param array{title: string, children: list<array<string, mixed>>} $node
     * @return list<string>
     */
    private static function drawNode(array $node, string $prefix, bool $last): array
    {
        $lines = [$prefix . ($last ? '└─ ' : '├─ ') . $node['title']];
        $childPrefix = $prefix . ($last ? '   ' : '│  ');
        $children = $node['children'];
        $count = count($children);
        foreach ($children as $index => $child) {
            if (!is_array($child) || !isset($child['title'])) {
                continue;
            }
            foreach (self::drawNode($child, $childPrefix, $index === $count - 1) as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private static function paraDesdeOutcome(string $outcome): string
    {
        $folded = self::fold($outcome);
        if (strpos($folded, 'no esta a cargo') !== false) {
            return 'la persona que escribe o también otra persona';
        }

        return 'solo la persona que escribe';
    }

    private static function fold(string $text): string
    {
        $text = mb_strtolower($text);

        return strtr($text, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, array<string, mixed>> $states
     * @return list<array{option: ?string, to: string}>
     */
    private static function outgoing(array $state, array $states): array
    {
        $rows = self::alwaysRows($state['always'] ?? null);
        $named = self::namedOptionTargets($state, $rows);
        if ($named !== []) {
            $unique = array_values(array_unique($named));
            if (count($unique) === 1) {
                $to = $unique[0];
                if (isset($states[$to])) {
                    return [['option' => null, 'to' => $to]];
                }

                return [];
            }
            $out = [];
            foreach ($named as $label => $to) {
                if (!isset($states[$to])) {
                    continue;
                }
                $out[] = ['option' => $label, 'to' => $to];
            }

            return $out;
        }

        $to = self::defaultTarget($rows);
        if ($to === '' || !isset($states[$to])) {
            return [];
        }

        return [['option' => null, 'to' => $to]];
    }

    /**
     * @var array<string, true>
     */
    private static $resolvingContinuation = [];

    /**
     * @param array<string, array<string, mixed>> $states
     * @return list<array{option: ?string, to: string}>
     */
    private static function continuations(string $id, array $states): array
    {
        if ($id === '' || isset(self::$resolvingContinuation[$id]) || !isset($states[$id]) || !is_array($states[$id])) {
            return [];
        }
        self::$resolvingContinuation[$id] = true;
        $edges = self::continuationEdges($states[$id], $states);
        unset(self::$resolvingContinuation[$id]);

        return $edges;
    }

    /**
     * Si el paso declara opciones de catálogo, sigue esas. Si no, y hay varios destinos, cada uno es una rama.
     * Un destino que solo adelanta pantallas del camino sin guardia no abre otra rama.
     *
     * @param array<string, mixed> $state
     * @param array<string, array<string, mixed>> $states
     * @return list<array{option: ?string, to: string}>
     */
    private static function continuationEdges(array $state, array $states): array
    {
        $catalog = self::outgoing($state, $states);
        if (self::options($state) !== []) {
            return $catalog;
        }
        $targets = [];
        foreach (self::alwaysRows($state['always'] ?? null) as $row) {
            $to = $row['target'];
            if ($to === '' || !isset($states[$to]) || in_array($to, $targets, true)) {
                continue;
            }
            $targets[] = $to;
        }
        if (count($targets) <= 1) {
            return $catalog;
        }
        $unguarded = self::unguardedTarget(self::alwaysRows($state['always'] ?? null));
        if ($unguarded !== '' && isset($states[$unguarded])) {
            $reachable = self::reachableFrom($unguarded, $states);
            $forks = [$unguarded];
            foreach ($targets as $to) {
                if ($to !== $unguarded && !isset($reachable[$to])) {
                    $forks[] = $to;
                }
            }
            $targets = $forks;
        }
        if (count($targets) === 1) {
            return [['option' => null, 'to' => $targets[0]]];
        }
        $out = [];
        foreach ($targets as $to) {
            $out[] = ['option' => null, 'to' => $to];
        }

        return $out;
    }

    /**
     * @param array<string, array<string, mixed>> $states
     * @param array<string, true> $seen
     * @return array<string, true>
     */
    private static function reachableFrom(string $id, array $states, array $seen = []): array
    {
        if (isset($seen[$id]) || !isset($states[$id]) || !is_array($states[$id])) {
            return $seen;
        }
        $seen[$id] = true;
        if (self::isFinal($states[$id])) {
            return $seen;
        }
        foreach (self::continuations($id, $states) as $edge) {
            $seen = self::reachableFrom($edge['to'], $states, $seen);
        }

        return $seen;
    }

    /**
     * Opción de catálogo → pantalla siguiente. Si el catálogo no tiene fila propia, usa el destino sin guard.
     *
     * @param array<string, mixed> $state
     * @param list<array{guard: string, target: string}> $rows
     * @return array<string, string>
     */
    private static function namedOptionTargets(array $state, array $rows): array
    {
        $labels = self::options($state);
        if ($labels === []) {
            return [];
        }

        $best = [];
        foreach ($rows as $row) {
            $label = self::optionLabelFromGuard($state, $row['guard']);
            if ($label === '') {
                continue;
            }
            $parts = $row['guard'] === '' ? 0 : substr_count($row['guard'], ',') + 1;
            if (!isset($best[$label]) || $parts < $best[$label]['parts']) {
                $best[$label] = ['target' => $row['target'], 'parts' => $parts];
            }
        }

        $fallback = self::unguardedTarget($rows);
        $out = [];
        foreach ($labels as $label) {
            if (isset($best[$label]) && $best[$label]['target'] !== '') {
                $out[$label] = $best[$label]['target'];
                continue;
            }
            if ($fallback !== '') {
                $out[$label] = $fallback;
            }
        }

        return $out;
    }

    private static function period(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if (str_ends_with($text, '.')) {
            return $text;
        }

        return $text . '.';
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
     * @param array<string, mixed> $state
     */
    private static function outcomeText(array $state): string
    {
        return trim((string) ($state['outcome'] ?? ''));
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
     * @param list<array{guard: string, target: string}> $rows
     */
    private static function unguardedTarget(array $rows): string
    {
        foreach ($rows as $row) {
            if ($row['guard'] === '' && $row['target'] !== '') {
                return $row['target'];
            }
        }

        return '';
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
    private static function submitLabel(array $state): string
    {
        $meta = isset($state['meta']) && is_array($state['meta']) ? $state['meta'] : [];
        $submit = $meta['flow_submit'] ?? null;
        if (!is_array($submit)) {
            return '';
        }

        return trim((string) ($submit['label'] ?? ''));
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
