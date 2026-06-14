<?php

namespace common\components\Platform\Assistant\Chat;

use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;
use common\components\Platform\Assistant\Chat\Routing\ChatRouter;
use common\components\Platform\Assistant\Service\AssistantDraftNormalizer;
use common\components\Platform\Assistant\SubIntentEngine\FlowDraftHydratorService;
use common\components\Platform\Assistant\SubIntentEngine\SubIntentEngine;

/**
 * Punto de entrada único del chat (`POST /api/v1/asistente/enviar`).
 */
final class ChatOrchestrator
{
    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function handle(array $body, int $userId): array
    {
        $intentId = AssistantDraftNormalizer::scalarString($body['intent_id'] ?? '');

        if ($intentId !== '') {
            $content = AssistantDraftNormalizer::scalarString($body['content'] ?? '');
            if ($content !== '') {
                ChatPreprocessContext::set(ChatPreprocessService::run($content, $userId));
            }

            try {
                FlowDraftHydratorService::hydrateFromIntentManifest($intentId, $body);
            } catch (\yii\web\ForbiddenHttpException $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            } catch (\InvalidArgumentException $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            } catch (\RuntimeException $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }

            $motor = SubIntentEngine::process($body, $userId);

            return self::finalizeMotor($motor);
        }

        $motor = ChatRouter::routeRootQuery($body, $userId);

        return self::finalizeMotor($motor);
    }

    /**
     * Texto para persistir la respuesta del bot en BD.
     *
     * @param array<string, mixed> $envelope
     */
    public static function botReplyTextForPersistence(array $envelope): string
    {
        $text = AssistantDraftNormalizer::scalarString($envelope['text'] ?? '');
        if ($text !== '') {
            return $text;
        }

        return 'Consulta procesada';
    }

    /**
     * @param array<string, mixed> $motor
     * @return array<string, mixed>
     */
    private static function finalizeMotor(array $motor): array
    {
        if (AssistantEnvelope::isPublicEnvelope($motor)) {
            return $motor;
        }

        if (empty($motor['success'])) {
            return $motor;
        }

        return AssistantEnvelope::fromMotorResponse($motor);
    }
}
