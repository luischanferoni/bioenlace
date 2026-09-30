<?php

namespace common\components\Platform\Assistant\Planning;

use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;
use common\components\Platform\Assistant\Chat\Thread\ThreadNeedList;
use common\components\Platform\Assistant\Preprocess\PreprocessRoutingHintCatalog;

/**
 * Adapta preprocess → shape 1ª IA v1.
 * Los tags son solo los que devolvió la 1ª IA (sin inferencia PHP).
 */
final class AssistantFirstIaAdapter
{
    /**
     * @param array<string, mixed> $preprocess
     * @return array{
     *   normalized_text: string,
     *   necesidad_usuario: string,
     *   routing_hint: string,
     *   tags: list<string>,
     *   context_areas: list<string>,
     *   extractions: list<array{span: string, category: string, synonyms: list<string>}>,
     *   intent_ids_hint: list<string>
     * }
     */
    public static function fromPreprocess(array $preprocess, string $rawContent = ''): array
    {
        $normalized = trim((string) ($preprocess['normalized_text'] ?? $rawContent));
        $extractions = is_array($preprocess['extractions'] ?? null) ? $preprocess['extractions'] : [];

        $tags = ChatPreprocessService::normalizeTags($preprocess['tags'] ?? []);

        $actionText = trim((string) ($preprocess['action_text'] ?? ''));
        $needs = isset($preprocess['necesidades_usuario']) && is_array($preprocess['necesidades_usuario'])
            ? ThreadNeedList::normalize($preprocess['necesidades_usuario'])
            : [];
        if ($needs !== []) {
            $necesidad = ThreadNeedList::activeText($needs);
        } else {
            $necesidad = trim((string) ($preprocess['necesidad_usuario'] ?? ''));
            if ($necesidad === '') {
                $necesidad = $actionText !== '' ? $actionText : $normalized;
            }
        }

        $routingHint = ChatPreprocessService::canonicalizeRoutingHint((string) ($preprocess['routing_hint'] ?? ''));
        if ($routingHint === PreprocessRoutingHintCatalog::SIN_PEDIDO && isset($preprocess['user_goal'])) {
            $routingHint = ChatPreprocessService::routingHintFromLegacyGoal(
                ChatPreprocessService::canonicalizeGoal((string) $preprocess['user_goal'])
            );
        }

        $intentHints = [];
        if (isset($preprocess['intent_ids_hint']) && is_array($preprocess['intent_ids_hint'])) {
            foreach ($preprocess['intent_ids_hint'] as $id) {
                if (is_string($id) && trim($id) !== '') {
                    $intentHints[] = trim($id);
                }
            }
        }

        return [
            'normalized_text' => $normalized,
            'necesidad_usuario' => $necesidad,
            'necesidades_usuario' => $needs,
            'routing_hint' => $routingHint,
            'tags' => $tags,
            'context_areas' => [],
            'extractions' => $extractions,
            'intent_ids_hint' => $intentHints,
        ];
    }
}
