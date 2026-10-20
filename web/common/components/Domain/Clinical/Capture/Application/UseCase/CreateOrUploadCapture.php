<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\Service\CaptureDraftService;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCapture;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureStage;
use common\components\Domain\Clinical\Capture\Infrastructure\SpeechToText\CaptureSpeechInputResolver;
use common\components\Platform\Ai\SpeechToText\DeviceSttQualityAssessor;
use common\components\Platform\Ai\SpeechToText\SttConfigService;
use common\models\Clinical\EncounterCaptureAudit;
use Yii;
use yii\web\UploadedFile;

/** Caso de uso: crear/actualizar captura + audio/transcript inicial. */
final class CreateOrUploadCapture
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
    public function execute(array $body, ?UploadedFile $file = null): array
    {

        $body = $this->draft->normalizeMultipartJsonFields($body);
        $clientId = trim((string) ($body['client_capture_id'] ?? $body['clientCaptureId'] ?? ''));
        if ($clientId === '') {
            throw $this->draft->failure('capture_client_id_required');
        }
        if (strlen($clientId) > 64) {
            throw $this->draft->failure('capture_client_id_too_long');
        }

        $subjectPersonaId = (int) ($body['id_persona'] ?? $body['subject_persona_id'] ?? 0);
        if ($subjectPersonaId <= 0) {
            throw $this->draft->failure('subject_required');
        }

        $userId = (int) (Yii::$app->user->id ?? 0);
        if ($userId <= 0) {
            throw $this->draft->failure('unauthenticated');
        }

        $parent = isset($body['parent']) ? trim((string) $body['parent']) : null;
        if ($parent === '') {
            $parent = null;
        }
        $parentId = isset($body['parent_id']) ? (int) $body['parent_id'] : null;
        if ($parentId !== null && $parentId <= 0) {
            $parentId = null;
        }

        $existing = $this->draft->captures()->findByClientCaptureId($clientId);
        if ($existing === null) {
            $domain = ClinicalCapture::start($clientId, $subjectPersonaId, $userId, $parent, $parentId);
        } else {
            if ($existing->subjectPersonaId() !== $subjectPersonaId) {
                throw $this->draft->failure('capture_client_id_conflict');
            }
            if ($existing->stage() === ClinicalCaptureStage::COMPLETED) {
                throw $this->draft->failure('capture_already_completed');
            }
            if ($existing->stage() === ClinicalCaptureStage::DISCARDED) {
                throw $this->draft->failure('capture_discarded');
            }
            $domain = $existing;
        }

        if ($file !== null) {
            $stored = $this->draft->storeUploadedAudioFile(
                $domain->clientCaptureId(),
                $domain->audioRelativePath(),
                $file
            );
            $hadProgress = in_array($domain->stage(), [
                ClinicalCaptureStage::STT_FAILED,
                ClinicalCaptureStage::ANALYSIS_FAILED,
                ClinicalCaptureStage::SAVE_FAILED,
                ClinicalCaptureStage::TRANSCRIBED,
                ClinicalCaptureStage::READY_FOR_REVIEW,
            ], true);
            $domain->attachAudio($stored['relative'], $stored['mime']);
            if ($hadProgress) {
                $domain->resetAfterAudioReplace();
            } else {
                $domain->markUploaded();
            }
        }

        $texto = trim((string) ($body['consulta'] ?? $body['texto'] ?? $body['transcript'] ?? ''));
        $stt = is_array($body['stt'] ?? null) ? $body['stt'] : [];
        if ($texto === '' && isset($stt['text'])) {
            $texto = trim((string) $stt['text']);
        }

        $forceServer = !empty($body['stt_force_server']) || !empty($stt['force_server']);

        if ($texto !== '' && !$forceServer) {
            $quality = null;
            if ($this->draft->shouldEvaluateDeviceStt($stt)) {
                $quality = DeviceSttQualityAssessor::assess($texto, $stt, 'captura_clinica');
            }
            $acceptDevice = $quality === null || !empty($quality['ok']);
            if (!$acceptDevice && !SttConfigService::isServerEnabled() && $domain->hasAudio()) {
                $acceptDevice = true;
            }
            if ($acceptDevice) {
                $domain->acceptInitialTranscript($texto, array_merge($stt, [
                    'provenance' => CaptureSpeechInputResolver::PROVENANCE_DEVICE,
                    'quality' => $quality,
                ]));
            } elseif ($domain->hasAudio()) {
                $domain->markUploaded(array_merge($stt, [
                    'quality' => $quality,
                    'pending_server_stt' => true,
                ]));
            } else {
                $domain->acceptInitialTranscript($texto, array_merge($stt, [
                    'provenance' => CaptureSpeechInputResolver::PROVENANCE_TEXT_ONLY,
                    'quality' => $quality,
                ]));
            }
        } elseif ($texto !== '' && $forceServer && !$domain->hasAudio()) {
            $domain->acceptInitialTranscript($texto, array_merge($stt, [
                'provenance' => CaptureSpeechInputResolver::PROVENANCE_TEXT_ONLY,
            ]));
        } elseif (!$domain->hasAudio() && $texto === '') {
            throw $this->draft->failure('capture_input_required');
        } elseif ($domain->hasAudio() && $texto === '') {
            $domain->markUploaded($stt !== [] ? $stt : null);
        }

        try {
            $capture = $this->draft->persistDomain($domain);
        } catch (\Throwable $e) {
            Yii::error('No se pudo persistir la captura: ' . $e->getMessage(), 'encounter-capture');
            throw $this->draft->failure('capture_persist_failed');
        }

        $sttMeta = $capture->getSttMeta();
        $this->draft->audit()->record($capture, EncounterCaptureAudit::EVENT_UPLOADED, [
            'stage' => $capture->stage,
            'has_audio' => $capture->hasAudio(),
            'has_transcript' => $capture->hasTranscript(),
            'stt_provenance' => $sttMeta['provenance'] ?? null,
            'pending_server_stt' => !empty($sttMeta['pending_server_stt']),
        ]);

        return $this->draft->ok($capture);
    }
}
