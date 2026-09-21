<?php

namespace common\components\Domain\Clinical\Capture\Application\Service;

use common\components\Domain\Clinical\Capture\Application\Presentation\CapturePresenter;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCapture;
use common\components\Domain\Clinical\Capture\Domain\Port\ClinicalCaptureRepository;
use common\components\Domain\Clinical\Capture\Infrastructure\Persistence\ActiveRecordClinicalCaptureRepository;
use common\components\Domain\Clinical\Capture\Infrastructure\SpeechToText\CaptureSpeechInputResolver;
use common\components\Domain\Clinical\Encounter\Application\Service\EncounterDocumentationService;
use common\components\Domain\Clinical\Encounter\Application\Service\EncounterCaptureAuditService;
use common\components\Platform\Ai\SpeechToText\SttConfigService;
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

    private EncounterDocumentationService $documentation;

    private EncounterCaptureAuditService $audit;

    private ClinicalCaptureRepository $captures;

    private ActiveRecordClinicalCaptureRepository $captureRows;

    private CapturePresenter $presenter;

    public function __construct(
        ?EncounterDocumentationService $documentation = null,
        ?EncounterCaptureAuditService $audit = null,
        ?ClinicalCaptureRepository $captures = null,
        ?CapturePresenter $presenter = null
    ) {
        $this->documentation = $documentation ?? new EncounterDocumentationService();
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

    public function documentation(): EncounterDocumentationService
    {
        return $this->documentation;
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
     * @return EncounterCapture|array<string, mixed>
     */
    public function findOpenCapture(array $body)
    {
        $capture = $this->findCapture($body, true);
        if (is_array($capture)) {
            return $capture;
        }
        if (!$capture->isOpen()) {
            return $this->presenter->fail(409, 'La captura no está abierta (stage=' . $capture->stage . ').', $capture);
        }

        return $capture;
    }

    /**
     * @param array<string, mixed> $body
     * @return EncounterCapture|array<string, mixed>
     */
    public function findCapture(array $body, bool $openOnly)
    {
        $id = (int) ($body['capture_id'] ?? $body['id'] ?? 0);
        $clientId = trim((string) ($body['client_capture_id'] ?? $body['clientCaptureId'] ?? ''));

        $capture = null;
        if ($id > 0) {
            $capture = EncounterCapture::findOne($id);
        } elseif ($clientId !== '') {
            $capture = EncounterCapture::findOne(['client_capture_id' => $clientId]);
        } else {
            return $this->presenter->fail(400, 'Se requiere capture_id o client_capture_id.');
        }

        if ($capture === null) {
            return $this->presenter->fail(404, 'Captura no encontrada.');
        }

        if ($openOnly && !$capture->isOpen()) {
            return $this->presenter->fail(409, 'La captura no está abierta (stage=' . $capture->stage . ').', $capture);
        }

        return $capture;
    }

    /**
     * Guarda el archivo en disco; no muta el aggregate.
     *
     * @return array{relative: string, mime: string|null}|array{__fail: array<string, mixed>}
     */
    public function storeUploadedAudioFile(string $clientCaptureId, ?string $previousRelativePath, UploadedFile $file): array
    {
        if (!$file->tempName) {
            return ['__fail' => $this->presenter->fail(400, 'Archivo de audio inválido.')];
        }

        $ext = strtolower((string) ($file->getExtension() ?: pathinfo((string) $file->name, PATHINFO_EXTENSION)));
        if ($ext === '') {
            $ext = 'm4a';
        }
        if (!preg_match('/^[a-z0-9]+$/', $ext)) {
            return ['__fail' => $this->presenter->fail(400, 'Extensión de audio no permitida.')];
        }

        $dirRelative = self::AUDIO_DIR . '/' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $clientCaptureId);
        $basePath = Yii::getAlias('@frontend/web') . '/' . $dirRelative;
        if (!is_dir($basePath) && !@mkdir($basePath, 0755, true)) {
            return ['__fail' => $this->presenter->fail(500, 'No se pudo crear el directorio de audio.')];
        }

        $filename = 'audio_' . date('YmdHis') . '_' . uniqid() . '.' . $ext;
        $relative = $dirRelative . '/' . $filename;
        $fullPath = Yii::getAlias('@frontend/web') . '/' . $relative;

        if (is_string($previousRelativePath) && trim($previousRelativePath) !== '') {
            $stub = new EncounterCapture();
            $stub->audio_relative_path = $previousRelativePath;
            $this->deleteAudioFile($stub);
        }

        if (!$file->saveAs($fullPath)) {
            return ['__fail' => $this->presenter->fail(500, 'Error al guardar el archivo de audio.')];
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
    public function ok(EncounterCapture $capture, string $message, bool $includeAnalysis = false): array
    {
        return $this->presenter->ok($capture, $message, $includeAnalysis);
    }

    /** @param array<string, mixed> $extra @return array<string, mixed> */
    public function fail(int $status, string $message, ?EncounterCapture $capture = null, array $extra = []): array
    {
        return $this->presenter->fail($status, $message, $capture, $extra);
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
