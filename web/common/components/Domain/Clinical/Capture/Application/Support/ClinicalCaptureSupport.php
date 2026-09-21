<?php

namespace common\components\Domain\Clinical\Capture\Application\Support;

use common\components\Domain\Clinical\Capture\Application\RowContract\ClinicalCaptureRowContracts;
use common\components\Domain\Clinical\Capture\Application\Workflow\EncounterCaptureCategoryResolver;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCapture;
use common\components\Domain\Clinical\Capture\Domain\Port\ClinicalCaptureRepository;
use common\components\Domain\Clinical\Capture\Infrastructure\Persistence\ActiveRecordClinicalCaptureRepository;
use common\components\Domain\Clinical\Capture\Infrastructure\SpeechToText\ClinicalSpeechInputResolver;
use common\components\Domain\Clinical\Encounter\Application\Documentation\EncounterDocumentationService;
use common\components\Domain\Clinical\Encounter\Application\EncounterCaptureAuditService;
use common\components\Domain\Clinical\Encounter\Application\EncounterOpenProblemsService;
use common\components\Domain\Clinical\Encounter\Application\EpisodeCaptureDedupService;
use common\components\Domain\Clinical\Encounter\Application\Presentation\EncounterCaptureReviewPresenter;
use common\components\Platform\Ai\SpeechToText\SttConfigService;
use common\models\Clinical\EncounterCapture;
use common\models\Clinical\EncounterDefinition;
use Yii;
use yii\web\UploadedFile;

/**
 * Helpers compartidos de captura (lookup, audio, API shape, persist).
 * La orquestación por etapa vive en los use cases de Application; no es entrypoint HTTP.
 *
 * Mutaciones vía aggregate {@see ClinicalCapture} + {@see ClinicalCaptureRepository}.
 */
final class ClinicalCaptureSupport
{
    private const AUDIO_DIR = 'uploads/encounter_capture';

    private EncounterDocumentationService $documentation;

    private EncounterCaptureAuditService $audit;

    private ClinicalCaptureRepository $captures;

    private ActiveRecordClinicalCaptureRepository $captureRows;

    public function __construct(
        ?EncounterDocumentationService $documentation = null,
        ?EncounterCaptureAuditService $audit = null,
        ?ClinicalCaptureRepository $captures = null
    ) {
        $this->documentation = $documentation ?? new EncounterDocumentationService();
        $this->audit = $audit ?? new EncounterCaptureAuditService();
        $repo = $captures ?? new ActiveRecordClinicalCaptureRepository();
        $this->captures = $repo;
        $this->captureRows = $repo instanceof ActiveRecordClinicalCaptureRepository
            ? $repo
            : new ActiveRecordClinicalCaptureRepository();
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
     * @param array<string, mixed> $body
     * @return list<array<string, mixed>>
     */
    public function resolveCategoriasForCapture(EncounterCapture $capture, array $body): array
    {
        $analysis = $capture->getAnalysisResponse();
        $fromAnalysis = $analysis['categorias'] ?? null;
        if (is_array($fromAnalysis) && $fromAnalysis !== []) {
            return array_values(array_filter($fromAnalysis, 'is_array'));
        }

        $idConfig = (int) ($body['id_configuracion'] ?? $analysis['id_configuracion'] ?? 0);
        if ($idConfig <= 0) {
            return [];
        }
        $def = EncounterDefinition::findOne($idConfig);

        return $def !== null
            ? (new EncounterCaptureCategoryResolver())->resolve($def, $body)
            : [];
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
            return $this->fail(409, 'La captura no está abierta (stage=' . $capture->stage . ').', $capture);
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
            return $this->fail(400, 'Se requiere capture_id o client_capture_id.');
        }

        if ($capture === null) {
            return $this->fail(404, 'Captura no encontrada.');
        }

        if ($openOnly && !$capture->isOpen()) {
            return $this->fail(409, 'La captura no está abierta (stage=' . $capture->stage . ').', $capture);
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
            return ['__fail' => $this->fail(400, 'Archivo de audio inválido.')];
        }

        $ext = strtolower((string) ($file->getExtension() ?: pathinfo((string) $file->name, PATHINFO_EXTENSION)));
        if ($ext === '') {
            $ext = 'm4a';
        }
        if (!preg_match('/^[a-z0-9]+$/', $ext)) {
            return ['__fail' => $this->fail(400, 'Extensión de audio no permitida.')];
        }

        $dirRelative = self::AUDIO_DIR . '/' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $clientCaptureId);
        $basePath = Yii::getAlias('@frontend/web') . '/' . $dirRelative;
        if (!is_dir($basePath) && !@mkdir($basePath, 0755, true)) {
            return ['__fail' => $this->fail(500, 'No se pudo crear el directorio de audio.')];
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
            return ['__fail' => $this->fail(500, 'Error al guardar el archivo de audio.')];
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
        if (($stt['provenance'] ?? '') === ClinicalSpeechInputResolver::PROVENANCE_DEVICE) {
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

    /**
     * @return array<string, mixed>
     */
    public function ok(EncounterCapture $capture, string $message, bool $includeAnalysis = false): array
    {
        return [
            'success' => true,
            'message' => $message,
            'capture' => $this->toApiArray($capture, $includeAnalysis),
        ];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public function fail(int $status, string $message, ?EncounterCapture $capture = null, array $extra = []): array
    {
        $out = array_merge([
            '__statusCode' => $status,
            'success' => false,
            'message' => $message,
        ], $extra);
        if ($capture !== null) {
            $out['capture'] = $this->toApiArray($capture, false);
        }

        return $out;
    }

    /**
     * Serializa captura para API.
     *
     * - `$includeAnalysis = false` (listar): meta + transcript + `has_analysis`.
     * - `$includeAnalysis = true` (ver / analizar / guardar): `capture_review` +
     *   `datosExtraidos` una sola vez (sin reenviar el snapshot crudo del analizar).
     *
     * @return array<string, mixed>
     */
    public function toApiArray(EncounterCapture $capture, bool $includeAnalysis = false): array
    {
        $extraidosStored = $capture->getDatosExtraidos();
        $analysisStored = $capture->getAnalysisResponse();
        $hasAnalysis = $analysisStored !== [] || $extraidosStored !== [];

        $out = [
            'id' => (int) $capture->id,
            'client_capture_id' => $capture->client_capture_id,
            'subject_persona_id' => (int) $capture->subject_persona_id,
            'parent' => $capture->parent_type,
            'parent_id' => $capture->parent_id !== null ? (int) $capture->parent_id : null,
            'encounter_id' => $capture->encounter_id !== null ? (int) $capture->encounter_id : null,
            'stage' => $capture->stage,
            'has_audio' => $capture->hasAudio(),
            'has_analysis' => $hasAnalysis,
            'transcript' => $capture->transcript,
            'texto_procesado' => $capture->texto_procesado,
            'stt' => $capture->getSttMeta(),
            'staged_item_ids' => $capture->getStagedItemIds(),
            'analysis_cache_token' => $capture->analysis_cache_token,
            'last_error' => $capture->last_error,
            'attempts_stt' => (int) $capture->attempts_stt,
            'attempts_analysis' => (int) $capture->attempts_analysis,
            'attempts_save' => (int) $capture->attempts_save,
            'created_at' => $capture->created_at,
            'updated_at' => $capture->updated_at,
        ];

        if (!$includeAnalysis) {
            return $out;
        }

        $extraidos = $extraidosStored;
        if ($extraidos === [] && $analysisStored !== []) {
            $extraidos = $this->extractDatosExtraidosFromAnalizar($analysisStored);
        }

        $review = $analysisStored['capture_review'] ?? null;
        if (!is_array($review)) {
            $review = null;
        }

        if ($review !== null) {
            // Issues/completitud frescos desde el contrato de dominio (no el blob cacheado del analizar).
            $categorias = $this->resolveCategoriasForCapture($capture, []);
            if ($extraidos !== [] && $categorias !== []) {
                $refined = ClinicalCaptureRowContracts::refineDerivaciones($extraidos, $categorias);
                if ($refined !== $extraidos) {
                    $extraidos = $refined;
                    $textoOriginal = trim((string) ($analysisStored['texto_original'] ?? $capture->transcript ?? ''));
                    $textoProcesado = isset($analysisStored['texto_procesado'])
                        ? (string) $analysisStored['texto_procesado']
                        : ($capture->texto_procesado !== null ? (string) $capture->texto_procesado : null);
                    $review = (new EncounterCaptureReviewPresenter())->build(
                        $extraidos,
                        $categorias,
                        $textoOriginal,
                        $textoProcesado,
                        false
                    );
                } else {
                    $review = EncounterCaptureReviewPresenter::withFreshCompleteness(
                        $review,
                        $extraidos,
                        $categorias
                    );
                }
            }
            $review = EncounterCaptureReviewPresenter::slimCaptureReviewForApi($review);
            // open_problems fresco: solo previos, no diagnósticos/planes de esta captura.
            try {
                $categoriasForOps = $categorias !== []
                    ? $categorias
                    : $this->resolveCategoriasForCapture($capture, []);
                $openProblems = (new EncounterOpenProblemsService())->forCaptureReview(
                    (int) $capture->subject_persona_id,
                    is_array($extraidos) ? $extraidos : [],
                    $categoriasForOps,
                    $capture->encounter_id !== null ? (int) $capture->encounter_id : null
                );
                $hasOps = ($openProblems['conditions'] ?? []) !== []
                    || ($openProblems['care_plans'] ?? []) !== [];
                if ($hasOps) {
                    $review['open_problems'] = $openProblems;
                } else {
                    unset($review['open_problems']);
                }
            } catch (\Throwable $e) {
                unset($review['open_problems']);
            }
            $review = $this->applyEpisodeDedupToReview(
                $capture,
                $review,
                trim((string) ($analysisStored['texto_original'] ?? $capture->transcript ?? ''))
            );
            $out['capture_review'] = $review;
        } elseif ($extraidos !== []) {
            // Sin review: solo entonces exponer extracción cruda.
            $out['datosExtraidos'] = $extraidos;
        }

        // id_configuracion / id_consulta: necesarios para guardar; no duplicar textos/flags del review.
        if (array_key_exists('id_configuracion', $analysisStored) && !array_key_exists('id_configuracion', $out)) {
            $out['id_configuracion'] = $analysisStored['id_configuracion'];
        }
        if (array_key_exists('id_consulta', $analysisStored) && !array_key_exists('id_consulta', $out)) {
            $out['id_consulta'] = $analysisStored['id_consulta'];
        }
        if (isset($analysisStored['encounter_id']) && empty($out['encounter_id'])) {
            $out['encounter_id'] = (int) $analysisStored['encounter_id'];
        }
        // texto_procesado solo si aporta algo distinto al transcript.
        $tp = trim((string) ($out['texto_procesado'] ?? ''));
        $tr = trim((string) ($out['transcript'] ?? ''));
        if ($tp === '' || $tp === $tr) {
            unset($out['texto_procesado']);
        }

        return $out;
    }

    /**
     * El analizar legacy expone extracción en datos.datosExtraidos (no top-level).
     *
     * @param array<string, mixed> $out
     * @return array<string, mixed>
     */
    public function extractDatosExtraidosFromAnalizar(array $out): array
    {
        $direct = $out['datosExtraidos'] ?? $out['datos_extraidos'] ?? null;
        if (is_array($direct) && $direct !== []) {
            return $direct;
        }
        $datos = $out['datos'] ?? null;
        if (!is_array($datos)) {
            return [];
        }
        $nested = $datos['datosExtraidos'] ?? null;
        if (is_array($nested)) {
            return $nested;
        }
        if (is_string($nested) && $nested !== '') {
            $decoded = json_decode($nested, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $review
     * @return array<string, mixed>
     */
    public function applyEpisodeDedupToReview(
        EncounterCapture $capture,
        array $review,
        string $texto
    ): array {
        $parent = strtoupper(trim((string) ($capture->parent ?? '')));
        $parentId = (int) ($capture->parent_id ?? 0);
        $subjectId = (int) ($capture->subject_persona_id ?? 0);
        if ($parentId <= 0 || $subjectId <= 0) {
            return $review;
        }
        $dedupSvc = new EpisodeCaptureDedupService();
        $dedup = $dedupSvc->analyze(
            $parent,
            $parentId,
            $subjectId,
            $texto,
            is_array($review['categories'] ?? null) ? $review['categories'] : []
        );

        return $dedupSvc->applyToReview($review, $dedup);
    }
}