<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\Service\CaptureDraftService;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureStage;
use common\components\Domain\Clinical\Capture\Infrastructure\SpeechToText\CaptureSpeechInputResolver;
use common\components\Platform\Ai\SpeechToText\SpeechToTextManager;
use common\components\Platform\Ai\SpeechToText\SttConfigService;
use common\models\Clinical\EncounterCaptureAudit;

/** Caso de uso: STT servidor sobre audio ya subido. */
final class TranscribeCapture
{
    private CaptureDraftService $draft;

    public function __construct(?CaptureDraftService $draft = null)
    {
        $this->draft = $draft ?? new CaptureDraftService();
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function execute(array $body): array
    {

        $capture = $this->draft->findOpenCapture($body);

        $domain = $this->draft->captureRows()->toAggregate($capture);

        if ($domain->hasTranscript()
            && in_array($domain->stage(), [
                ClinicalCaptureStage::TRANSCRIBED,
                ClinicalCaptureStage::ANALYSIS_FAILED,
                ClinicalCaptureStage::READY_FOR_REVIEW,
                ClinicalCaptureStage::SAVE_FAILED,
            ], true)
            && empty($body['force'])
        ) {
            return $this->draft->ok($capture);
        }

        if (!$domain->hasAudio()) {
            throw $this->draft->failure('capture_no_audio', $capture);
        }

        $absolute = $this->draft->absoluteAudioPath($capture);
        if ($absolute === null || !is_file($absolute)) {
            $domain->markSttFailed($this->draft->errorMessage('audio_file_not_found'));
            $capture = $this->draft->persistDomain($domain);
            $this->draft->audit()->record($capture, EncounterCaptureAudit::EVENT_STT_FAILED, [
                'error_code' => 'audio_missing',
                'attempts_stt' => $domain->attemptsStt(),
            ]);

            throw $this->draft->failure('audio_file_not_found', $capture);
        }

        if (!SttConfigService::isServerEnabled()) {
            $domain->markSttFailed($this->draft->errorMessage('stt_server_disabled'));
            $capture = $this->draft->persistDomain($domain);
            $this->draft->audit()->record($capture, EncounterCaptureAudit::EVENT_STT_FAILED, [
                'error_code' => 'server_stt_disabled',
                'attempts_stt' => $domain->attemptsStt(),
            ]);

            throw $this->draft->failure('stt_server_disabled', $capture);
        }

        $modelo = (string) ($body['modelo'] ?? 'economico');
        $result = SpeechToTextManager::transcribir($absolute, $modelo);
        $texto = trim((string) ($result['texto'] ?? ''));

        if ($texto === '') {
            $domain->markSttFailed($this->draft->errorMessage('stt_failed'));
            $capture = $this->draft->persistDomain($domain);
            $this->draft->audit()->record($capture, EncounterCaptureAudit::EVENT_STT_FAILED, [
                'error_code' => 'empty_transcript',
                'attempts_stt' => $domain->attemptsStt(),
                'modelo' => $modelo,
            ]);

            throw $this->draft->failure('stt_failed', $capture);
        }

        $meta = $domain->sttMeta();
        $meta['provenance'] = CaptureSpeechInputResolver::PROVENANCE_SERVER;
        $meta['server_stt'] = [
            'confidence' => $result['confidence'] ?? null,
            'modelo_usado' => $result['modelo_usado'] ?? null,
        ];
        $domain->markTranscribed($texto, $meta);
        $capture = $this->draft->persistDomain($domain);
        $this->draft->audit()->record($capture, EncounterCaptureAudit::EVENT_STT_OK, [
            'attempts_stt' => $domain->attemptsStt(),
            'provenance' => CaptureSpeechInputResolver::PROVENANCE_SERVER,
            'modelo_usado' => $result['modelo_usado'] ?? null,
            'transcript_length' => mb_strlen($texto),
        ]);

        return $this->draft->ok($capture);
    }
}
