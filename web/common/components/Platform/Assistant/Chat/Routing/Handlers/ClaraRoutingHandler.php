<?php

namespace common\components\Platform\Assistant\Chat\Routing\Handlers;

use common\components\Platform\Assistant\Chat\Channels\Operational\OperationalChannel;
use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;
use common\components\Platform\Assistant\Copy\AssistantChannelCopy;
use common\components\Platform\Assistant\Service\AssistantDraftNormalizer;

/**
 * Match 100% a un intent: abre el flow (1 IA).
 */
final class ClaraRoutingHandler
{
    /**
     * @return array<string, mixed>
     */
    public static function handleSingle(string $content, string $intentId, int $userId): array
    {
        $intentId = trim($intentId);
        $out = OperationalChannel::handle($content, $intentId, $userId);

        return self::ensureIntentVisible($out, $intentId);
    }

    /**
     * Garantiza que el intent decidido por smart-catalog quede en el envelope
     * (QA / clientes leen session.intent_id o intent_id).
     *
     * @param array<string, mixed> $out
     * @return array<string, mixed>
     */
    private static function ensureIntentVisible(array $out, string $intentId): array
    {
        if ($intentId === '') {
            return $out;
        }

        if (AssistantEnvelope::isPublicEnvelope($out)) {
            if (($out['kind'] ?? '') === 'flow') {
                $session = isset($out['session']) && is_array($out['session']) ? $out['session'] : [];
                if (AssistantDraftNormalizer::scalarString($session['intent_id'] ?? '') === '') {
                    $session['intent_id'] = $intentId;
                }
                $out['session'] = $session;
            }
            if (AssistantDraftNormalizer::scalarString($out['intent_id'] ?? '') === '') {
                $out['intent_id'] = $intentId;
            }

            return $out;
        }

        if (!empty($out['success'])) {
            if (AssistantDraftNormalizer::scalarString($out['intent_id'] ?? '') === '') {
                $out['intent_id'] = $intentId;
            }
            if (AssistantDraftNormalizer::scalarString($out['flow_action_id'] ?? '') === '') {
                $out['flow_action_id'] = $intentId;
            }

            return $out;
        }

        $error = AssistantDraftNormalizer::scalarString($out['error'] ?? '');
        if ($error === '') {
            $error = AssistantChannelCopy::t('intent_not_allowed');
        }

        return [
            'kind' => 'message',
            'text' => $error,
            'success' => false,
            'error' => $error,
        ];
    }
}
