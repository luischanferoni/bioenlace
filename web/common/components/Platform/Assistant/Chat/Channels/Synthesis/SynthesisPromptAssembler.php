<?php

namespace common\components\Platform\Assistant\Chat\Channels\Synthesis;

use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;

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
        string $content
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
            'current_message' => $messageForPrompt,
        ]);
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
            $desc = AssistantContextHISArea::description($area);
            $lines[] = $desc !== '' ? '- ' . $area . ' — ' . $desc : '- ' . $area;
        }

        return implode("\n", $lines);
    }
}
