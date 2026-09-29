<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;

/**
 * Arma el recorrido de un flow en JSON para el prompt de la guía.
 *
 * - `boton`: control del chat.
 * - Con menú inicial: `recorridos[]` (nombre, objetivo, pasos, exito).
 * - Sin menú: `objetivo` / `pasos` / `exito` a nivel del botón.
 * - `elige_entre` solo si el paso declara `meta.guide_options`.
 * - `ofrece` desde `meta.flow_actions`.
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
        $items = [];
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
            $item = self::buildIntentPayload($intentId);
            if ($item !== null) {
                $items[] = $item;
            }
            if (count($items) >= max(1, $maxIntents)) {
                break;
            }
        }

        return self::encode(['funcionalidades' => $items]);
    }

    /**
     * Intents cuyo tag cruzó. El bloque es el recorrido del flow, no la lista de estados que matchearon.
     *
     * @param list<array{intent_id: string, score: int, states: list<array{id: string, description: string}>}> $hits
     */
    public static function formatStateHits(array $hits, int $maxIntents = 4): string
    {
        $items = [];
        foreach ($hits as $hit) {
            if (!is_array($hit)) {
                continue;
            }
            $intentId = trim((string) ($hit['intent_id'] ?? ''));
            $states = $hit['states'] ?? [];
            if ($intentId === '' || !is_array($states) || $states === []) {
                continue;
            }
            $item = self::buildIntentPayload($intentId);
            if ($item === null) {
                continue;
            }
            $items[] = $item;
            if (count($items) >= max(1, $maxIntents)) {
                break;
            }
        }

        return self::encode(['funcionalidades' => $items]);
    }

    public static function formatCatalogItem(UiActionCatalogItem $item): string
    {
        return self::formatIntentId($item->action_id, $item);
    }

    public static function formatIntentId(string $intentId, ?UiActionCatalogItem $item = null, int $root = 1): string
    {
        unset($root);
        $payload = self::buildIntentPayload($intentId, $item);
        if ($payload === null) {
            return '';
        }

        return self::encode(['funcionalidades' => [$payload]]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function buildIntentPayload(string $intentId, ?UiActionCatalogItem $item = null): ?array
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return null;
        }
        $manifest = YamlIntentManifestLoader::load($intentId);
        $label = self::label($manifest, $item, $intentId);
        if ($label === '') {
            return null;
        }

        return self::buildFuncionalidad($manifest, $label);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function encode(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return is_string($json) ? $json : '';
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
     * @return array<string, mixed>
     */
    private static function buildFuncionalidad(?array $manifest, string $label): array
    {
        $out = ['boton' => $label];
        if ($manifest === null) {
            return $out;
        }
        $states = $manifest['states'] ?? null;
        if (!is_array($states) || $states === []) {
            return $out;
        }
        $initial = self::initialId($manifest, $states);
        if ($initial === '' || !isset($states[$initial]) || !is_array($states[$initial])) {
            return $out;
        }

        $initialState = $states[$initial];
        $intro = self::explanation($initialState);
        if ($intro !== '') {
            $out['al_presionar'] = $intro;
        }

        $rows = self::pickBranches(self::alwaysRows($initialState['always'] ?? null));
        $menuChoices = self::rowsAreMenuChoices($initialState, $rows);
        if ($menuChoices) {
            $recorridos = [];
            foreach ($rows as $row) {
                if (self::optionLabelFromGuard($initialState, $row['guard']) === '') {
                    continue;
                }
                $recorrido = self::buildRecorrido($initialState, $row, $states);
                if ($recorrido !== null) {
                    $recorridos[] = $recorrido;
                }
            }
            if ($recorridos !== []) {
                $out['recorridos'] = $recorridos;
            }

            return $out;
        }

        foreach ($rows as $row) {
            $flat = self::buildFlatPath($row, $states);
            if ($flat === null) {
                continue;
            }
            foreach ($flat as $key => $value) {
                $out[$key] = $value;
            }
            break;
        }

        return $out;
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
     * @return array<string, mixed>|null
     */
    private static function buildRecorrido(array $sourceState, array $row, array $states): ?array
    {
        $chain = self::chainStates($states, $row['target']);
        $steps = $chain['states'];
        if ($steps === []) {
            return null;
        }
        $option = self::optionLabelFromGuard($sourceState, $row['guard']);
        if ($option === '') {
            return null;
        }
        $help = self::helpFromGuard($sourceState, $row['guard']);
        $last = $steps[count($steps) - 1];
        $stops = self::chainStops($steps, $chain['cut']);
        $goal = self::objectiveClause($last);

        $out = ['nombre' => $option];
        if ($goal !== '') {
            $out['objetivo'] = $goal;
        }
        $pasos = self::buildPasos($steps, $help, $chain['cut']);
        if ($pasos !== []) {
            $out['pasos'] = $pasos;
        }
        if ($stops && self::outcomeText($last) !== '') {
            $out['exito'] = self::closingSentence($last);
        }

        return $out;
    }

    /**
     * @param array{guard: string, target: string} $row
     * @param array<string, array<string, mixed>> $states
     * @return array<string, mixed>|null
     */
    private static function buildFlatPath(array $row, array $states): ?array
    {
        $chain = self::chainStates($states, $row['target']);
        $steps = $chain['states'];
        if ($steps === []) {
            return null;
        }
        $last = $steps[count($steps) - 1];
        $stops = self::chainStops($steps, $chain['cut']);
        $goal = self::objectiveClause($last);
        $out = [];
        if ($goal !== '') {
            $out['objetivo'] = $goal;
        }
        $pasos = self::buildPasos($steps, '', $chain['cut']);
        if ($pasos !== []) {
            $out['pasos'] = $pasos;
        }
        if ($stops && self::outcomeText($last) !== '') {
            $out['exito'] = self::closingSentence($last);
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param list<array<string, mixed>> $steps
     * @return list<array<string, mixed>>
     */
    private static function buildPasos(array $steps, string $help, bool $cut): array
    {
        $pasos = [];
        $lastIndex = count($steps) - 1;
        foreach ($steps as $index => $step) {
            $text = self::explanation($step);
            if ($help !== '' && $index === 0 && $lastIndex === 0) {
                $text = 'El sistema muestra orientación por urgencia y frena la reserva en la app.';
            }
            if ($text === '') {
                continue;
            }
            $paso = ['hace' => $text];
            $labels = self::options($step);
            if ($labels !== []) {
                $paso['elige_entre'] = $labels;
            }
            $offers = self::actionLabels($step);
            if ($offers !== []) {
                $paso['ofrece'] = $offers;
            }
            $pasos[] = $paso;
        }
        if ($cut) {
            $pasos[] = ['hace' => '…'];
        }

        return $pasos;
    }

    /**
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
