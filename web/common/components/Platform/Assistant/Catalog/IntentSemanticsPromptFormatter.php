<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;
use common\components\Platform\Assistant\SubIntentEngine\FlowStatechart;

/**
 * Formatea {@see intent_semantics} y el statechart para prompts de 2ª IA (guide).
 *
 * Solo lo que la IA necesita: objetivo + pasos.
 * Un statechart grande muestra el estado inicial, sus transiciones y los cierres.
 */
final class IntentSemanticsPromptFormatter
{
    private const MAX_STEPS = 12;

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
     * Recorte: solo los estados cuyo meta.tags cruzó con el preprocess.
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
            $sem = self::semanticsFrom($manifest, null);
            $label = self::label($manifest, null, $intentId);
            $objective = trim((string) ($sem['objective'] ?? ''));
            if ($objective === '') {
                $objective = $label;
            }
            $lines = ['- ' . $label . ': ' . $objective, '  Estados:'];
            foreach ($states as $state) {
                if (!is_array($state)) {
                    continue;
                }
                $description = trim((string) ($state['description'] ?? ''));
                if ($description === '') {
                    continue;
                }
                $lines[] = '    - ' . $description;
            }
            if (count($lines) <= 2) {
                continue;
            }
            $blocks[] = implode("\n", $lines);
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
        $sem = self::semanticsFrom($manifest, $item);
        $label = self::label($manifest, $item, $intentId);
        $objective = trim((string) ($sem['objective'] ?? ''));
        if ($objective === '') {
            $objective = $label;
        }

        $lines = [];
        $lines[] = '- ' . $label . ': ' . $objective;

        $slice = self::statechartSlice($manifest);
        if ($slice !== []) {
            foreach ($slice as $line) {
                $lines[] = $line;
            }
        } else {
            foreach (self::stepLines($manifest) as $stepLine) {
                $lines[] = $stepLine;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed>|null $manifest
     * @return array<string, mixed>
     */
    private static function semanticsFrom(?array $manifest, ?UiActionCatalogItem $item): array
    {
        if ($item !== null && is_array($item->intent_semantics)) {
            return $item->intent_semantics;
        }
        if ($manifest !== null && isset($manifest['intent_semantics']) && is_array($manifest['intent_semantics'])) {
            return YamlIntentCatalogService::normalizeIntentSemanticsPublic($manifest['intent_semantics']);
        }

        return [];
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
    private static function stepLines(?array $manifest): array
    {
        if ($manifest === null) {
            return [];
        }
        $subs = FlowStatechart::ordered($manifest);
        if ($subs === []) {
            return [];
        }

        $lines = ['  Pasos:'];
        $count = 0;
        $total = 0;
        foreach ($subs as $sub) {
            if (!is_array($sub)) {
                continue;
            }
            $id = trim((string) ($sub['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $total++;
            if ($count >= self::MAX_STEPS) {
                continue;
            }
            $does = trim((string) ($sub['assistant_text'] ?? ''));
            if ($does === '') {
                $does = $id;
            }
            $lines[] = '    ' . ($count + 1) . '. ' . $does;
            $count++;
        }
        if ($total > self::MAX_STEPS) {
            $lines[] = '    … (+' . ($total - self::MAX_STEPS) . ' pasos más)';
        }

        return $count > 0 ? $lines : [];
    }

    /**
     * Statechart con más estados que el tope de pasos: solo la raíz y los cierres.
     *
     * @param array<string, mixed>|null $manifest
     * @return list<string>
     */
    private static function statechartSlice(?array $manifest): array
    {
        if ($manifest === null) {
            return [];
        }
        $states = $manifest['states'] ?? null;
        if (!is_array($states) || count($states) <= self::MAX_STEPS) {
            return [];
        }

        $initial = trim((string) ($manifest['initial'] ?? ''));
        if ($initial === '' || !isset($states[$initial]) || !is_array($states[$initial])) {
            $initial = '';
            foreach ($states as $id => $state) {
                if (is_string($id) && is_array($state)) {
                    $initial = $id;
                    break;
                }
            }
        }
        if ($initial === '' || !is_array($states[$initial])) {
            return [];
        }

        $lines = ['  Recorrido:'];
        $lines[] = '    ' . self::stateLabel($initial, $states[$initial]);
        foreach (self::alwaysRows($states[$initial]['always'] ?? null) as $row) {
            $target = $row['target'];
            $targetState = isset($states[$target]) && is_array($states[$target]) ? $states[$target] : [];
            $dest = $target === '' ? 'fin' : self::stateLabel($target, $targetState);
            $guard = $row['guard'] === '' ? 'en otro caso' : $row['guard'];
            $lines[] = '    - ' . $guard . ' → ' . $dest;
        }

        $finals = [];
        foreach ($states as $id => $state) {
            if (!is_string($id) || !is_array($state)) {
                continue;
            }
            if (trim((string) ($state['type'] ?? '')) !== 'final') {
                continue;
            }
            $finals[] = self::stateLabel($id, $state);
        }
        if ($finals !== []) {
            $lines[] = '    Cierres: ' . implode('; ', $finals);
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function stateLabel(string $id, array $state): string
    {
        $description = trim((string) ($state['description'] ?? ''));

        return $description !== '' ? $description : $id;
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
