<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;

/**
 * Arma el recorrido de un flow como wireflow, para el prompt de la guía.
 *
 * Cada opción del primer paso es un camino. La línea Para sale del outcome
 * con el que cierra ese camino. No adjunta ids ni nombres de pantalla.
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
        if ($manifest === null) {
            return 'Botón "' . $button . '"';
        }
        $states = $manifest['states'] ?? null;
        if (!is_array($states) || $states === []) {
            return 'Botón "' . $button . '"';
        }
        $initial = self::initialId($manifest, $states);
        if ($initial === '' || !isset($states[$initial]) || !is_array($states[$initial])) {
            return 'Botón "' . $button . '"';
        }

        $lines = ['Botón "' . $button . '"'];
        $named = [];
        foreach (self::continuations($initial, $states) as $edge) {
            if (is_string($edge['option']) && $edge['option'] !== '') {
                $named[] = $edge;
            }
        }
        if (count($named) > 1) {
            $intro = self::period(self::explanation($states[$initial]));
            if ($intro !== '') {
                $lines[] = $intro;
            }
            foreach ($named as $edge) {
                $lines[] = '';
                $lines[] = self::renderCamino($edge['option'], $edge['to'], $states);
            }

            return implode("\n", $lines);
        }

        $lines[] = '';
        $lines[] = self::renderCamino(null, $initial, $states);

        return implode("\n", $lines);
    }

    /**
     * @param array<string, array<string, mixed>> $states
     */
    private static function renderCamino(?string $name, string $startId, array $states): string
    {
        $paras = array_values(array_unique(self::parasDe($startId, $states, 0, [])));
        if ($paras === []) {
            $paras = [self::paraDesdeOutcome('')];
        }
        $anotar = count($paras) !== 1;
        $body = self::narrar($startId, $states, 0, [], $anotar);
        $lines = [];
        if ($name !== null && $name !== '') {
            $lines[] = 'Camino: ' . $name;
        }
        if (!$anotar) {
            $lines[] = 'Para: ' . $paras[0] . '.';
        }
        $lines[] = 'Pantallas: ' . $body;

        return implode("\n", $lines);
    }

    /**
     * @param array<string, array<string, mixed>> $states
     * @param array<string, true> $seen
     * @return list<string>
     */
    private static function parasDe(string $id, array $states, int $depth, array $seen): array
    {
        if ($depth > self::MAX_SCREENS || isset($seen[$id]) || !isset($states[$id]) || !is_array($states[$id])) {
            return [];
        }
        $seen[$id] = true;
        $state = $states[$id];
        $edges = self::isFinal($state) ? [] : self::continuations($id, $states);
        if ($edges === []) {
            return [self::paraDesdeOutcome(self::outcomeText($state))];
        }
        $out = [];
        foreach ($edges as $edge) {
            foreach (self::parasDe($edge['to'], $states, $depth + 1, $seen) as $para) {
                $out[] = $para;
            }
        }

        return $out;
    }

    private static function paraDesdeOutcome(string $outcome): string
    {
        $folded = self::fold($outcome);
        if (strpos($folded, 'no esta a cargo') !== false) {
            return 'la persona que escribe o también otra persona';
        }

        return 'solo la persona que escribe';
    }

    /**
     * @param array<string, array<string, mixed>> $states
     * @param array<string, true> $seen
     */
    private static function narrar(string $id, array $states, int $depth, array $seen, bool $anotarPara): string
    {
        if ($depth > self::MAX_SCREENS || isset($seen[$id]) || !isset($states[$id]) || !is_array($states[$id])) {
            return '';
        }
        $seen[$id] = true;
        $state = $states[$id];
        $head = self::conAcciones(self::explanation($state), $state);
        if (self::isFinal($state)) {
            return self::conCierre($head, $state, $anotarPara);
        }

        $edges = self::continuations($id, $states);
        if ($edges === []) {
            return self::conCierre($head, $state, $anotarPara);
        }

        $named = [];
        $unnamed = [];
        foreach ($edges as $edge) {
            if (is_string($edge['option']) && $edge['option'] !== '') {
                $named[] = $edge;
                continue;
            }
            $unnamed[] = $edge;
        }
        $forks = count($named) > 1 ? $named : (count($unnamed) > 1 && $named === [] ? $unnamed : []);
        if ($forks !== []) {
            $lines = [self::period($head)];
            foreach ($forks as $edge) {
                $child = self::narrar($edge['to'], $states, $depth + 1, $seen, $anotarPara);
                if ($child === '') {
                    continue;
                }
                $prefix = is_string($edge['option']) && $edge['option'] !== '' ? $edge['option'] . ': ' : '';
                $lines[] = '  - ' . $prefix . self::indentRest($child);
            }

            return implode("\n", $lines);
        }

        $next = (string) ($edges[0]['to'] ?? '');
        $rest = $next === '' ? '' : self::narrar($next, $states, $depth + 1, $seen, $anotarPara);
        $options = self::options($state);
        $text = rtrim(self::period($head), '.');
        if ($options !== [] && ($edges[0]['option'] ?? null) === null) {
            $text .= ' (' . implode('; ', $options) . ')';
        }
        if ($rest === '') {
            return self::conCierre($text, $state, $anotarPara);
        }

        return $text . '. Después: ' . $rest;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function conAcciones(string $text, array $state): string
    {
        $extra = [];
        foreach (self::actionLabels($state) as $label) {
            $extra[] = 'puede ' . self::lowerFirst($label);
        }
        $submit = self::submitLabel($state);
        if ($submit !== '' && !self::isFinal($state)) {
            $extra[] = 'puede ' . self::lowerFirst($submit);
        }
        $text = rtrim(self::period($text), '.');
        if ($extra === []) {
            return $text;
        }

        return $text . ', ' . implode(', ', $extra);
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function conCierre(string $head, array $state, bool $anotarPara): string
    {
        $head = rtrim(trim($head), '.');
        $text = $head === '' ? 'Ahí termina.' : $head . '. Ahí termina.';
        if ($anotarPara) {
            $text .= ' Para: ' . self::paraDesdeOutcome(self::outcomeText($state)) . '.';
        }

        return $text;
    }

    private static function indentRest(string $text): string
    {
        $lines = explode("\n", $text);
        if (count($lines) < 2) {
            return $text;
        }
        $out = [$lines[0]];
        $rest = count($lines);
        for ($i = 1; $i < $rest; $i++) {
            $out[] = '  ' . $lines[$i];
        }

        return implode("\n", $out);
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

    private static function lowerFirst(string $text): string
    {
        if ($text === '') {
            return '';
        }
        $first = mb_substr($text, 0, 1);
        $rest = mb_substr($text, 1);

        return mb_strtolower($first) . $rest;
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

        return self::pasoTexto($text);
    }

    /**
     * El Para del camino ya dice para quién es. El paso nombra la acción, sin repetir «La persona».
     */
    private static function pasoTexto(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^La persona\s+/u', '', $text) ?? $text;
        $text = preg_replace('/,?\s*para la persona que consulta o por otra persona/iu', '', $text) ?? $text;
        $text = trim($text, " \t,");
        if ($text === '') {
            return '';
        }

        return self::upperFirst($text);
    }

    private static function upperFirst(string $text): string
    {
        if ($text === '') {
            return '';
        }
        $first = mb_substr($text, 0, 1);
        $rest = mb_substr($text, 1);

        return mb_strtoupper($first) . $rest;
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
