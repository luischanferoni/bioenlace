<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;

/**
 * Traduce un flow YAML a la ficha que la guía adjunta.
 *
 * El YAML describe el recorrido. La ficha resume qué cubre, si hay urgencia adentro y en qué termina,
 * usando explanations, opciones, acciones y outcomes. No lee un bloque de ficha en el YAML.
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

        return self::translateFicha($manifest, $button);
    }

    /**
     * @param array<string, mixed>|null $manifest
     */
    private static function translateFicha(?array $manifest, string $button): string
    {
        $lines = [
            'ID: ' . self::slug($button),
            'TEXTO_BOTÓN: "' . $button . '"',
        ];
        $states = self::statesOf($manifest);
        $initial = $manifest !== null ? self::initialId($manifest, $states) : '';
        $covers = $initial !== '' ? self::coverageLines($initial, $states) : [];
        if ($covers !== []) {
            $lines[] = '';
            $lines[] = 'QUÉ CUBRE:';
            foreach ($covers as $line) {
                $lines[] = $line;
            }
        }
        $urgencia = $initial !== '' ? self::urgenciaTexto($button, $initial, $states) : '';
        if ($urgencia !== '') {
            $lines[] = '';
            $lines[] = 'URGENCIA:';
            $lines[] = $urgencia;
        }
        $resultado = self::resultadoLine($states);
        if ($resultado !== '') {
            $lines[] = '';
            $lines[] = 'RESULTADO:';
            $lines[] = $resultado;
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed>|null $manifest
     * @return array<string, array<string, mixed>>
     */
    private static function statesOf(?array $manifest): array
    {
        if ($manifest === null) {
            return [];
        }
        $states = $manifest['states'] ?? null;
        if (!is_array($states)) {
            return [];
        }
        $out = [];
        foreach ($states as $id => $state) {
            if (is_string($id) && is_array($state)) {
                $out[$id] = $state;
            }
        }

        return $out;
    }

    /**
     * @param array<string, array<string, mixed>> $states
     * @return list<string>
     */
    private static function coverageLines(string $initial, array $states): array
    {
        $choiceId = self::firstChoice($initial, $states);
        if ($choiceId === null) {
            $line = self::linearCoverage($initial, $states);
            return $line === '' ? [] : [$line];
        }

        $state = $states[$choiceId];
        $intro = self::upperFirst(self::sinElige(self::explanation($state)));
        $lines = $intro !== '' ? [$intro . ':'] : [];
        $ramas = [];
        foreach (self::outgoing($state, $states) as $edge) {
            $label = $edge['option'];
            if ($label === null || $label === '' || !isset($states[$edge['to']])) {
                continue;
            }
            $ids = self::reachableFrom($edge['to'], $states);
            $ramas[] = [
                'label' => $label,
                'state' => $states[$edge['to']],
                'ids' => $ids,
                'tel' => self::telEn($ids, $states),
                'clases' => self::clasesResultado($ids, $states),
            ];
        }
        $firmaComun = null;
        $mismaFirma = true;
        foreach ($ramas as $rama) {
            if ($rama['tel'] !== null) {
                continue;
            }
            $keys = array_keys($rama['clases']);
            sort($keys);
            $firma = implode('|', $keys);
            if ($firmaComun === null) {
                $firmaComun = $firma;
                continue;
            }
            if ($firma !== $firmaComun) {
                $mismaFirma = false;
                break;
            }
        }
        $medicacionYa = false;
        foreach ($ramas as $rama) {
            if ($rama['tel'] !== null) {
                $lines[] = '- ' . self::bulletUrgencia($rama['state'], $rama['tel']);
                continue;
            }
            $text = $rama['label'];
            if (!$mismaFirma && isset($rama['clases']['turno'], $rama['clases']['consulta'])) {
                $text .= ': consulta o turno';
            }
            $lines[] = '- ' . self::period($text);
            if ($medicacionYa) {
                continue;
            }
            $med = self::fraseMedicacion($rama['ids'], $states);
            if ($med !== '') {
                $medicacionYa = true;
                $lines[] = '- ' . self::period($med);
            }
        }

        return $lines;
    }

    /**
     * @param array<string, array<string, mixed>> $states
     */
    private static function firstChoice(string $id, array $states): ?string
    {
        $seen = [];
        for ($guard = 0; $guard < self::MAX_SCREENS; $guard++) {
            if ($id === '' || isset($seen[$id]) || !isset($states[$id])) {
                return null;
            }
            $seen[$id] = true;
            if (self::options($states[$id]) !== []) {
                return $id;
            }
            $edges = self::continuations($id, $states);
            if (count($edges) !== 1 || ($edges[0]['option'] ?? null) !== null) {
                return null;
            }
            $id = $edges[0]['to'];
        }

        return null;
    }

    /**
     * @param array<string, array<string, mixed>> $states
     */
    private static function linearCoverage(string $id, array $states): string
    {
        $parts = [];
        $seen = [];
        for ($guard = 0; $guard < self::MAX_SCREENS; $guard++) {
            if ($id === '' || isset($seen[$id]) || !isset($states[$id])) {
                break;
            }
            $seen[$id] = true;
            $state = $states[$id];
            $bit = self::sinElige(self::explanation($state));
            if ($bit !== '') {
                $parts[] = $bit;
            }
            if (self::isFinal($state)) {
                break;
            }
            $edges = self::continuations($id, $states);
            if (count($edges) !== 1) {
                break;
            }
            $id = $edges[0]['to'];
        }

        return self::period(self::joinY($parts));
    }

    /**
     * @param array<string, mixed> $state
     * @param array{label: string, href: string} $tel
     */
    private static function bulletUrgencia(array $state, array $tel): string
    {
        $clause = self::primeraClausula(self::explanation($state));
        if ($clause === '') {
            $clause = 'Urgencia';
        }

        return self::period($clause . ' (dentro del flujo, deriva a ' . self::lowerFirst($tel['label']) . ')');
    }

    /**
     * @param array<string, array<string, mixed>> $states
     */
    private static function urgenciaTexto(string $button, string $initial, array $states): string
    {
        $choiceId = self::firstChoice($initial, $states);
        $start = $choiceId ?? $initial;
        if (!isset($states[$start])) {
            return '';
        }
        foreach (self::outgoing($states[$start], $states) as $edge) {
            $ids = self::reachableFrom($edge['to'], $states);
            $tel = self::telEn($ids, $states);
            if ($tel === null || !isset($states[$edge['to']])) {
                continue;
            }
            $incluye = self::lowerFirst(rtrim(self::explanation($states[$edge['to']]), '.'));
            $accion = $tel['label'];

            return 'Si el relato sugiere urgencia o el usuario la declara, mencionar en el mensaje "' . $accion . '", sin retrasar.'
                . "\n"
                . 'El botón "' . $button . '" puede ofrecerse igual, porque dentro incluye ' . $incluye . '.'
                . "\n"
                . 'Si la urgencia es explícita, priorizar la mención de "' . $accion . '" en el mensaje.';
        }

        return '';
    }

    /**
     * @param array<string, array<string, mixed>> $states
     */
    private static function resultadoLine(array $states): string
    {
        $ids = [];
        foreach ($states as $id => $state) {
            if (is_array($state)) {
                $ids[$id] = true;
            }
        }
        $clases = self::clasesResultado($ids, $states);
        $parts = [];
        if (isset($clases['turno'])) {
            $parts[] = 'Turno agendado';
        }
        if (isset($clases['consulta'])) {
            $parts[] = 'mensaje enviado';
        }
        if (isset($clases['urgencia'])) {
            $parts[] = $clases['urgencia'];
        }
        $n = count($parts);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return self::period($parts[0]);
        }
        $last = array_pop($parts);

        return self::period(implode(', ', $parts) . ', o ' . $last);
    }

    /**
     * @param array<string, true> $ids
     * @param array<string, array<string, mixed>> $states
     * @return array<string, string>
     */
    private static function clasesResultado(array $ids, array $states): array
    {
        $clases = [];
        foreach ($ids as $id => $_) {
            if (!isset($states[$id])) {
                continue;
            }
            $state = $states[$id];
            $outcome = self::fold(self::outcomeText($state));
            if ($outcome === '') {
                continue;
            }
            if (strpos($outcome, 'reserva un turno') !== false) {
                $clases['turno'] = 'turno';
            }
            if (strpos($outcome, 'urgencia') !== false) {
                $tel = self::telsDeEstado($state);
                $clases['urgencia'] = $tel !== []
                    ? 'derivación a ' . self::lowerFirst($tel[0]['label'])
                    : 'derivación por urgencia';
                continue;
            }
            if (strpos($outcome, 'medicacion') !== false) {
                continue;
            }
            if (strpos($outcome, 'envia la consulta') !== false || strpos($outcome, 'envia el pedido') !== false) {
                $clases['consulta'] = 'consulta';
            }
        }

        return $clases;
    }

    /**
     * @param array<string, true> $ids
     * @param array<string, array<string, mixed>> $states
     */
    private static function fraseMedicacion(array $ids, array $states): string
    {
        $renovacion = false;
        $ajuste = false;
        $medicacion = false;
        foreach ($ids as $id => $_) {
            if (!isset($states[$id])) {
                continue;
            }
            $state = $states[$id];
            $blob = self::fold(
                self::explanation($state) . ' ' . self::submitLabel($state) . ' ' . self::outcomeText($state)
            );
            $tags = isset($state['meta']['tags']) && is_array($state['meta']['tags']) ? $state['meta']['tags'] : [];
            foreach ($tags as $tag) {
                $blob .= ' ' . self::fold((string) $tag);
            }
            if (strpos($blob, 'medicacion') !== false || strpos($blob, 'medicamento') !== false) {
                $medicacion = true;
            }
            if (strpos($blob, 'renov') !== false) {
                $renovacion = true;
            }
            if (strpos($blob, 'ajuste') !== false || strpos($blob, 'ajustar') !== false) {
                $ajuste = true;
            }
            if (strpos($blob, 'cambio') !== false && strpos($blob, 'medicacion') !== false) {
                $ajuste = true;
            }
        }
        if ($renovacion && $ajuste) {
            return 'Renovación o ajuste de medicación';
        }
        if ($renovacion) {
            return 'Renovación de medicación';
        }
        if ($ajuste) {
            return 'Ajuste de medicación';
        }
        if ($medicacion) {
            return 'Medicación';
        }

        return '';
    }

    /**
     * @param array<string, true> $ids
     * @param array<string, array<string, mixed>> $states
     * @return array{label: string, href: string}|null
     */
    private static function telEn(array $ids, array $states): ?array
    {
        foreach ($ids as $id => $_) {
            if (!isset($states[$id])) {
                continue;
            }
            $tels = self::telsDeEstado($states[$id]);
            if ($tels !== []) {
                return $tels[0];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $state
     * @return list<array{label: string, href: string}>
     */
    private static function telsDeEstado(array $state): array
    {
        $meta = isset($state['meta']) && is_array($state['meta']) ? $state['meta'] : [];
        $actions = $meta['flow_actions'] ?? null;
        if (!is_array($actions)) {
            return [];
        }
        $out = [];
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $label = trim((string) ($action['label'] ?? ''));
            $href = trim((string) ($action['href'] ?? ''));
            if ($label !== '' && strncmp($href, 'tel:', 4) === 0) {
                $out[] = ['label' => $label, 'href' => $href];
            }
        }

        return $out;
    }

    private static function primeraClausula(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $comma = strpos($text, ',');
        if ($comma !== false) {
            $text = substr($text, 0, $comma);
        }

        return trim($text, " \t.");
    }

    private static function sinPersona(string $text): string
    {
        $text = trim($text);
        if (stripos($text, 'La persona ') === 0) {
            $text = trim(substr($text, strlen('La persona ')));
        }

        return $text;
    }

    private static function sinElige(string $text): string
    {
        $text = self::sinPersona($text);
        if (stripos($text, 'elige ') === 0) {
            $text = trim(substr($text, strlen('elige ')));
        }

        return trim($text, " \t.");
    }

    /**
     * @param list<string> $parts
     */
    private static function joinY(array $parts): string
    {
        $clean = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $clean[] = $part;
            }
        }
        $n = count($clean);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return self::upperFirst($clean[0]);
        }
        $last = array_pop($clean);

        return self::upperFirst(implode(', ', $clean) . ' y ' . $last);
    }

    private static function slug(string $text): string
    {
        $text = self::fold($text);
        $text = preg_replace('/[^a-z0-9]+/', '_', $text) ?? '';

        return trim($text, '_');
    }

    private static function upperFirst(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }

    private static function lowerFirst(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return mb_strtolower(mb_substr($text, 0, 1)) . mb_substr($text, 1);
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
