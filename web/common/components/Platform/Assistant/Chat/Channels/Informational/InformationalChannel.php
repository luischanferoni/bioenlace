<?php

namespace common\components\Platform\Assistant\Chat\Channels\Informational;

use common\components\Platform\Assistant\Chat\Channels\Conversational\ConversationalChannel;
use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;
use common\components\Platform\Assistant\IntentEngine\IntentClassificationRulesService;
use common\components\Platform\Assistant\IntentEngine\IntentEngine;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;

/**
 * Canal informativo / meta: listar capacidades o mensaje guía.
 */
final class InformationalChannel
{
    /**
     * @return array<string, mixed>
     */
    public static function handle(string $content, int $userId): array
    {
        if (IntentEngine::isListAllQueryPublic($content)) {
            return self::finalize(IntentEngine::processQuery($content, $userId, null));
        }

        if (!self::isCapabilityMenuQuery($content)) {
            return ConversationalChannel::handle($content, $userId);
        }

        if (IntentClassificationRulesService::isClinicalSymptomContent($content)) {
            return ConversationalChannel::handle($content, $userId);
        }

        $catalog = UiActionCatalog::forUser($userId);
        $buttons = [];
        foreach (array_slice($catalog->items, 0, 8) as $it) {
            $buttons[] = [
                'label' => $it->display_name !== '' ? $it->display_name : $it->action_id,
                'intent_id' => $it->action_id,
            ];
        }

        return AssistantEnvelope::interactive(
            'Estas son algunas cosas que podés hacer. Elegí una opción o contame qué necesitás.',
            $buttons
        );
    }

    /**
     * Pregunta explícita por capacidades/menú (no síntomas ni charla clínica).
     */
    public static function isCapabilityMenuQuery(string $content): bool
    {
        return IntentClassificationRulesService::isCapabilityMenuQuery($content);
    }

    /**
     * @param array<string, mixed> $motor
     * @return array<string, mixed>
     */
    private static function finalize(array $motor): array
    {
        if (empty($motor['success'])) {
            return $motor;
        }

        return AssistantEnvelope::fromMotorResponse($motor);
    }
}
