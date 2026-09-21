<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\Pipeline\ClinicalCapturePipelineSupport;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCapture;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureStage;
use common\components\Domain\Clinical\Capture\Infrastructure\SpeechToText\ClinicalSpeechInputResolver;
use common\components\Platform\Ai\SpeechToText\DeviceSttQualityAssessor;
use common\models\Clinical\EncounterCaptureAudit;
use Yii;
use yii\web\UploadedFile;

/** Caso de uso: crear/actualizar captura + audio/transcript inicial. */
final class CreateOrUploadClinicalCapture
{
    private ClinicalCapturePipelineSupport $pipeline;

    public function __construct(?ClinicalCapturePipelineSupport $pipeline = null)
    {
        $this->pipeline = $pipeline ?? new ClinicalCapturePipelineSupport();
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function execute(array $body, ?UploadedFile $file = null): array
    {

        $body = $this->pipeline->normalizeMultipartJsonFields($body);
        $clientId = trim((string) ($body['client_capture_id'] ?? $body['clientCaptureId'] ?? ''));
        if ($clientId === '') {
            return $this->pipeline->fail(400, 'Se requiere client_capture_id.');
        }
        if (strlen($clientId) > 64) {
            return $this->pipeline->fail(400, 'client_capture_id demasiado largo.');
        }

        $subjectPersonaId = (int) ($body['id_persona'] ?? $body['subject_persona_id'] ?? 0);
        if ($subjectPersonaId <= 0) {
            return $this->pipeline->fail(400, 'Se requiere id_persona.');
        }

        $userId = (int) (Yii::$app->user->id ?? 0);
        if ($userId <= 0) {
            return $this->pipeline->fail(401, 'Usuario no autenticado.');
        }

        $parent = isset($body['parent']) ? trim((string) $body['parent']) : null;
        if ($parent === '') {
            $parent = null;
        }
        $parentId = isset($body['parent_id']) ? (int) $body['parent_id'] : null;
        if ($parentId !== null && $parentId <= 0) {
            $parentId = null;
        }

        $existing = $this->pipeline->captures()->findByClientCaptureId($clientId);
        if ($existing === null) {
            $domain = ClinicalCapture::start($clientId, $subjectPersonaId, $userId, $parent, $parentId);
        } else {
            if ($existing->subjectPersonaId() !== $subjectPersonaId) {
                return $this->pipeline->fail(409, 'client_capture_id ya existe para otra persona.');
            }
            if ($existing->stage() === ClinicalCaptureStage::COMPLETED) {
                return $this->pipeline->fail(409, 'La captura ya fue completada.');
            }
            if ($existing->stage() === ClinicalCaptureStage::DISCARDED) {
                return $this->pipeline->fail(409, 'La captura fue descartada. Use un nuevo client_capture_id.');
            }
            $domain = $existing;
        }

        if ($file !== null) {
            $stored = $this->pipeline->storeUploadedAudioFile(
                $domain->clientCaptureId(),
                $domain->audioRelativePath(),
                $file
            );
            if (isset($stored['__fail'])) {
                return $stored['__fail'];
            }
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
            if ($this->pipeline->shouldEvaluateDeviceStt($stt)) {
                $quality = DeviceSttQualityAssessor::assess($texto, $stt, 'captura_clinica');
            }
            $acceptDevice = $quality === null || !empty($quality['ok']);
            if ($acceptDevice) {
                $domain->acceptInitialTranscript($texto, array_merge($stt, [
                    'provenance' => ClinicalSpeechInputResolver::PROVENANCE_DEVICE,
                    'quality' => $quality,
                ]));
            } elseif ($domain->hasAudio()) {
                $domain->markUploaded(array_merge($stt, [
                    'quality' => $quality,
                    'pending_server_stt' => true,
                ]));
            } else {
                $domain->acceptInitialTranscript($texto, array_merge($stt, [
                    'provenance' => ClinicalSpeechInputResolver::PROVENANCE_TEXT_ONLY,
                    'quality' => $quality,
                ]));
            }
        } elseif ($texto !== '' && $forceServer && !$domain->hasAudio()) {
            $domain->acceptInitialTranscript($texto, array_merge($stt, [
                'provenance' => ClinicalSpeechInputResolver::PROVENANCE_TEXT_ONLY,
            ]));
        } elseif (!$domain->hasAudio() && $texto === '') {
            return $this->pipeline->fail(400, 'Envíe audio (file) y/o texto de la consulta.');
        } elseif ($domain->hasAudio() && $texto === '') {
            $domain->markUploaded($stt !== [] ? $stt : null);
        }

        try {
            $capture = $this->pipeline->persistDomain($domain);
        } catch (\Throwable $e) {
            return $this->pipeline->fail(500, 'No se pudo persistir la captura: ' . $e->getMessage());
        }

        $sttMeta = $capture->getSttMeta();
        $this->pipeline->audit()->record($capture, EncounterCaptureAudit::EVENT_UPLOADED, [
            'stage' => $capture->stage,
            'has_audio' => $capture->hasAudio(),
            'has_transcript' => $capture->hasTranscript(),
            'stt_provenance' => $sttMeta['provenance'] ?? null,
            'pending_server_stt' => !empty($sttMeta['pending_server_stt']),
        ]);

        return $this->pipeline->ok($capture, 'Captura registrada.');
    }
}
