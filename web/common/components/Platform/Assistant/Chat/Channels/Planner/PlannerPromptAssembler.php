<?php

namespace common\components\Platform\Assistant\Chat\Channels\Planner;

/**
 * Ensambla el prompt de la IA planificadora.
 */
final class PlannerPromptAssembler
{
    /**
     * @param array<string, mixed> $firstIa
     * @param list<array{tool_id: string, tool_type: string, description: string, param_schema: array<string, mixed>|\stdClass}> $shortlist
     * @param list<string> $declarativeToolIds
     */
    public static function build(array $firstIa, array $shortlist, array $declarativeToolIds): string
    {
        $necesidad = trim((string) ($firstIa['necesidad_usuario'] ?? ''));
        if ($necesidad === '') {
            $necesidad = trim((string) ($firstIa['normalized_text'] ?? ''));
        }

        $shortlistJson = json_encode($shortlist, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($shortlistJson === false) {
            $shortlistJson = '[]';
        }

        $declarativeJson = json_encode(array_values($declarativeToolIds), JSON_UNESCAPED_UNICODE);
        if ($declarativeJson === false) {
            $declarativeJson = '[]';
        }

        return PlannerChannelConfig::assemblePrompt([
            'necesidad_usuario' => $necesidad,
            'declarative_tool_ids_json' => $declarativeJson,
            'shortlist_json' => $shortlistJson,
        ]);
    }
}
