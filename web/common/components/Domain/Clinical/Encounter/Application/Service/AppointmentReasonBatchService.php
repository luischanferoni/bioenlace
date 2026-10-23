<?php

/**
 * Procesa en un solo lote todos los mensajes de motivos (texto, audio, imagen)
 * y persiste resumen como Condition rol CC ({@see RecordEncounterReason}).
 */

namespace common\components\Domain\Clinical\Encounter\Application\Service;

use common\components\Domain\Clinical\Encounter\Application\Service\PatientAiContextService;
use common\components\Platform\Ai\Prompt\PromptRegistry;
use common\components\Platform\Ai\Prompt\YamlPrompt;
use common\components\Platform\Ai\IAManager;
use common\components\Platform\Ai\SpeechToText\SpeechToTextManager;
use common\components\Platform\Ai\SpeechToText\SttConfigService;
use common\components\Domain\Clinical\Encounter\Application\Service\SecureMediaService;
use common\models\Clinical\Encounter;
use common\models\Clinical\EncounterReasonMessage;
use Yii;

final class AppointmentReasonBatchService
{
    private const SUMMARY_PROMPT = 'clinical.appointment_reason_summary';

    /**
     * @return array{ok: bool, message: string, summary?: string}
     */
    public static function process(int $encounterId, bool $force = false): array
    {
        $encounter = Encounter::findOne(['id' => $encounterId]);
        if (!$encounter) {
            return ['ok' => false, 'message' => 'Encounter no encontrado'];
        }

        $reasonSvc = new RecordEncounterReason();
        if (!$force && !empty($encounter->motivos_ia_processed_at)) {
            $stored = $reasonSvc->displayText($encounter);
            if ($stored !== '' && !self::isLowQualitySummary($stored)) {
                return [
                    'ok' => true,
                    'message' => 'Ya procesado',
                    'summary' => $stored,
                ];
            }
        }

        $messages = EncounterReasonMessage::find()
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

        $summary = trim($summary);
        $reasonSvc->replaceReasons($encounter, [$summary]);
        $encounter->motivos_ia_processed_at = date('Y-m-d H:i:s');
        $encounter->ia_clinical_suggestions = null;
        if (!$encounter->save(false, ['motivos_ia_processed_at', 'ia_clinical_suggestions'])) {
            return ['ok' => false, 'message' => 'No se pudo marcar el procesamiento IA del encounter'];
        }

        AppointmentReasonClinicalInsightsService::generateAndPersist($encounterId, $summary);

        Yii::info("Motivos IA batch OK encounter={$encounterId}", 'encounter-reasons');

        return [
            'ok' => true,
            'message' => 'Resumen generado',
            'summary' => $summary,
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

        $hasMessages = EncounterReasonMessage::find()
            ->where(['encounter_id' => (int) $encounter->id])
            ->exists();
        if (!$hasMessages) {
            return;
        }

        $reasonSvc = new RecordEncounterReason();
        $stored = $reasonSvc->displayText($encounter);
        $needsBatch = empty($encounter->motivos_ia_processed_at)
            || $stored === ''
            || self::isLowQualitySummary($stored);

        if ($needsBatch) {
            self::process((int) $encounter->id, true);
            $encounter->refresh();

            return;
        }

        if ($stored !== '' && empty($encounter->ia_clinical_suggestions)) {
            AppointmentReasonClinicalInsightsService::generateAndPersist(
                (int) $encounter->id,
                $stored
            );
            $encounter->refresh();
        }
    }

    /**
     * @param EncounterReasonMessage[] $messages
     * @return list<array{ref: string, url: string}>
     */
    public static function imagenesAdjuntasFromMessages(array $messages, int $encounterId): array
    {
        $input = self::buildMotivosInput($messages, $encounterId);

        return $input['imagenes'];
    }

    /**
     * @param EncounterReasonMessage[] $messages
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
            if ($msg->message_type === EncounterReasonMessage::TYPE_TEXTO) {
                $t = trim((string) $msg->texto);
                if ($t !== '') {
                    $textos[] = $t;
                    $lines[] = $t;
                }
                continue;
            }

            if ($msg->message_type === EncounterReasonMessage::TYPE_AUDIO) {
                $deviceTranscript = trim((string) $msg->transcript);
                if ($deviceTranscript !== '' && self::deviceTranscriptAccepted($msg)) {
                    $textos[] = $deviceTranscript;
                    $lines[] = '(audio transcripto) ' . $deviceTranscript;
                    continue;
                }

                $path = self::resolveLocalPath((string) $msg->texto, $webRoot);
                if ($path !== null && is_file($path) && SttConfigService::isServerEnabled()) {
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

            if ($msg->message_type === EncounterReasonMessage::TYPE_IMAGEN) {
                $imgIndex++;
                $ref = 'imagen' . $imgIndex;
                $url = self::resolveImagenUrl((string) $msg->texto, $encounterId);
                $imagenes[] = ['ref' => $ref, 'url' => $url];
                $lines[] = "[{$ref}]";
            }
        }

        if ($audioPaths !== [] && $audioPlaceholderIndex !== null) {
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

    private static function deviceTranscriptAccepted(EncounterReasonMessage $message): bool
    {
        $metadata = json_decode((string) $message->stt_meta_json, true);

        return is_array($metadata) && ($metadata['device_transcript_accepted'] ?? false) === true;
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
            $patientBlock = (new PatientAiContextService())->build(
                $subjectPersonaId,
                PatientAiContextService::PROFILE_MOTIVOS
            );
        }

        $imagenBlock = '';
        if ($input['imagenes'] !== []) {
            $imagenBlock = PromptRegistry::text(self::SUMMARY_PROMPT, 'image_header') . "\n";
            foreach ($input['imagenes'] as $img) {
                $imagenBlock .= '- [' . $img['ref'] . "]\n";
            }
        }

        $prompt = PromptRegistry::render(self::SUMMARY_PROMPT, [
            'patient_block' => $patientBlock !== '' ? "\n\n" . $patientBlock : '',
            'image_block' => $imagenBlock !== '' ? "\n\n" . $imagenBlock : '',
            'transcript' => (string) $input['transcript'],
        ]);

        $raw = IAManager::consultarIA($prompt, 'encounter-reasons-batch', 'analysis');
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
            SecureMediaService::SCOPE_ENCOUNTER_REASONS,
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
            if (preg_match('#/media/encounter-reasons/\d+/(.+)$#', $stored, $m) === 1) {
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
