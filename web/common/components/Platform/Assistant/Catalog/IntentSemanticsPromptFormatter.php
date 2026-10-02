<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;

/**
 * Arma el mapa de pantallas de un flow para el prompt de la guía.
 *
 * El botón de inicio abre la primera pantalla. Cada opción abre la siguiente.
 * Cada estado reachable se escribe una sola vez, con su explanation.
 * Si todas las opciones van a la misma pantalla, la transición es CUALQUIER_OPCION.
 * No adjunta ids técnicos.
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

        $flowToken = self::slug($button);
        if ($flowToken === '') {
            $flowToken = 'FLUJO';
        }

        $map = self::screenMap($manifest, $flowToken);
        $lines = ['FLUJO: ' . $flowToken];
        $opens = $map[0]['token'] ?? '';
        if (is_string($opens) && $opens !== '') {
            $lines[] = 'Botón "' . $button . '" abre ' . $opens;
        } else {
            $lines[] = 'Botón "' . $button . '"';
        }
        if ($map === []) {
            return implode("\n", $lines);
        }

        $lines[] = '';
        $chunks = [];
        foreach ($map as $screen) {
            $chunks[] = self::renderScreen($screen);
        }
        $lines[] = implode("\n\n", $chunks);

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed>|null $manifest
     * @return list<array<string, mixed>>
     */
    private static function screenMap(?array $manifest, string $flowToken): array
    {
        if ($manifest === null) {
            return [];
        }
        $states = $manifest['states'] ?? null;
        if (!is_array($states) || $states === []) {
            return [];
        }
        $initial = self::initialId($manifest, $states);
        if ($initial === '' || !isset($states[$initial]) || !is_array($states[$initial])) {
            return [];
        }

        $graph = self::walk($states, $initial);
        $tokens = self::assignTokens($graph['order'], $states, $initial, $flowToken);
        $outgoing = [];
        foreach ($graph['edges'] as $edge) {
            $from = $edge['from'];
            if (!isset($outgoing[$from])) {
                $outgoing[$from] = [];
            }
            $outgoing[$from][] = $edge;
        }

        $screens = [];
        foreach ($graph['order'] as $stateId) {
            if (!isset($states[$stateId]) || !is_array($states[$stateId])) {
                continue;
            }
            $state = $states[$stateId];
            $kind = self::screenKind($state);
            $transitions = [];
            foreach ($outgoing[$stateId] ?? [] as $edge) {
                $to = $edge['to'];
                if (!isset($tokens[$to])) {
                    continue;
                }
                $transitions[] = [
                    'option' => $edge['option'],
                    'to' => $tokens[$to],
                ];
            }
            $screens[] = [
                'token' => $tokens[$stateId],
                'kind' => $kind,
                'explanation' => self::explanation($state),
                'options' => self::options($state),
                'transitions' => $transitions,
                'actions' => self::actionLabels($state),
                'accion_final' => $kind === 'seleccion' && self::isFinal($state)
                    ? self::accionFinal($state)
                    : '',
                'fin' => $kind === 'informacion' || $kind === 'texto',
            ];
        }

        return $screens;
    }

    /**
     * @param array<string, array<string, mixed>> $states
     * @return array{order: list<string>, edges: list<array{from: string, option: ?string, to: string}>}
     */
    private static function walk(array $states, string $initial): array
    {
        $order = [];
        $edges = [];
        $seen = [];
        $queue = [$initial];

        while ($queue !== [] && count($order) < self::MAX_SCREENS) {
            $id = array_shift($queue);
            if (!is_string($id) || $id === '' || isset($seen[$id]) || !isset($states[$id]) || !is_array($states[$id])) {
                continue;
            }
            $seen[$id] = true;
            $order[] = $id;
            if (self::isFinal($states[$id])) {
                continue;
            }
            foreach (self::outgoing($states[$id], $states) as $edge) {
                $edges[] = [
                    'from' => $id,
                    'option' => $edge['option'],
                    'to' => $edge['to'],
                ];
                if (!isset($seen[$edge['to']])) {
                    $queue[] = $edge['to'];
                }
            }
        }

        return ['order' => $order, 'edges' => $edges];
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

    /**
     * @param list<string> $order
     * @param array<string, array<string, mixed>> $states
     * @return array<string, string>
     */
    private static function assignTokens(array $order, array $states, string $initial, string $flowToken): array
    {
        $short = [];
        $long = [];
        foreach ($order as $id) {
            $state = $states[$id];
            $source = self::tokenSource($state);
            $short[$id] = self::slug($source);
            $long[$id] = self::slug($source, true);
            if ($short[$id] === '') {
                $short[$id] = 'PANTALLA';
            }
            if ($long[$id] === '') {
                $long[$id] = $short[$id];
            }
        }

        $groups = [];
        foreach ($order as $id) {
            if ($id === $initial) {
                continue;
            }
            $groups[$short[$id]][] = $id;
        }

        $tokens = [];
        $used = [];
        $entryIsMenu = self::options($states[$initial]) !== [];
        $tokens[$initial] = self::claim($entryIsMenu ? $flowToken : $short[$initial], $used);
        foreach ($order as $id) {
            if ($id === $initial) {
                continue;
            }
            $group = $groups[$short[$id]] ?? [$id];
            $keepShort = count($group) === 1;
            if (!$keepShort) {
                $plain = [];
                foreach ($group as $other) {
                    if (!str_contains(self::tokenSource($states[$other]), '(')) {
                        $plain[] = $other;
                    }
                }
                $keepShort = $plain === [$id];
            }
            $candidate = $keepShort ? $short[$id] : $long[$id];
            $tokens[$id] = self::claim($candidate, $used);
        }

        return $tokens;
    }

    /**
     * @param array<string, true> $used
     */
    private static function claim(string $token, array &$used): string
    {
        $base = $token !== '' ? $token : 'PANTALLA';
        $candidate = $base;
        $n = 2;
        while (isset($used[$candidate])) {
            $candidate = $base . '_' . $n;
            $n++;
        }
        $used[$candidate] = true;

        return $candidate;
    }

    /**
     * @param array<string, mixed> $screen
     */
    private static function renderScreen(array $screen): string
    {
        $lines = ['[PANTALLA: ' . $screen['token'] . ']'];
        $lines[] = '  Tipo: ' . self::kindLabel((string) $screen['kind']);
        $explanation = trim((string) ($screen['explanation'] ?? ''));
        if ($explanation !== '') {
            $lines[] = '  ' . $explanation;
        }

        $options = $screen['options'];
        if (is_array($options) && $options !== []) {
            $lines[] = '  Opciones:';
            foreach ($options as $option) {
                if (is_string($option) && $option !== '') {
                    $lines[] = '    - ' . $option;
                }
            }
        }

        $transitions = $screen['transitions'];
        if (is_array($transitions) && $transitions !== []) {
            $rows = [];
            foreach ($transitions as $transition) {
                if (!is_array($transition)) {
                    continue;
                }
                $option = $transition['option'];
                $from = is_string($option) && $option !== '' ? $option : 'CUALQUIER_OPCION';
                $rows[] = [$from, (string) $transition['to']];
            }
            if ($rows !== []) {
                $width = 0;
                foreach ($rows as $row) {
                    $width = max($width, mb_strlen($row[0]));
                }
                $lines[] = '  Al elegir:';
                foreach ($rows as $row) {
                    $pad = str_repeat(' ', max(0, $width - mb_strlen($row[0])));
                    $lines[] = '    ' . $row[0] . $pad . ' abre ' . $row[1];
                }
            }
        }

        $actions = $screen['actions'];
        if (is_array($actions) && $actions !== []) {
            $lines[] = '  Acciones:';
            foreach ($actions as $action) {
                if (is_string($action) && $action !== '') {
                    $lines[] = '    - ' . $action;
                }
            }
        }

        $accion = trim((string) $screen['accion_final']);
        if ($accion !== '') {
            $lines[] = '  ACCION_FINAL: ' . $accion;
        }
        if (!empty($screen['fin'])) {
            $lines[] = '  FIN_DEL_FLUJO: Sí';
        }

        return implode("\n", $lines);
    }

    private static function kindLabel(string $kind): string
    {
        if ($kind === 'informacion') {
            return 'Información';
        }
        if ($kind === 'texto') {
            return 'Texto';
        }

        return 'Selección de opción';
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function screenKind(array $state): string
    {
        $meta = isset($state['meta']) && is_array($state['meta']) ? $state['meta'] : [];
        if (isset($meta['composer_capture']) && is_array($meta['composer_capture'])) {
            return 'texto';
        }
        if (!empty($meta['terminal_without_submit'])) {
            return 'informacion';
        }

        return 'seleccion';
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function accionFinal(array $state): string
    {
        $meta = isset($state['meta']) && is_array($state['meta']) ? $state['meta'] : [];
        $submit = $meta['flow_submit'] ?? null;
        if (is_array($submit)) {
            $label = trim((string) ($submit['label'] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }
        $outcome = self::outcomeText($state);
        if ($outcome !== '' && stripos($outcome, 'reserva un turno') !== false) {
            return 'Confirmar turno';
        }
        $clause = self::objectiveClause($state);
        if ($clause !== '') {
            return $clause;
        }

        return 'Confirmar';
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function tokenSource(array $state): string
    {
        if (self::screenKind($state) === 'informacion') {
            $explanation = self::explanation($state);
            if ($explanation !== '') {
                return $explanation;
            }
        }
        $label = trim((string) ($state['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        return self::explanation($state);
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

    private static function stripParen(string $text): string
    {
        $stripped = preg_replace('/\s*\([^)]*\)/', '', $text);

        return trim(is_string($stripped) ? $stripped : $text);
    }

    private static function slug(string $text, bool $keepParen = false): string
    {
        if ($keepParen) {
            $text = str_replace(['(', ')'], ' ', $text);
        } else {
            $text = self::stripParen($text);
        }
        $text = strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        ]);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
        if (!is_string($text)) {
            return '';
        }
        $stop = [
            'de' => true, 'del' => true, 'la' => true, 'el' => true, 'los' => true, 'las' => true,
            'un' => true, 'una' => true, 'y' => true, 'o' => true, 'en' => true, 'al' => true,
            'con' => true, 'para' => true, 'por' => true, 'que' => true, 'es' => true,
        ];
        $parts = [];
        foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
            if ($word === '' || isset($stop[$word])) {
                continue;
            }
            $parts[] = $word;
        }

        return strtoupper(implode('_', $parts));
    }
}
