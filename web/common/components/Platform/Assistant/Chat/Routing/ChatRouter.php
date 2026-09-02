<?php

namespace common\components\Platform\Assistant\Chat\Routing;

use common\components\Platform\Assistant\Chat\Channels\Ambiguous\AmbiguousChannelConfig;
use common\components\Platform\Assistant\Chat\Channels\Operational\OperationalChannel;
use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;
use common\components\Platform\Assistant\Chat\Preprocess\ChatChannelPolicy;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;
use common\components\Platform\Assistant\Chat\Thread\AssistantThreadStateService;
use common\components\Platform\Assistant\Chat\Routing\Handlers\LegacyRoutingFallback;
use common\components\Platform\Assistant\Chat\Routing\Handlers\SmartCatalogRoutingHandlers;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingService;

/**
 * Router unificado post-preprocess (catálogo inteligente + handlers).
 */
final class ChatRouter
{
    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function routeRootQuery(array $body, int $userId): array
    {
        $content = isset($body['content']) ? trim((string) $body['content']) : '';
        $actionId = $body['action_id'] ?? null;
        if ($actionId !== null && $actionId !== '') {
            $actionId = (string) $actionId;
        } else {
            $actionId = null;
        }

        if ($actionId !== null) {
            if (AmbiguousChannelConfig::isSteerIntentId($actionId)) {
                return self::routeForcedChannel(
                    AmbiguousChannelConfig::channelFromSteerIntentId($actionId),
                    $content,
                    $userId
                );
            }

            AssistantThreadStateService::observe($userId, 'operational', $content);

            return OperationalChannel::handle($content, $actionId, $userId);
        }

        $preprocess = ChatPreprocessService::run($content, $userId);
        if ($preprocess === null) {
            return AssistantEnvelope::message(
                'No pudimos interpretar tu mensaje en este momento. Probá de nuevo en unos segundos.'
            );
        }

        \common\components\Platform\Assistant\Chat\ChatPreprocessContext::set($preprocess);
        self::enrichPreprocessHisContext($content, $preprocess);
        \common\components\Platform\Assistant\Chat\ChatPreprocessContext::set($preprocess);

        $threadGoal = ChatPreprocessService::canonicalizeGoal((string) ($preprocess['user_goal'] ?? 'ambiguous'));
        AssistantThreadStateService::observe($userId, $threadGoal, $content);

        return self::routeFromPreprocess($preprocess, $content, $userId);
    }

    /**
     * Encauzamiento desde botón ambiguous (`assistant.channel.*`) o tests con fixture.
     *
     * @return array<string, mixed>
     */
    public static function routeForcedChannel(string $channel, string $content, int $userId): array
    {
        $channel = trim($channel);
        $routingHint = self::routingHintFromForcedChannel($channel);
        $preprocess = [
            'ok' => true,
            'normalized_text' => $content,
            'necesidad_usuario' => $content !== '' ? $content : 'Necesito orientación en Bioenlace.',
            'routing_hint' => $routingHint,
            'tags' => [],
            'user_goal' => ChatPreprocessService::userGoalFromRoutingHint($routingHint, []),
            'action_text' => '',
            'extractions' => [],
            'context_areas' => $routingHint === 'incompletas'
                ? [AssistantContextHISArea::PRODUCT]
                : [],
            'intent_ids_hint' => [],
        ];

        self::enrichPreprocessHisContext($content, $preprocess);
        \common\components\Platform\Assistant\Chat\ChatPreprocessContext::set($preprocess);
        AssistantThreadStateService::observe($userId, $preprocess['user_goal'], $content);

        return self::routeFromPreprocess($preprocess, $content, $userId);
    }

    /**
     * Compat tests / callers legacy: despacha por user_goal sin GuideChannel.
     *
     * @return array<string, mixed>
     */
    public static function dispatchByGoal(string $goal, string $content, int $userId): array
    {
        $goal = ChatPreprocessService::canonicalizeGoal($goal);
        $routingHint = ChatPreprocessService::routingHintFromLegacyGoal($goal);

        $preprocess = [
            'ok' => true,
            'normalized_text' => $content,
            'necesidad_usuario' => $content,
            'routing_hint' => $routingHint,
            'tags' => [],
            'user_goal' => $goal,
            'action_text' => '',
            'extractions' => [],
            'context_areas' => ChatPreprocessService::normalizeContextAreas(
                $goal === 'guide' ? [AssistantContextHISArea::APPOINTMENTS] : []
            ),
            'intent_ids_hint' => [],
        ];

        if ($goal === 'guide') {
            self::enrichPreprocessHisContext($content, $preprocess);
        }

        \common\components\Platform\Assistant\Chat\ChatPreprocessContext::set($preprocess);

        return self::routeFromPreprocess($preprocess, $content, $userId);
    }

    /**
     * @param array<string, mixed> $preprocess
     * @return array<string, mixed>
     */
    private static function routeFromPreprocess(array $preprocess, string $content, int $userId): array
    {
        $evaluation = SmartCatalogRoutingService::evaluate($preprocess, $userId, $content);
        $preprocess['smart_routing'] = $evaluation->decision->routingResult;
        \common\components\Platform\Assistant\Chat\ChatPreprocessContext::set($preprocess);

        $handled = SmartCatalogRoutingHandlers::tryHandle($evaluation, $content, $userId);
        if ($handled !== null) {
            return self::finalizeWithPlanning($handled);
        }

        return self::finalizeWithPlanning(
            LegacyRoutingFallback::handle($evaluation, $content, $userId)
        );
    }

    /**
     * @param array<string, mixed> $motor
     * @return array<string, mixed>
     */
    private static function finalizeWithPlanning(array $motor): array
    {
        AssistantPlanningLogService::flushToYiiLog();

        return AssistantPlanningLogService::attachDebugIfEnabled($motor);
    }

    private static function routingHintFromForcedChannel(string $channel): string
    {
        return ChatPreprocessService::routingHintFromLegacyGoal(
            ChatPreprocessService::canonicalizeGoal($channel)
        );
    }

    /**
     * Enriquece context_areas / routing_hint; no fuerza GuideChannel.
     *
     * @param array<string, mixed> $preprocess
     */
    private static function enrichPreprocessHisContext(string $content, array &$preprocess): void
    {
        $areas = ChatPreprocessService::normalizeContextAreas($preprocess['context_areas'] ?? []);

        if (ChatChannelPolicy::isAppointmentPolicyQuestion($content)) {
            $areas = array_values(array_unique(array_merge($areas, [AssistantContextHISArea::APPOINTMENTS])));
            $preprocess['context_areas'] = $areas;

            if (!ChatChannelPolicy::requestsOperationalTramiteExecution($content)) {
                $preprocess['routing_hint'] = 'incompletas';
                $preprocess['user_goal'] = ChatPreprocessService::userGoalFromRoutingHint(
                    'incompletas',
                    is_array($preprocess['tags'] ?? null) ? $preprocess['tags'] : []
                );
            }
        }
    }
}
