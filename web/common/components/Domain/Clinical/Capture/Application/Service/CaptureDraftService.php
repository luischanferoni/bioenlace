<?php

namespace common\components\Domain\Clinical\Capture\Application\Service;

use common\components\Domain\Clinical\Capture\Application\Presentation\CapturePresenter;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCapture;
use common\components\Domain\Clinical\Capture\Domain\Port\ClinicalCaptureRepository;
use common\components\Domain\Clinical\Capture\Infrastructure\Persistence\ActiveRecordClinicalCaptureRepository;
use common\components\Domain\Clinical\Capture\Infrastructure\SpeechToText\CaptureSpeechInputResolver;
use common\components\Domain\Clinical\Encounter\Application\UseCase\DocumentEncounter;
use common\components\Domain\Clinical\Encounter\Application\Service\EncounterCaptureAuditService;
use common\components\Platform\Ai\SpeechToText\SttConfigService;
use common\components\Platform\Core\Http\Contract\ApiErrorCatalog;
use common\components\Platform\Core\Http\Contract\ApiException;
use common\models\Clinical\EncounterCapture;
use Yii;
use yii\web\UploadedFile;

/**
 * Application Service del draft de captura: lookup, persistencia del aggregate, audio en disco.
 * Forma API: {@see CapturePresenter}. Orquestación por etapa = UseCase/*.
 *
 * Mutaciones vía aggregate {@see ClinicalCapture} + {@see ClinicalCaptureRepository}.
 */
final class CaptureDraftService
{
    private const AUDIO_DIR = 'uploads/encounter_capture';

    private DocumentEncounter $documentation;

    private EncounterCaptureAuditService $audit;

    private ClinicalCaptureRepository $captures;

    private ActiveRecordClinicalCaptureRepository $captureRows;

    private CapturePresenter $presenter;

    public function __construct(
        ?DocumentEncounter $documentation = null,
        ?EncounterCaptureAuditService $audit = null,
        ?ClinicalCaptureRepository $captures = null,
        ?CapturePresenter $presenter = null
    ) {
        $this->documentation = $documentation ?? new DocumentEncounter();
        $this->audit = $audit ?? new EncounterCaptureAuditService();
        $repo = $captures ?? new ActiveRecordClinicalCaptureRepository();
        $this->captures = $repo;
        $this->captureRows = $repo instanceof ActiveRecordClinicalCaptureRepository
            ? $repo
            : new ActiveRecordClinicalCaptureRepository();
        $this->presenter = $presenter ?? new CapturePresenter();
    }

    public function presenter(): CapturePresenter
    {
        return $this->presenter;
    }

    public function captures(): ClinicalCaptureRepository
    {
        return $this->captures;
    }

    public function captureRows(): ActiveRecordClinicalCaptureRepository
    {
        return $this->captureRows;
    }

    public function audit(): EncounterCaptureAuditService
    {
        return $this->audit;
    }

    public function documentation(): DocumentEncounter
    {
        return $this->documentation;
    }



    /**
     * Texto de un lote de notas (la captura principal incluida), en el orden recibido.
     * Todas deben ser abiertas, del mismo paciente y del mismo profesional.
     *
     * @param list<string> $clientIds
     * @throws ApiException
     */
    public function batchTranscript(ClinicalCapture $primary, array $clientIds): string
    {
        if (!in_array($primary->clientCaptureId(), $clientIds, true)) {
            throw $this->failure('capture_conflict', null, ['detail' => 'La captura principal no forma parte del lote.']);
        }

        $texts = [];
        foreach ($clientIds as $clientId) {
            $note = $clientId === $primary->clientCaptureId()
                ? $primary
                : $this->captures->findByClientCaptureId($clientId);
            if ($note === null) {
                throw $this->failure('capture_not_found');
            }
            if (!$note->isOpen()
                || $note->subjectPersonaId() !== $primary->subjectPersonaId()
                || $note->createdByUserId() !== $primary->createdByUserId()
            ) {
                throw $this->failure('capture_conflict', null, ['detail' => 'Una de las notas no pertenece a este borrador.']);
            }
            $texts[] = (string) $note->transcript();
        }

        return CaptureBatchTextComposer::compose($texts);
    }

    /**
     * Completa las notas acompañantes del lote una vez guardada la principal.
     *
     * @param list<string> $clientIds
     */
    public function completeBatchCompanions(ClinicalCapture $primary, array $clientIds, ?int $encounterId): void
    {
        foreach ($clientIds as $clientId) {
            if ($clientId === $primary->clientCaptureId()) {
                continue;
            }
            $note = $this->captures->findByClientCaptureId($clientId);
            if ($note === null
                || !$note->isOpen()
                || $note->subjectPersonaId() !== $primary->subjectPersonaId()
                || $note->createdByUserId() !== $primary->createdByUserId()
            ) {
                continue;
            }
            $note->complete($encounterId);
            $row = $this->persistDomain($note);
            $this->deleteAudioFile($row);
        }
    }

    /**
     * Persiste el aggregate y devuelve el AR para audit / respuesta API.
     */
    public function persistDomain(ClinicalCapture $domain): EncounterCapture
    {
        $this->captures->save($domain);
        $row = $this->captureRows->findActiveRecordByAggregate($domain);
        if (!$row instanceof EncounterCapture) {
            throw new \RuntimeException('ClinicalCapture persistido pero AR no encontrado.');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $body
     */
    public function findOpenCapture(array $body): EncounterCapture
    {
        return $this->findCapture($body, true);
    }

    /**
     * @param array<string, mixed> $body
     * @throws ApiException
     */
    public function findCapture(array $body, bool $openOnly): EncounterCapture
    {
        $id = (int) ($body['capture_id'] ?? $body['id'] ?? 0);
        $clientId = trim((string) ($body['client_capture_id'] ?? $body['clientCaptureId'] ?? ''));

        $capture = null;
        if ($id > 0) {
            $capture = EncounterCapture::findOne($id);
        } elseif ($clientId !== '') {
            $capture = EncounterCapture::findOne(['client_capture_id' => $clientId]);
        } else {
            throw $this->presenter->failure('capture_ref_required');
        }

        if ($capture === null) {
            throw $this->presenter->failure('capture_not_found');
        }

        if ($openOnly && !$capture->isOpen()) {
            throw $this->presenter->failure('capture_not_open', $capture);
        }

        return $capture;
    }

    /**
     * Guarda el archivo en disco; no muta el aggregate.
     *
     * @return array{relative: string, mime: string|null}
     * @throws ApiException
     */
    public function storeUploadedAudioFile(string $clientCaptureId, ?string $previousRelativePath, UploadedFile $file): array
    {
        if (!$file->tempName) {
            throw $this->presenter->failure('audio_invalid');
        }

        $ext = strtolower((string) ($file->getExtension() ?: pathinfo((string) $file->name, PATHINFO_EXTENSION)));
        if ($ext === '') {
            $ext = 'm4a';
        }
        if (!preg_match('/^[a-z0-9]+$/', $ext)) {
            throw $this->presenter->failure('audio_extension_not_allowed');
        }

        $dirRelative = self::AUDIO_DIR . '/' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $clientCaptureId);
        $filename = 'audio_' . date('YmdHis') . '_' . uniqid() . '.' . $ext;
        $relative = $dirRelative . '/' . $filename;
        $fullPath = Yii::getAlias('@frontend/web') . '/' . $relative;

        if (is_string($previousRelativePath) && trim($previousRelativePath) !== '') {
            $stub = new EncounterCapture();
            $stub->audio_relative_path = $previousRelativePath;
            $this->deleteAudioFile($stub);
        }

        $basePath = dirname($fullPath);
        if (!is_dir($basePath) && !@mkdir($basePath, 0755, true)) {
            throw $this->presenter->failure('audio_store_failed');
        }

        if (!$file->saveAs($fullPath)) {
            throw $this->presenter->failure('audio_store_failed');
        }

        return [
            'relative' => $relative,
            'mime' => $file->type ?: $this->guessMime($ext),
        ];
    }

    public function absoluteAudioPath(EncounterCapture $capture): ?string
    {
        if (!$capture->hasAudio()) {
            return null;
        }
        $relative = str_replace('\\', '/', (string) $capture->audio_relative_path);
        if (strpos($relative, '..') !== false || strpos($relative, self::AUDIO_DIR . '/') !== 0) {
            return null;
        }

        return Yii::getAlias('@frontend/web') . '/' . $relative;
    }

    public function deleteAudioFile(EncounterCapture $capture): void
    {
        $absolute = $this->absoluteAudioPath($capture);
        if ($absolute !== null && is_file($absolute)) {
            @unlink($absolute);
        }
        $capture->audio_relative_path = null;
        $capture->audio_mime = null;

        if ($absolute !== null) {
            $dir = dirname($absolute);
            if (is_dir($dir)) {
                $left = glob($dir . '/*');
                if ($left === [] || $left === false) {
                    @rmdir($dir);
                }
            }
        }
    }

    /**
     * Multipart suele mandar JSON anidados como string.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function normalizeMultipartJsonFields(array $body): array
    {
        foreach (['stt', 'userPerTabConfig', 'user_per_tab_config'] as $key) {
            if (!isset($body[$key]) || !is_string($body[$key])) {
                continue;
            }
            $decoded = json_decode($body[$key], true);
            if (is_array($decoded)) {
                $body[$key] = $decoded;
            }
        }
        if (isset($body['user_per_tab_config']) && !isset($body['userPerTabConfig'])) {
            $body['userPerTabConfig'] = $body['user_per_tab_config'];
        }

        return $body;
    }

    /**
     * @param array<string, mixed> $stt
     */
    public function shouldEvaluateDeviceStt(array $stt): bool
    {
        if (!SttConfigService::isDeviceEnabled()) {
            return false;
        }
        if (($stt['provenance'] ?? '') === CaptureSpeechInputResolver::PROVENANCE_DEVICE) {
            return true;
        }
        if (!empty($stt['engine'])) {
            return true;
        }

        return false;
    }

    private function guessMime(string $ext): string
    {
        $map = [
            'm4a' => 'audio/mp4',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'webm' => 'audio/webm',
            'ogg' => 'audio/ogg',
        ];

        return $map[$ext] ?? 'application/octet-stream';
    }


    /** @param array<string, mixed> $body @return list<array<string, mixed>> */
    public function resolveCategoriasForCapture(EncounterCapture $capture, array $body): array
    {
        return $this->presenter->resolveCategoriasForCapture($capture, $body);
    }

    /** @return array<string, mixed> */
    public function ok(EncounterCapture $capture, bool $includeAnalysis = false): array
    {
        return $this->presenter->ok($capture, $includeAnalysis);
    }

    /**
     * @param array<string, scalar> $params
     * @param array<string, mixed> $meta
     */
    public function failure(string $code, ?EncounterCapture $capture = null, array $params = [], array $meta = []): ApiException
    {
        return $this->presenter->failure($code, $capture, $params, $meta);
    }

    /**
     * Texto del catálogo para persistir como `last_error` de la captura.
     *
     * @param array<string, scalar> $params
     */
    public function errorMessage(string $code, array $params = []): string
    {
        return ApiErrorCatalog::resolve($code, $params)['message'];
    }

    /** @return array<string, mixed> */
    public function toApiArray(EncounterCapture $capture, bool $includeAnalysis = false): array
    {
        return $this->presenter->toApiArray($capture, $includeAnalysis);
    }

    /** @param array<string, mixed> $out @return array<string, mixed> */
    public function extractDatosExtraidos(array $out): array
    {
        return $this->presenter->extractDatosExtraidos($out);
    }

    /** @param array<string, mixed> $review @return array<string, mixed> */
    public function applyEpisodeDedupToReview(EncounterCapture $capture, array $review, string $texto): array
    {
        return $this->presenter->applyEpisodeDedupToReview($capture, $review, $texto);
    }
}
