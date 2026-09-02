<?php

namespace common\components\Platform\Assistant\Planning;

use common\components\Platform\Assistant\Chat\Preprocess\ChatChannelPolicy;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;

/**
 * Adapta preprocess → shape 1ª IA v1 (campos nativos o inferidos).
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
        $areas = ChatPreprocessService::normalizeContextAreas($preprocess['context_areas'] ?? []);
        $extractions = is_array($preprocess['extractions'] ?? null) ? $preprocess['extractions'] : [];
        $goal = ChatPreprocessService::canonicalizeGoal((string) ($preprocess['user_goal'] ?? 'ambiguous'));

        $tags = ChatPreprocessService::normalizeTags($preprocess['tags'] ?? []);
        $tags = array_values(array_unique(array_merge(
            $tags,
            self::inferTags($normalized, $areas, $goal, $tags !== [])
        )));

        $actionText = trim((string) ($preprocess['action_text'] ?? ''));
        $necesidad = trim((string) ($preprocess['necesidad_usuario'] ?? ''));
        if ($necesidad === '') {
            $necesidad = $actionText !== '' ? $actionText : $normalized;
        }

        $routingHint = ChatPreprocessService::canonicalizeRoutingHint((string) ($preprocess['routing_hint'] ?? ''));
        if ($routingHint === 'dudosa' && isset($preprocess['user_goal'])) {
            $routingHint = self::routingHintFromGoal(
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
            'routing_hint' => $routingHint,
            'tags' => $tags,
            'context_areas' => $areas,
            'extractions' => $extractions,
            'intent_ids_hint' => $intentHints,
        ];
    }

    /**
     * @param list<string> $areas
     * @param list<string> $existingTags
     * @return list<string>
     */
    private static function inferTags(string $normalized, array $areas, string $goal, bool $skipHeuristics = false): array
    {
        if ($skipHeuristics) {
            return [];
        }

        $tags = [];
        foreach ($areas as $area) {
            $tags[] = $area;
        }

        if ($normalized !== '') {
            if (ChatChannelPolicy::isAppointmentPolicyQuestion($normalized)) {
                $tags[] = 'llegar_tarde';
            }
            if (ChatPreprocessService::isClinicalSymptomContent($normalized)) {
                $tags[] = 'sintoma';
                $tags[] = 'necesito_atencion';
            }
            if (preg_match('/\b(medium|horoscop|clima)\b/u', mb_strtolower($normalized, 'UTF-8'))) {
                $tags[] = 'fuera_his';
            }
            if (preg_match('/\b(representacion|representación|tutela|sobrin|sobrina|menor|representante)\b/u', mb_strtolower($normalized, 'UTF-8'))) {
                $tags[] = 'representacion';
            }
        }

        if ($goal === 'operational') {
            $tags[] = 'tramite';
        }

        return array_values(array_unique($tags));
    }

    private static function routingHintFromGoal(string $goal): string
    {
        if ($goal === 'operational' || $goal === 'in_flow_question') {
            return 'clara';
        }
        if ($goal === 'guide') {
            return 'incompletas';
        }

        return 'dudosa';
    }
}
