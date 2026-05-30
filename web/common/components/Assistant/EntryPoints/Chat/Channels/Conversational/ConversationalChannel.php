<?php

namespace common\components\Assistant\EntryPoints\Chat\Channels\Conversational;

use common\components\Ai\IAManager;
use common\components\Assistant\EntryPoints\Chat\Envelope\AssistantEnvelope;
use common\components\Clinical\AiContext\PatientAiContextBuilder;
use Yii;

/**
 * Canal conversacional: preprocess + respuesta automática con ventana acotada de historial.
 */
final class ConversationalChannel
{
    /**
     * Instrucciones estables (context caching): sin historial ni mensaje actual.
     */
    public static function stablePromptPrefix(): string
    {
        return <<<'PROMPT'
Sos el asistente de una aplicación de salud. Respondé en español, breve y amable.
No inventes datos clínicos ni confirmes turnos.
Si el usuario describe síntomas o malestar sin pedir una acción del sistema, respondé con empatía y orientá a consultar con un profesional; no listes menús de turnos ni trámites.
Si piden una acción operativa (turno, cancelar, agenda), sugerí que lo formulen como pedido concreto.
Si hay historial reciente, continuá la charla sin repetir preguntas ya respondidas.

PROMPT;
    }

    public static function buildPrompt(string $content, int $userId): string
    {
        $content = trim($content);
        $parts = [rtrim(self::stablePromptPrefix())];

        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona > 0) {
            try {
                $patientBlock = (new PatientAiContextBuilder())->build(
                    $idPersona,
                    PatientAiContextBuilder::PROFILE_CONVERSATIONAL
                );
                if ($patientBlock !== '') {
                    $parts[] = '';
                    $parts[] = $patientBlock;
                }
            } catch (\Throwable $e) {
                Yii::warning('ConversationalChannel contexto paciente: ' . $e->getMessage(), 'asistente');
            }
        }

        $history = ConversationalHistoryWindow::formatForPrompt($userId, $content);
        if ($history !== '') {
            $parts[] = '';
            $parts[] = 'Historial reciente (del más antiguo al más reciente):';
            $parts[] = $history;
        }

        $parts[] = '';
        $parts[] = 'Mensaje actual del paciente:';
        $parts[] = $content;

        return implode("\n", $parts);
    }

    /**
     * @return array<string, mixed>
     */
    public static function handle(string $content, int $userId): array
    {
        $content = trim($content);
        if ($content === '') {
            return AssistantEnvelope::message('');
        }

        $prompt = self::buildPrompt($content, $userId);

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

        return AssistantEnvelope::message(
            'Entiendo tu consulta. Por ahora no puedo orientarte con detalle; te recomiendo consultar con un profesional de salud. '
            . 'Si querés sacar un turno, decime el servicio o la especialidad que necesitás.'
        );
    }
}
