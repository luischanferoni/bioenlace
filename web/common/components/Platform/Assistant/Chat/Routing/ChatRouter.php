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

/**
 * Enruta por user_goal tras preprocess.
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
            : 'ambiguous_conversational';

        $formattedHistory = $userId > 0
            ? ConversationalHistoryWindow::formatForPrompt($userId, $content)
            : '';

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
        $formattedHistory = $userId > 0
            ? ConversationalHistoryWindow::formatForPrompt($userId, $content)
            : '';

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

            case 'conversational_clinical':
                return ConversationalChannel::handle($content, $userId, $formattedHistory);

            case 'informational_conversational':
            case 'meta':
                return InformationalChannel::handle($content, $userId);

            case 'ambiguous_conversational':
            default:
                return AmbiguousChannel::handle();
        }
    }
}
