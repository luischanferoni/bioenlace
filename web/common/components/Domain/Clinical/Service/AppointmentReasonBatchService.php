<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Clinical\AiContext\PatientAiContextBuilder;
use common\components\Ai\IAManager;
use common\components\Platform\Ai\SpeechToText\SpeechToTextManager;
use common\components\Domain\Clinical\Service\SecureMediaService;
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
            $stored = trim((string) $encounter->reason_text);
            if ($stored !== '' && !self::isLowQualitySummary($stored)) {
                return [
                    'ok' => true,
                    'message' => 'Ya procesado',
                    'reason_text' => $stored,
                ];
            }
        }

        $messages = ConsultaMotivosMessage::find()
            ->where(['encounter_id' => $encounterId])
            ->orderBy(['created_at' => SORT_ASC])
            ->all();

        if ($messages === []) {
            return ['ok' => false, 'message' => 'Sin mensajes de motivos'];
        }

        $input = self::buildMotivosInput($messages, $encounterId);
        if ($input['transcript'] === '' && $input['imagenes'] === []) {
            return ['ok' => false, 'message' => 'No hay contenido utilizable'];
        }

        $summary = self::summarizeWithIa($input, (int) $encounter->subject_persona_id);
        if ($summary === null || self::isLowQualitySummary($summary)) {
            $summary = self::buildFallbackProseSummary($input);
        }

        $encounter->reason_text = trim($summary);
        $encounter->motivos_ia_processed_at = date('Y-m-d H:i:s');
        $encounter->motivos_ia_insights_json = null;
        if (!$encounter->save(false, ['reason_text', 'motivos_ia_processed_at', 'motivos_ia_insights_json'])) {
            return ['ok' => false, 'message' => 'No se pudo guardar el resumen en el encounter'];
        }

        AppointmentReasonClinicalInsightsService::generateAndPersist($encounterId, $encounter->reason_text);

        Yii::info("Motivos IA batch OK encounter={$encounterId}", 'motivos-consulta');

        return [
            'ok' => true,
            'message' => 'Resumen generado',
            'reason_text' => $encounter->reason_text,
        ];
    }

    /**
     * Resumen + sugerencias si la ventana del paciente ya cerró y hay mensajes sin procesar.
     */
    public static function ensureProcessedForMedico(Encounter $encounter): void
    {
        if (AppointmentReasonWindowService::isInputOpenForEncounter($encounter)) {
            return;
        }

        $hasMessages = ConsultaMotivosMessage::find()
            ->where(['encounter_id' => (int) $encounter->id])
            ->exists();
        if (!$hasMessages) {
            return;
        }

        $stored = trim((string) $encounter->reason_text);
        $needsBatch = empty($encounter->motivos_ia_processed_at)
            || $stored === ''
            || self::isLowQualitySummary($stored);

        if ($needsBatch) {
            self::process((int) $encounter->id, true);
            $encounter->refresh();

            return;
        }

        if ($stored !== '' && empty($encounter->motivos_ia_insights_json)) {
            AppointmentReasonClinicalInsightsService::generateAndPersist(
                (int) $encounter->id,
                $stored
            );
            $encounter->refresh();
        }
    }

    /**
     * Imágenes del chat de motivos para enlazar placeholders [imagenN] en la UI.
     *
     * @param ConsultaMotivosMessage[] $messages
     * @return list<array{ref: string, url: string}>
     */
    public static function imagenesAdjuntasFromMessages(array $messages, int $encounterId): array
    {
        $input = self::buildMotivosInput($messages, $encounterId);

        return $input['imagenes'];
    }

    /**
     * @param ConsultaMotivosMessage[] $messages
     * @return array{
     *   transcript: string,
     *   textos: list<string>,
     *   imagenes: list<array{ref: string, url: string}>
     * }
     */
    private static function buildMotivosInput(array $messages, int $encounterId): array
    {
        $webRoot = Yii::getAlias('@frontend/web');
        $lines = [];
        $textos = [];
        $imagenes = [];
        $imgIndex = 0;
        /** @var list<string> $audioPaths */
        $audioPaths = [];
        $audioPlaceholderIndex = null;

        foreach ($messages as $msg) {
            if ($msg->message_type === ConsultaMotivosMessage::TYPE_TEXTO) {
                // Texto escrito o transcript on-device ya persistido como mensaje de texto.
                $t = trim((string) $msg->texto);
                if ($t !== '') {
                    $textos[] = $t;
                    $lines[] = $t;
                }
                continue;
            }

            if ($msg->message_type === ConsultaMotivosMessage::TYPE_AUDIO) {
                $path = self::resolveLocalPath((string) $msg->texto, $webRoot);
                if ($path !== null && is_file($path)) {
                    if ($audioPlaceholderIndex === null) {
                        $audioPlaceholderIndex = count($lines);
                        $lines[] = '';
                    }
                    $audioPaths[] = $path;
                } else {
                    $lines[] = '(audio sin transcripción)';
                }
                continue;
            }

            if ($msg->message_type === ConsultaMotivosMessage::TYPE_IMAGEN) {
                $imgIndex++;
                $ref = 'imagen' . $imgIndex;
                $url = self::resolveImagenUrl((string) $msg->texto, $encounterId);
                $imagenes[] = ['ref' => $ref, 'url' => $url];
                $lines[] = "[{$ref}]";
            }
        }

        if ($audioPaths !== [] && $audioPlaceholderIndex !== null) {
            // Una sola llamada STT (Groq) con todos los audios del hilo concatenados.
            $stt = SpeechToTextManager::transcribirLote($audioPaths, 'economico');
            $texto = trim((string) ($stt['texto'] ?? ''));
            if ($texto !== '') {
                $textos[] = $texto;
                $lines[$audioPlaceholderIndex] = '(audio transcrito) ' . $texto;
            } else {
                $lines[$audioPlaceholderIndex] = '(audio sin transcripción)';
            }
        }

        return [
            'transcript' => implode("\n", array_values(array_filter(
                $lines,
                static fn ($line): bool => $line !== null && $line !== ''
            ))),
            'textos' => $textos,
            'imagenes' => $imagenes,
        ];
    }

    /**
     * @param array{
     *   transcript: string,
     *   textos: list<string>,
     *   imagenes: list<array{ref: string, url: string}>
     * } $input
     */
    private static function buildFallbackProseSummary(array $input): string
    {
        $partes = [];
        if ($input['textos'] !== []) {
            $unido = implode(' ', $input['textos']);
            $partes[] = 'El paciente reporta: ' . self::sentenceCase($unido) . '.';
        }

        if ($input['imagenes'] !== []) {
            $refs = array_map(static fn (array $img): string => '[' . $img['ref'] . ']', $input['imagenes']);
            $partes[] = 'Adjunta las siguientes imágenes: ' . implode(' ', $refs);
        }

        if ($partes === []) {
            return 'Sin motivos de consulta en texto.';
        }

        return implode("\n\n", $partes);
    }

    /**
     * @param array{
     *   transcript: string,
     *   textos: list<string>,
     *   imagenes: list<array{ref: string, url: string}>
     * } $input
     */
    private static function summarizeWithIa(array $input, int $subjectPersonaId): ?string
    {
        $patientBlock = '';
        if ($subjectPersonaId > 0) {
            $patientBlock = (new PatientAiContextBuilder())->build(
                $subjectPersonaId,
                PatientAiContextBuilder::PROFILE_MOTIVOS
            );
        }

        $imagenBlock = '';
        if ($input['imagenes'] !== []) {
            $imagenBlock = "Imágenes adjuntas (usá solo estos marcadores en el resumen, sin nombres de archivo):\n";
            foreach ($input['imagenes'] as $img) {
                $imagenBlock .= '- [' . $img['ref'] . "]\n";
            }
        }

        $prompt = <<<PROMPT
Sos un asistente clínico. El paciente cargó motivos de consulta antes del turno.
PROMPT;

        if ($patientBlock !== '') {
            $prompt .= "\n\n" . $patientBlock;
        }

        if ($imagenBlock !== '') {
            $prompt .= "\n\n" . $imagenBlock;
        }

        $prompt .= <<<PROMPT


Mensajes del paciente:
---
{$input['transcript']}
---

Redactá un resumen breve en español para el médico:
1) Un párrafo en prosa clara con el motivo y síntomas ("El paciente observa…", "Refiere…"). No copies el formato "usuario: mensaje".
2) Si hay imágenes, una segunda línea exacta: "Adjunta las siguientes imágenes: [imagen1] [imagen2]…" usando solo los marcadores indicados.
Sin diagnósticos ni tratamientos. Sin markdown ni JSON.
PROMPT;

        $raw = IAManager::consultarIA($prompt, 'motivos-consulta-batch', 'analysis');
        $text = self::extractTextFromIaResult($raw);

        return $text !== null && !self::isLowQualitySummary($text) ? $text : null;
    }

    public static function isLowQualitySummary(string $text): bool
    {
        $text = trim($text);
        if ($text === '') {
            return true;
        }
        if (preg_match('/\[imagen adjunta:/i', $text) === 1) {
            return true;
        }
        if (preg_match('/^[\w.-]+:\s/m', $text) === 1 && substr_count($text, "\n") >= 1) {
            return true;
        }
        if (preg_match('/^paciente_/i', $text) === 1 && str_contains($text, ':')) {
            return true;
        }

        return false;
    }

    private static function sentenceCase(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }

    private static function resolveImagenUrl(string $stored, int $encounterId): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return '';
        }
        if (strpos($stored, 'http') === 0) {
            return $stored;
        }

        $filename = SecureMediaService::filenameFromStored($stored);
        if ($filename === '') {
            return $stored;
        }

        return SecureMediaService::absoluteApiUrl(
            SecureMediaService::SCOPE_MOTIVOS_CONSULTA,
            $encounterId,
            $filename
        );
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
            if (preg_match('#/media/motivos-consulta/\d+/(.+)$#', $stored, $m) === 1) {
                $stored = 'uploads/motivos_consulta/' . $m[1];
            }
        }

        $full = $webRoot . '/' . ltrim($stored, '/');

        return is_file($full) ? $full : null;
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
            $t = trim($raw);
            if ($t !== '' && !self::looksLikeJsonBlob($t)) {
                return $t;
            }
            $decoded = json_decode($t, true);

            return is_array($decoded) ? self::extractTextFromIaResult($decoded) : null;
        }
        if (!is_array($raw)) {
            return null;
        }
        foreach (['resumen', 'texto', 'text', 'content', 'respuesta', 'summary', 'generated_text', 'output'] as $key) {
            if (!empty($raw[$key]) && is_string($raw[$key])) {
                $candidate = trim($raw[$key]);
                if ($candidate !== '' && !self::looksLikeJsonBlob($candidate)) {
                    return $candidate;
                }
            }
        }
        if (isset($raw[0]) && is_string($raw[0])) {
            return trim($raw[0]);
        }
        if (isset($raw['datosExtraidos']) && is_array($raw['datosExtraidos'])) {
            foreach ($raw['datosExtraidos'] as $bloque) {
                if (is_array($bloque) && !empty($bloque['texto']) && is_string($bloque['texto'])) {
                    return trim($bloque['texto']);
                }
            }
        }

        return null;
    }

    private static function looksLikeJsonBlob(string $text): bool
    {
        $t = ltrim($text);

        return $t !== '' && ($t[0] === '{' || $t[0] === '[');
    }
}
