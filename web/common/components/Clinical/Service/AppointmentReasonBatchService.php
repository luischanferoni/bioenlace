<?php

namespace common\components\Clinical\Service;

use common\components\Clinical\AiContext\PatientAiContextBuilder;
use common\components\Ai\IAManager;
use common\components\Ai\SpeechToText\SpeechToTextManager;
use common\models\Clinical\Encounter;
use common\models\ConsultaMotivosMessage;
use Yii;

/**
 * Procesa en un solo lote todos los mensajes de motivos (texto, audio, imagen) y escribe {@see Encounter::$reason_text}.
 */
final class AppointmentReasonBatchService
{
    /**
     * @return array{ok: bool, message: string, reason_text?: string}
     */
    public static function process(int $encounterId, bool $force = false): array
    {
        $encounter = Encounter::findOne(['id' => $encounterId]);
        if (!$encounter) {
            return ['ok' => false, 'message' => 'Encounter no encontrado'];
        }

        if (!$force && !empty($encounter->motivos_ia_processed_at)) {
            return [
                'ok' => true,
                'message' => 'Ya procesado',
                'reason_text' => (string) $encounter->reason_text,
            ];
        }

        $messages = ConsultaMotivosMessage::find()
            ->where(['encounter_id' => $encounterId])
            ->orderBy(['created_at' => SORT_ASC])
            ->all();

        if ($messages === []) {
            return ['ok' => false, 'message' => 'Sin mensajes de motivos'];
        }

        $lines = self::buildTranscriptLines($messages);
        if ($lines === []) {
            return ['ok' => false, 'message' => 'No hay contenido utilizable'];
        }

        $transcript = implode("\n", $lines);
        $summary = self::summarizeWithIa($transcript, (int) $encounter->subject_persona_id);
        if ($summary === null || trim($summary) === '') {
            $summary = $transcript;
        }

        $encounter->reason_text = trim($summary);
        $encounter->motivos_ia_processed_at = date('Y-m-d H:i:s');
        if (!$encounter->save(false, ['reason_text', 'motivos_ia_processed_at'])) {
            return ['ok' => false, 'message' => 'No se pudo guardar el resumen en el encounter'];
        }

        Yii::info("Motivos IA batch OK encounter={$encounterId}", 'motivos-consulta');

        return [
            'ok' => true,
            'message' => 'Resumen generado',
            'reason_text' => $encounter->reason_text,
        ];
    }

    /**
     * @param ConsultaMotivosMessage[] $messages
     * @return list<string>
     */
    private static function buildTranscriptLines(array $messages): array
    {
        $webRoot = Yii::getAlias('@frontend/web');
        $lines = [];

        foreach ($messages as $msg) {
            $who = trim((string) $msg->user_name) !== '' ? $msg->user_name : 'Paciente';
            if ($msg->message_type === ConsultaMotivosMessage::TYPE_TEXTO) {
                $t = trim((string) $msg->texto);
                if ($t !== '') {
                    $lines[] = "{$who}: {$t}";
                }
                continue;
            }

            if ($msg->message_type === ConsultaMotivosMessage::TYPE_AUDIO) {
                $path = self::resolveLocalPath((string) $msg->texto, $webRoot);
                if ($path !== null && is_file($path)) {
                    $stt = SpeechToTextManager::transcribir($path, 'economico');
                    $texto = trim((string) ($stt['texto'] ?? ''));
                    if ($texto !== '') {
                        $lines[] = "{$who} (audio): {$texto}";
                        continue;
                    }
                }
                $lines[] = "{$who}: [audio sin transcripción]";
                continue;
            }

            if ($msg->message_type === ConsultaMotivosMessage::TYPE_IMAGEN) {
                $path = self::resolveLocalPath((string) $msg->texto, $webRoot);
                $label = $path !== null ? basename($path) : 'imagen';
                $lines[] = "{$who}: [imagen adjunta: {$label}]";
            }
        }

        return $lines;
    }

    private static function resolveLocalPath(string $stored, string $webRoot): ?string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return null;
        }
        if (strpos($stored, 'http') === 0) {
            $path = parse_url($stored, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                return null;
            }
            $stored = ltrim($path, '/');
        }

        $full = $webRoot . '/' . ltrim($stored, '/');

        return is_file($full) ? $full : null;
    }

    private static function summarizeWithIa(string $transcript, int $subjectPersonaId): ?string
    {
        $patientBlock = '';
        if ($subjectPersonaId > 0) {
            $patientBlock = (new PatientAiContextBuilder())->build(
                $subjectPersonaId,
                PatientAiContextBuilder::PROFILE_MOTIVOS
            );
        }

        $prompt = <<<PROMPT
Sos un asistente clínico. El paciente cargó motivos de consulta antes del turno (texto, audios transcritos e imágenes referenciadas).
PROMPT;

        if ($patientBlock !== '') {
            $prompt .= "\n\n" . $patientBlock;
        }

        $prompt .= <<<PROMPT


Conversación cruda:
---
{$transcript}
---

Generá un resumen en español para el médico que abra la consulta:
- Motivo(s) de consulta en prosa clara (2–8 oraciones).
- Síntomas, duración y datos relevantes si aparecen.
- Sin diagnósticos ni recomendaciones de tratamiento.
- Solo el resumen, sin encabezados markdown ni JSON.
PROMPT;

        $raw = IAManager::consultarIA($prompt, 'motivos-consulta-batch', 'analysis');

        return self::extractTextFromIaResult($raw);
    }

    /**
     * @param mixed $raw
     */
    private static function extractTextFromIaResult($raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        if (is_string($raw)) {
            return trim($raw);
        }
        if (!is_array($raw)) {
            return null;
        }
        foreach (['resumen', 'texto', 'text', 'content', 'respuesta', 'summary'] as $key) {
            if (!empty($raw[$key]) && is_string($raw[$key])) {
                return trim($raw[$key]);
            }
        }
        if (isset($raw['datosExtraidos']) && is_array($raw['datosExtraidos'])) {
            foreach ($raw['datosExtraidos'] as $bloque) {
                if (is_array($bloque) && !empty($bloque['texto']) && is_string($bloque['texto'])) {
                    return trim($bloque['texto']);
                }
            }
        }

        return trim(json_encode($raw, JSON_UNESCAPED_UNICODE));
    }
}
