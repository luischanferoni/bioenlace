<?php

namespace common\components\Assistant\EntryPoints\Chat;

use common\components\Assistant\EntryPoints\Chat\ChatPreprocessContext;
use common\components\Assistant\EntryPoints\Chat\Envelope\AssistantEnvelope;
use common\components\Assistant\EntryPoints\Chat\Preprocess\ChatPreprocessService;
use common\components\Assistant\EntryPoints\Chat\Routing\ChatRouter;
use common\components\Assistant\SubIntentEngine\SubIntentEngine;
use common\components\Organization\Service\ProfesionalEfectorServicio\ProfesionalEfectorServicioAgendaFlowDraftHydrator;
use common\components\Organization\Service\ProfesionalEfectorServicio\ProfesionalEfectorServicioCrearFlowDraftHydrator;

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
        $intentId = isset($body['intent_id']) ? trim((string) $body['intent_id']) : '';

        if ($intentId !== '') {
            $content = isset($body['content']) ? trim((string) $body['content']) : '';
            if ($content !== '') {
                ChatPreprocessContext::set(ChatPreprocessService::run($content, $userId));
            }

            if ($intentId === 'agenda.crear-profesional-flow') {
                try {
                    ProfesionalEfectorServicioCrearFlowDraftHydrator::hydrate($body);
                } catch (\InvalidArgumentException $e) {
                    return ['success' => false, 'error' => $e->getMessage()];
                } catch (\RuntimeException $e) {
                    return ['success' => false, 'error' => $e->getMessage()];
                }
            } elseif ($intentId === 'agenda.editar-agenda-flow' || $intentId === 'agenda.editar-mi-agenda-flow') {
                try {
                    ProfesionalEfectorServicioAgendaFlowDraftHydrator::hydrate(
                        $body,
                        $intentId === 'agenda.editar-mi-agenda-flow'
                    );
                } catch (\yii\web\ForbiddenHttpException $e) {
                    return ['success' => false, 'error' => $e->getMessage()];
                }
            } elseif ($intentId === 'licencia.cargar-como-profesional-flow' || $intentId === 'licencia.cargar-para-profesional-flow') {
                try {
                    ProfesionalEfectorServicioAgendaFlowDraftHydrator::hydrate(
                        $body,
                        $intentId === 'licencia.cargar-como-profesional-flow'
                    );
                } catch (\yii\web\ForbiddenHttpException $e) {
                    return ['success' => false, 'error' => $e->getMessage()];
                }
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
        $text = isset($envelope['text']) ? trim((string) $envelope['text']) : '';
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
