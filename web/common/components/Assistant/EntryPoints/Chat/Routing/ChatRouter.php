<?php

namespace common\components\Assistant\EntryPoints\Chat\Routing;

use common\components\Assistant\EntryPoints\Chat\Channels\Conversational\ConversationalChannel;
use common\components\Assistant\EntryPoints\Chat\Channels\Informational\InformationalChannel;
use common\components\Assistant\EntryPoints\Chat\Channels\Operational\OperationalChannel;
use common\components\Assistant\EntryPoints\Chat\Envelope\AssistantEnvelope;
use common\components\Assistant\EntryPoints\Chat\Preprocess\ChatPreprocessService;
use common\components\Assistant\IntentEngine\IntentEngine;

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
            return OperationalChannel::handle($content, $actionId, $userId);
        }

        $preprocess = ChatPreprocessService::run($content, $userId);
        \common\components\Assistant\EntryPoints\Chat\ChatPreprocessContext::set($preprocess);

        $goal = isset($preprocess['user_goal']) ? trim((string) $preprocess['user_goal']) : 'unclear';
        $queryText = isset($preprocess['normalized_text']) ? trim((string) $preprocess['normalized_text']) : $content;
        if ($queryText === '') {
            $queryText = $content;
        }

        if (ChatPreprocessService::isStaffDataAccessOperationalQuery($queryText)) {
            return OperationalChannel::handle($content, null, $userId);
        }

        switch ($goal) {
            case 'operational':
            case 'in_flow_question':
                return OperationalChannel::handle($content, null, $userId);

            case 'conversational':
                return ConversationalChannel::handle($content, $userId);

            case 'informational':
            case 'meta':
                return InformationalChannel::handle($content, $userId);

            default:
                return AssistantEnvelope::message(
                    'No entendí bien tu pedido. ¿Podés contarme qué querés hacer en el sistema?'
                );
        }
    }
}
