<?php

namespace common\components\Platform\Assistant\Chat\Routing;

use common\components\Platform\Assistant\Chat\Channels\Ambiguous\AmbiguousChannel;
use common\components\Platform\Assistant\Chat\Channels\Ambiguous\AmbiguousChannelConfig;
use common\components\Platform\Assistant\Chat\Channels\Conversational\ConversationalChannel;
use common\components\Platform\Assistant\Chat\Channels\Conversational\ConversationalHistoryWindow;
use common\components\Platform\Assistant\Chat\Channels\Informational\InformationalChannel;
use common\components\Platform\Assistant\Chat\Channels\Operational\OperationalChannel;
use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;
use common\components\Platform\Assistant\Chat\Thread\AssistantThreadStateService;

/**
 * Enruta por user_goal tras preprocess (+ hilo / desvío).
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

        $goal = isset($preprocess['user_goal'])
            ? ChatPreprocessService::canonicalizeGoal((string) $preprocess['user_goal'])
            : 'ambiguous';

        $observed = AssistantThreadStateService::observe($userId, $goal, $content);
        $goal = $observed['goal'];
        $preprocess['user_goal'] = $goal;
        \common\components\Platform\Assistant\Chat\ChatPreprocessContext::set($preprocess);

        $formattedHistory = '';
        if ($userId > 0 && empty($observed['clear_history'])) {
            $formattedHistory = ConversationalHistoryWindow::formatForPrompt(
                $userId,
                $content,
                $observed['thread_tag']
            );
        }

        return self::dispatchByGoal($goal, $content, $userId, $formattedHistory);
    }

    /**
     * Encauzamiento desde botón ambiguous (`assistant.channel.*`) o tests con fixture de goal.
     *
     * @return array<string, mixed>
     */
    public static function routeForcedChannel(string $channel, string $content, int $userId): array
    {
        $channel = ChatPreprocessService::canonicalizeGoal($channel);
        \common\components\Platform\Assistant\Chat\ChatPreprocessContext::set([
            'ok' => true,
            'normalized_text' => $content,
            'user_goal' => $channel,
            'action_text' => '',
            'extractions' => [],
        ]);

        $observed = AssistantThreadStateService::observe($userId, $channel, $content);
        $channel = $observed['goal'];

        $formattedHistory = '';
        if ($userId > 0 && empty($observed['clear_history'])) {
            $formattedHistory = ConversationalHistoryWindow::formatForPrompt(
                $userId,
                $content,
                $observed['thread_tag']
            );
        }

        return self::dispatchByGoal($channel, $content, $userId, $formattedHistory);
    }

    /**
     * @return array<string, mixed>
     */
    public static function dispatchByGoal(
        string $goal,
        string $content,
        int $userId,
        string $formattedHistory = ''
    ): array {
        $goal = ChatPreprocessService::canonicalizeGoal($goal);

        switch ($goal) {
            case 'operational':
            case 'in_flow_question':
                return OperationalChannel::handle($content, null, $userId);

            case 'clinical':
                return ConversationalChannel::handle($content, $userId, $formattedHistory);

            case 'informational':
            case 'meta':
                return InformationalChannel::handle($content, $userId);

            case 'ambiguous':
            default:
                return AmbiguousChannel::handle();
        }
    }
}
