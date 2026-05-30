<?php

namespace common\components\Assistant\EntryPoints\Chat\Channels\Conversational;

use common\components\Ai\IAManager;
use common\components\Assistant\EntryPoints\Chat\Envelope\AssistantEnvelope;
use common\components\Assistant\EntryPoints\Chat\Preprocess\ChatPreprocessService;
use common\components\Assistant\IntentEngine\UiActionCatalog;
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
Sos el asistente de Bioenlace, una aplicación de salud donde las personas reservan turnos con profesionales y centros de la red.

Respondé en español, breve y amable (3–5 oraciones salvo que pidan más detalle).
No inventes datos clínicos, diagnósticos ni confirmes turnos ya hechos.

Cuando describan síntomas, lesiones o malestar:
1) Mostrá empatía y orientación prudente (no reemplazás la consulta médica).
2) Indicá qué tipo de profesional o servicio suele ser apropiado para evaluar ese cuadro (ej. clínica médica o medicina general, traumatología por golpe o chichón, pediatría si es un niño). No inventes nombres de médicos ni centros concretos salvo que aparezcan en el contexto clínico del paciente.
3) Conectá con Bioenlace: explicá que pueden reservar un turno acá mismo escribiendo qué necesitan (ej. "Quiero turno con clínica médica" o "Sacar turno de traumatología").
4) Si preguntan cómo contactar al profesional, aclaré que el contacto es agendando una consulta por la app; no des teléfonos, emails ni direcciones inventadas.

Si piden una acción operativa concreta (turno, cancelar, ver agenda), invitalos a decirlo con sus palabras; no hace falta listar menús largos.

Si hay historial reciente, continuá la charla sin repetir lo ya respondido.

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

        $text = null;
        try {
            $raw = IAManager::consultarIA($prompt, 'asistente-conversational', 'text-generation');
            if (is_string($raw) && trim($raw) !== '') {
                $text = trim($raw);
            } elseif (is_array($raw) && isset($raw['text'])) {
                $text = trim((string) $raw['text']);
            }
        } catch (\Throwable $e) {
            Yii::warning('ConversationalChannel: ' . $e->getMessage(), 'asistente');
        }

        if ($text === null || $text === '') {
            $text = 'Entiendo tu consulta. Te recomiendo que un profesional te evalúe en persona. '
                . 'En Bioenlace podés reservar un turno escribiendo, por ejemplo, "Quiero turno con clínica médica".';
        }

        return self::finalizeResponse($content, $userId, $text);
    }

    /**
     * @return array<string, mixed>
     */
    private static function finalizeResponse(string $content, int $userId, string $text): array
    {
        if (!ChatPreprocessService::isClinicalSymptomContent($content)) {
            return AssistantEnvelope::message($text);
        }

        $button = self::resolveBookingButton($userId);
        if ($button === null) {
            return AssistantEnvelope::message($text);
        }

        return AssistantEnvelope::interactive($text, [$button]);
    }

    /**
     * @return array{label: string, intent_id: string}|null
     */
    private static function resolveBookingButton(int $userId): ?array
    {
        $catalog = UiActionCatalog::forUser($userId);
        foreach (['turnos.crear-como-paciente', 'turnos.crear-para-paciente'] as $intentId) {
            $item = $catalog->byActionId[$intentId] ?? null;
            if ($item === null) {
                continue;
            }

            return [
                'label' => $item->display_name !== '' ? $item->display_name : 'Reservar turno',
                'intent_id' => $intentId,
            ];
        }

        foreach ($catalog->items as $item) {
            if (strpos($item->action_id, 'turnos.crear') === 0) {
                return [
                    'label' => $item->display_name !== '' ? $item->display_name : $item->action_id,
                    'intent_id' => $item->action_id,
                ];
            }
        }

        return null;
    }
}
