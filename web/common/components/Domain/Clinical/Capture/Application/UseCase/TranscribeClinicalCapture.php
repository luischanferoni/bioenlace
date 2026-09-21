<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\Checkpoint\ClinicalCaptureCheckpoint;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureStage;
use common\components\Domain\Clinical\Capture\Infrastructure\SpeechToText\ClinicalSpeechInputResolver;
use common\components\Platform\Ai\SpeechToText\SpeechToTextManager;
use common\components\Platform\Ai\SpeechToText\SttConfigService;
use common\models\Clinical\EncounterCaptureAudit;

/** Caso de uso: STT servidor sobre audio ya subido. */
final class TranscribeClinicalCapture
{
    private ClinicalCaptureCheckpoint $checkpoint;

    public function __construct(?ClinicalCaptureCheckpoint $checkpoint = null)
    {
        $this->checkpoint = $checkpoint ?? new ClinicalCaptureCheckpoint();
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function execute(array $body): array
    {

        $capture = $this->checkpoint->findOpenCapture($body);
        if (is_array($capture)) {
            return $capture;
        }

        $domain = $this->checkpoint->captureRows()->toAggregate($capture);

        if ($domain->hasTranscript()
            && in_array($domain->stage(), [
                ClinicalCaptureStage::TRANSCRIBED,
                ClinicalCaptureStage::ANALYSIS_FAILED,
                ClinicalCaptureStage::READY_FOR_REVIEW,
                ClinicalCaptureStage::SAVE_FAILED,
            ], true)
            && empty($body['force'])
        ) {
            return $this->checkpoint->ok($capture, 'Ya hay transcripción; no se reejecutó STT.');
        }

        if (!$domain->hasAudio()) {
            return $this->checkpoint->fail(400, 'La captura no tiene audio en servidor para transcribir.', $capture);
        }

        $absolute = $this->checkpoint->absoluteAudioPath($capture);
        if ($absolute === null || !is_file($absolute)) {
            $domain->markSttFailed('Archivo de audio no encontrado en servidor.');
            $capture = $this->checkpoint->persistDomain($domain);
            $this->checkpoint->audit()->record($capture, EncounterCaptureAudit::EVENT_STT_FAILED, [
                'error_code' => 'audio_missing',
                'attempts_stt' => $domain->attemptsStt(),
            ]);

            return $this->checkpoint->fail(404, $capture->last_error, $capture);
        }

        if (!SttConfigService::isServerEnabled()) {
            $domain->markSttFailed('La transcripción en servidor está deshabilitada.');
            $capture = $this->checkpoint->persistDomain($domain);
            $this->checkpoint->audit()->record($capture, EncounterCaptureAudit::EVENT_STT_FAILED, [
                'error_code' => 'server_stt_disabled',
                'attempts_stt' => $domain->attemptsStt(),
            ]);

            return $this->checkpoint->fail(400, $capture->last_error, $capture);
        }

        $modelo = (string) ($body['modelo'] ?? 'economico');
        $result = SpeechToTextManager::transcribir($absolute, $modelo);
        $texto = trim((string) ($result['texto'] ?? ''));

        if ($texto === '') {
            $err = trim((string) ($result['error'] ?? 'No se pudo transcribir el audio.'));
            $domain->markSttFailed($err !== '' ? $err : 'No se pudo transcribir el audio.');
            $capture = $this->checkpoint->persistDomain($domain);
            $this->checkpoint->audit()->record($capture, EncounterCaptureAudit::EVENT_STT_FAILED, [
                'error_code' => 'empty_transcript',
                'attempts_stt' => $domain->attemptsStt(),
                'modelo' => $modelo,
            ]);

            return $this->checkpoint->fail(502, $capture->last_error, $capture);
        }

        $meta = $domain->sttMeta();
        $meta['provenance'] = ClinicalSpeechInputResolver::PROVENANCE_SERVER;
        $meta['server_stt'] = [
            'confidence' => $result['confidence'] ?? null,
            'modelo_usado' => $result['modelo_usado'] ?? null,
        ];
        $domain->markTranscribed($texto, $meta);
        $capture = $this->checkpoint->persistDomain($domain);
        $this->checkpoint->audit()->record($capture, EncounterCaptureAudit::EVENT_STT_OK, [
            'attempts_stt' => $domain->attemptsStt(),
            'provenance' => ClinicalSpeechInputResolver::PROVENANCE_SERVER,
            'modelo_usado' => $result['modelo_usado'] ?? null,
            'transcript_length' => mb_strlen($texto),
        ]);

        return $this->checkpoint->ok($capture, 'Transcripción lista.');
    }
}
