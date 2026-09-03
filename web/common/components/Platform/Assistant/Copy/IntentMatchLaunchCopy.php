<?php

namespace common\components\Platform\Assistant\Copy;

use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;

/**
 * Texto de arranque cuando PHP matchea un intent al 100% (sin 2ª IA).
 *
 * Copy genérico en channel-copy.yaml. No usa necesidad_usuario (etiqueta interna del preprocess).
 */
final class IntentMatchLaunchCopy
{
    /**
     * @param list<string>|null $extractionSpans
     */
    public static function forFlowLaunch(
        UiActionCatalogItem $item,
        string $flowText = '',
        ?array $extractionSpans = null
    ): string {
        $lead = self::leadText($item, $extractionSpans);
        $step = self::userFacingStepPrompt($flowText, $item->display_name, $lead);
        if ($lead === '') {
            return $step;
        }
        if ($step === '') {
            return $lead;
        }

        return $lead . "\n\n" . $step;
    }

    public static function forOpenAction(UiActionCatalogItem $item, ?array $extractionSpans = null): string
    {
        $lead = self::leadText($item, $extractionSpans);
        if ($lead !== '') {
            return $lead;
        }

        return self::fallbackLabel($item);
    }

    /**
     * @param list<string>|null $extractionSpans
     */
    private static function leadText(UiActionCatalogItem $item, ?array $extractionSpans): string
    {
        $spans = $extractionSpans ?? self::spansFromContext();
        $label = self::fallbackLabel($item);
        $vars = [
            'label' => $label,
            'summary' => self::summaryFromItem($item),
            'spans' => implode(', ', $spans),
        ];

        if ($spans !== []) {
            $text = trim(AssistantChannelCopy::t('full_match_intent_with_spans', $vars));
            if ($text !== '') {
                return $text;
            }
        }

        $text = trim(AssistantChannelCopy::t('full_match_intent', $vars));
        if ($text !== '') {
            return $text;
        }

        $text = trim(AssistantChannelCopy::t('full_match_intent_fallback', $vars));

        return $text !== '' ? $text : $label;
    }

    private static function fallbackLabel(UiActionCatalogItem $item): string
    {
        $label = trim($item->display_name);

        return $label !== '' ? $label : 'esta gestión';
    }

    private static function summaryFromItem(UiActionCatalogItem $item): string
    {
        $sem = $item->intent_semantics;
        if (!is_array($sem)) {
            return trim($item->description);
        }
        $summary = trim((string) ($sem['summary'] ?? ''));
        if ($summary !== '') {
            return $summary;
        }

        return trim($item->description);
    }

    /**
     * @return list<string>
     */
    private static function spansFromContext(): array
    {
        try {
            return self::uniqueSpans(ChatPreprocessContext::extractions());
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param list<array<string, mixed>> $extractions
     * @return list<string>
     */
    public static function uniqueSpans(array $extractions): array
    {
        $out = [];
        $seen = [];
        foreach ($extractions as $row) {
            $span = trim((string) ($row['span'] ?? ''));
            if ($span === '') {
                continue;
            }
            $key = mb_strtolower($span);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $span;
        }

        return $out;
    }

    private static function userFacingStepPrompt(string $flowText, string $label, string $lead): string
    {
        $step = trim($flowText);
        if ($step === '') {
            return '';
        }
        if (self::sameText($step, $lead) || self::sameText($step, $label)) {
            return '';
        }
        if (str_contains($lead, $step)) {
            return '';
        }
        if (str_contains($step, '?')) {
            return $step;
        }
        if (mb_strlen($step) >= 40) {
            return $step;
        }

        return '';
    }

    private static function sameText(string $a, string $b): bool
    {
        return mb_strtolower(trim($a)) === mb_strtolower(trim($b));
    }
}
