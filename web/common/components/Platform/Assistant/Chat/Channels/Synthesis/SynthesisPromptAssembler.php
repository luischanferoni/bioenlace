<?php

namespace common\components\Platform\Assistant\Chat\Channels\Synthesis;

use common\components\Platform\Assistant\Catalog\IntentSemanticsPromptFormatter;
use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingEvaluation;

/**
 * Ensambla el prompt de la 2ª IA síntesis (incompletas).
 */
final class SynthesisPromptAssembler
{
    /**
     * @param array<string, mixed> $firstIa
     */
    public static function build(
        array $firstIa,
        string $scopedSystemRecords,
        string $articleBlock,
        string $content,
        ?SmartCatalogRoutingEvaluation $evaluation = null
    ): string {
        $necesidad = trim((string) ($firstIa['necesidad_usuario'] ?? ''));
        if ($necesidad === '') {
            $necesidad = trim((string) ($firstIa['normalized_text'] ?? $content));
        }

        $messageForPrompt = ChatPreprocessContext::normalizedText();
        if ($messageForPrompt === '') {
            $messageForPrompt = trim($content);
        }

        $areas = is_array($firstIa['context_areas'] ?? null)
            ? AssistantContextHISArea::sortByProductPriority($firstIa['context_areas'])
            : ChatPreprocessContext::contextAreas();

        return SynthesisChannelConfig::assemblePrompt([
            'necesidad_usuario' => $necesidad,
            'context_his_areas_lines' => self::formatContextHisAreasLines($areas),
            'scoped_system_records' => trim($scopedSystemRecords),
            'article_block' => trim($articleBlock),
            'intent_semantics' => self::formatIntentSemantics($evaluation),
            'current_message' => $messageForPrompt,
        ]);
    }

    private static function formatIntentSemantics(?SmartCatalogRoutingEvaluation $evaluation): string
    {
        if ($evaluation === null) {
            return '';
        }

        $ids = [];
        $entry = $evaluation->decision->catalogEntry;
        if ($entry !== null) {
            foreach ($entry->ctaIntentIds as $id) {
                $ids[] = $id;
            }
        }
        foreach ($evaluation->match->ranked as $row) {
            $catalogId = trim((string) ($row['catalog_id'] ?? ''));
            if ($catalogId === '') {
                continue;
            }
            $rankedEntry = SmartCatalogRegistry::findById($catalogId);
            if ($rankedEntry === null) {
                continue;
            }
            foreach ($rankedEntry->ctaIntentIds as $id) {
                $ids[] = $id;
            }
            if ($rankedEntry->toolType === 'intent' && $rankedEntry->toolRef !== '') {
                $ids[] = $rankedEntry->toolRef;
            }
        }

        return IntentSemanticsPromptFormatter::formatForIntentIds($ids, 4);
    }

    /**
     * @param list<string> $activeAreas
     */
    private static function formatContextHisAreasLines(array $activeAreas): string
    {
        if ($activeAreas === []) {
            return '';
        }

        $lines = [];
        foreach ($activeAreas as $area) {
            $desc = trim(AssistantContextHISArea::description($area));
            if ($desc === '') {
                continue;
            }
            $lines[] = '- ' . $desc;
        }

        return implode("\n", $lines);
    }
}
