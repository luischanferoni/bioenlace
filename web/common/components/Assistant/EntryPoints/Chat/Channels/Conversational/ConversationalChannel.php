<?php

namespace common\components\Assistant\EntryPoints\Chat\Channels\Conversational;

use common\components\Ai\IAManager;
use common\components\Assistant\EntryPoints\Chat\Envelope\AssistantEnvelope;
use Yii;

/**
 * Canal conversacional: respuesta libre (2ª IA).
 */
final class ConversationalChannel
{
    /**
     * @return array<string, mixed>
     */
    public static function handle(string $content, int $userId): array
    {
        unset($userId);
        $content = trim($content);
        if ($content === '') {
            return AssistantEnvelope::message('');
        }

        $prompt = <<<PROMPT
Sos el asistente de una aplicación de salud. Respondé en español, breve y amable.
No inventes datos clínicos ni confirmes turnos; si piden una acción operativa, sugerí que lo formulen como pedido concreto (turno, cancelar, etc.).

Usuario:
{$content}
PROMPT;

        try {
            $raw = IAManager::consultarIA($prompt, 'asistente-conversational', 'text-generation');
            if (is_string($raw) && trim($raw) !== '') {
                return AssistantEnvelope::message(trim($raw));
            }
            if (is_array($raw) && isset($raw['text'])) {
                return AssistantEnvelope::message(trim((string) $raw['text']));
            }
        } catch (\Throwable $e) {
            Yii::warning('ConversationalChannel: ' . $e->getMessage(), 'asistente');
        }

        return AssistantEnvelope::message('Hola. ¿En qué puedo ayudarte con turnos o trámites en la app?');
    }
}
