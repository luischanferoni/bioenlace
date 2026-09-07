<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;

/**
 * Formatea {@see intent_semantics} + subintents para prompts de 2ª IA (síntesis / guide).
 *
 * Solo lo que la IA necesita: objetivo + pasos (o outline si hay ramas).
 * `capabilities` quedan en YAML para gates PHP; no van al prompt.
 * `kind` no se emite: se infiere de steps/outline.
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
        $objective = trim((string) ($sem['objective'] ?? $sem['summary'] ?? ''));
        if ($objective === '') {
            $objective = $label;
        }

        $lines = [];
        $lines[] = '- ' . $label . ': ' . $objective;

        $outline = trim((string) ($sem['outline'] ?? ''));
        if ($outline !== '') {
            $lines[] = '  Recorrido: ' . $outline;
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
        $subs = $manifest['subintents'] ?? null;
        if (!is_array($subs) || $subs === []) {
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
}
