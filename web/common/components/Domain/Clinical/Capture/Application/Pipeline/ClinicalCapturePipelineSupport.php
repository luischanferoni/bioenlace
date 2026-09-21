<?php

namespace common\components\Domain\Clinical\Capture\Application\Pipeline;

use common\components\Domain\Clinical\Capture\Application\AnalyzeClinicalNote;
use common\components\Domain\Clinical\Capture\Application\ClinicalCaptureResolutionApplier;
use common\components\Domain\Clinical\Capture\Application\Workflow\EncounterCaptureCategoryResolver;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCapture;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureStage;
use common\components\Domain\Clinical\Capture\Domain\Policy\EncounterCaptureCompletenessValidator;
use common\components\Domain\Clinical\Capture\Domain\Port\ClinicalCaptureRepository;
use common\components\Domain\Clinical\Capture\Infrastructure\Persistence\ActiveRecordClinicalCaptureRepository;
use common\components\Domain\Clinical\Encounter\Application\Documentation\EncounterDocumentationService;
use common\components\Domain\Clinical\Encounter\Application\Presentation\EncounterCaptureReviewPresenter;
use common\models\Clinical\Input\DerivacionInput;
use common\components\Domain\Clinical\Encounter\Application\EncounterCaptureAuditService;
use common\components\Domain\Clinical\Encounter\Application\EncounterOpenProblemsService;
use common\components\Domain\Clinical\Encounter\Application\EpisodeCaptureDedupService;
use common\components\Domain\Clinical\Capture\Infrastructure\SpeechToText\ClinicalSpeechInputResolver;
use common\components\Platform\Ai\SpeechToText\DeviceSttQualityAssessor;
use common\components\Platform\Ai\SpeechToText\SpeechToTextManager;
use common\components\Platform\Ai\SpeechToText\SttConfigService;
use common\models\Clinical\EncounterCapture;
use common\models\Clinical\EncounterCaptureAudit;
use common\models\Clinical\EncounterDefinition;
use Yii;
use yii\web\UploadedFile;

/**
 * Soporte compartido del pipeline de captura (helpers + orquestación por etapa).
 * Los casos de uso en Application delegan aquí; no es entrypoint HTTP.
 *
 * Mutaciones vía aggregate {@see ClinicalCapture} + {@see ClinicalCaptureRepository}.
 */
final class ClinicalCapturePipelineSupport
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

    /**
     * Crea o actualiza una captura; opcionalmente sube audio y/o fija transcript de dispositivo.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function crearOSubir(array $body, ?UploadedFile $file = null): array
    {
        $body = $this->normalizeMultipartJsonFields($body);
        $clientId = trim((string) ($body['client_capture_id'] ?? $body['clientCaptureId'] ?? ''));
        if ($clientId === '') {
            return $this->fail(400, 'Se requiere client_capture_id.');
        }
        if (strlen($clientId) > 64) {
            return $this->fail(400, 'client_capture_id demasiado largo.');
        }

        $subjectPersonaId = (int) ($body['id_persona'] ?? $body['subject_persona_id'] ?? 0);
        if ($subjectPersonaId <= 0) {
            return $this->fail(400, 'Se requiere id_persona.');
        }

        $userId = (int) (Yii::$app->user->id ?? 0);
        if ($userId <= 0) {
            return $this->fail(401, 'Usuario no autenticado.');
        }

        $parent = isset($body['parent']) ? trim((string) $body['parent']) : null;
        if ($parent === '') {
            $parent = null;
        }
        $parentId = isset($body['parent_id']) ? (int) $body['parent_id'] : null;
        if ($parentId !== null && $parentId <= 0) {
            $parentId = null;
        }

        $existing = $this->captures->findByClientCaptureId($clientId);
        if ($existing === null) {
            $domain = ClinicalCapture::start($clientId, $subjectPersonaId, $userId, $parent, $parentId);
        } else {
            if ($existing->subjectPersonaId() !== $subjectPersonaId) {
                return $this->fail(409, 'client_capture_id ya existe para otra persona.');
            }
            if ($existing->stage() === ClinicalCaptureStage::COMPLETED) {
                return $this->fail(409, 'La captura ya fue completada.');
            }
            if ($existing->stage() === ClinicalCaptureStage::DISCARDED) {
                return $this->fail(409, 'La captura fue descartada. Use un nuevo client_capture_id.');
            }
            $domain = $existing;
        }

        if ($file !== null) {
            $stored = $this->storeUploadedAudioFile(
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
            if ($this->shouldEvaluateDeviceStt($stt)) {
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
            return $this->fail(400, 'Envíe audio (file) y/o texto de la consulta.');
        } elseif ($domain->hasAudio() && $texto === '') {
            $domain->markUploaded($stt !== [] ? $stt : null);
        }

        try {
            $capture = $this->persistDomain($domain);
        } catch (\Throwable $e) {
            return $this->fail(500, 'No se pudo persistir la captura: ' . $e->getMessage());
        }

        $sttMeta = $capture->getSttMeta();
        $this->audit->record($capture, EncounterCaptureAudit::EVENT_UPLOADED, [
            'stage' => $capture->stage,
            'has_audio' => $capture->hasAudio(),
            'has_transcript' => $capture->hasTranscript(),
            'stt_provenance' => $sttMeta['provenance'] ?? null,
            'pending_server_stt' => !empty($sttMeta['pending_server_stt']),
        ]);

        return $this->ok($capture, 'Captura registrada.');
    }

    /**
     * STT síncrono sobre el audio ya subido. No re-sube el archivo.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function transcribir(array $body): array
    {
        $capture = $this->findOpenCapture($body);
        if (is_array($capture)) {
            return $capture;
        }

        $domain = $this->captureRows->toAggregate($capture);

        if ($domain->hasTranscript()
            && in_array($domain->stage(), [
                ClinicalCaptureStage::TRANSCRIBED,
                ClinicalCaptureStage::ANALYSIS_FAILED,
                ClinicalCaptureStage::READY_FOR_REVIEW,
                ClinicalCaptureStage::SAVE_FAILED,
            ], true)
            && empty($body['force'])
        ) {
            return $this->ok($capture, 'Ya hay transcripción; no se reejecutó STT.');
        }

        if (!$domain->hasAudio()) {
            return $this->fail(400, 'La captura no tiene audio en servidor para transcribir.', $capture);
        }

        $absolute = $this->absoluteAudioPath($capture);
        if ($absolute === null || !is_file($absolute)) {
            $domain->markSttFailed('Archivo de audio no encontrado en servidor.');
            $capture = $this->persistDomain($domain);
            $this->audit->record($capture, EncounterCaptureAudit::EVENT_STT_FAILED, [
                'error_code' => 'audio_missing',
                'attempts_stt' => $domain->attemptsStt(),
            ]);

            return $this->fail(404, $capture->last_error, $capture);
        }

        if (!SttConfigService::isServerEnabled()) {
            $domain->markSttFailed('La transcripción en servidor está deshabilitada.');
            $capture = $this->persistDomain($domain);
            $this->audit->record($capture, EncounterCaptureAudit::EVENT_STT_FAILED, [
                'error_code' => 'server_stt_disabled',
                'attempts_stt' => $domain->attemptsStt(),
            ]);

            return $this->fail(400, $capture->last_error, $capture);
        }

        $modelo = (string) ($body['modelo'] ?? 'economico');
        $result = SpeechToTextManager::transcribir($absolute, $modelo);
        $texto = trim((string) ($result['texto'] ?? ''));

        if ($texto === '') {
            $err = trim((string) ($result['error'] ?? 'No se pudo transcribir el audio.'));
            $domain->markSttFailed($err !== '' ? $err : 'No se pudo transcribir el audio.');
            $capture = $this->persistDomain($domain);
            $this->audit->record($capture, EncounterCaptureAudit::EVENT_STT_FAILED, [
                'error_code' => 'empty_transcript',
                'attempts_stt' => $domain->attemptsStt(),
                'modelo' => $modelo,
            ]);

            return $this->fail(502, $capture->last_error, $capture);
        }

        $meta = $domain->sttMeta();
        $meta['provenance'] = ClinicalSpeechInputResolver::PROVENANCE_SERVER;
        $meta['server_stt'] = [
            'confidence' => $result['confidence'] ?? null,
            'modelo_usado' => $result['modelo_usado'] ?? null,
        ];
        $domain->markTranscribed($texto, $meta);
        $capture = $this->persistDomain($domain);
        $this->audit->record($capture, EncounterCaptureAudit::EVENT_STT_OK, [
            'attempts_stt' => $domain->attemptsStt(),
            'provenance' => ClinicalSpeechInputResolver::PROVENANCE_SERVER,
            'modelo_usado' => $result['modelo_usado'] ?? null,
            'transcript_length' => mb_strlen($texto),
        ]);

        return $this->ok($capture, 'Transcripción lista.');
    }

    /**
     * Análisis IA síncrono sobre el transcript ya persistido.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function analizar(array $body): array
    {
        $capture = $this->findOpenCapture($body);
        if (is_array($capture)) {
            return $capture;
        }

        $domain = $this->captureRows->toAggregate($capture);

        $textoOverride = trim((string) ($body['consulta'] ?? $body['texto'] ?? ''));
        if ($textoOverride !== '') {
            $domain->setTranscriptOverride($textoOverride);
        }

        if (!$domain->hasTranscript()) {
            return $this->fail(400, 'No hay transcripción. Ejecute captura-transcribir o envíe texto.', $capture);
        }

        if ($domain->stage() === ClinicalCaptureStage::READY_FOR_REVIEW
            && $domain->analysisResponse() !== []
            && empty($body['force'])
        ) {
            return $this->ok($capture, 'Análisis ya disponible.', true);
        }

        $analyzeBody = $body;
        $analyzeBody['consulta'] = $domain->transcript();
        $analyzeBody['id_persona'] = $domain->subjectPersonaId();
        $analyzeBody['subject_persona_id'] = $domain->subjectPersonaId();
        if ($domain->parentType() !== null) {
            $analyzeBody['parent'] = $domain->parentType();
        }
        if ($domain->parentId() !== null) {
            $analyzeBody['parent_id'] = $domain->parentId();
        }
        unset($analyzeBody['audio'], $analyzeBody['audio_data'], $analyzeBody['file']);
        $analyzeBody['stt'] = array_merge($domain->sttMeta(), [
            'text' => $domain->transcript(),
            'force_server' => false,
        ]);

        $out = (new AnalyzeClinicalNote())->execute($analyzeBody);

        if (empty($out['success'])) {
            $msg = trim((string) ($out['message'] ?? 'Error al analizar la consulta.'));
            $domain->markAnalysisFailed($msg !== '' ? $msg : 'Error al analizar la consulta.');
            $capture = $this->persistDomain($domain);
            $this->audit->record($capture, EncounterCaptureAudit::EVENT_ANALYSIS_FAILED, [
                'attempts_analysis' => $domain->attemptsAnalysis(),
                'error_code' => 'analysis_failed',
            ]);
            $status = (int) ($out['__statusCode'] ?? 500);

            return $this->fail($status > 0 ? $status : 500, $capture->last_error, $capture);
        }

        $textoProcesado = isset($out['texto_procesado'])
            ? (string) $out['texto_procesado']
            : (string) $domain->transcript();
        $extraidos = $this->extractDatosExtraidosFromAnalizar($out);
        $snapshot = $out;
        unset($snapshot['html']);
        $encounterId = isset($out['encounter_id'])
            ? (int) $out['encounter_id']
            : (isset($out['id_consulta']) ? (int) $out['id_consulta'] : null);
        $staged = null;
        $review = $out['capture_review'] ?? null;
        if (is_array($review) && isset($review['default_staged_item_ids']) && is_array($review['default_staged_item_ids'])) {
            $staged = array_map('strval', $review['default_staged_item_ids']);
        }
        $token = isset($out['analysis_cache_token']) ? (string) $out['analysis_cache_token'] : null;

        $domain->markReadyForReview(
            $textoProcesado,
            $extraidos,
            $snapshot,
            $token,
            $staged,
            $encounterId
        );
        $capture = $this->persistDomain($domain);
        $this->audit->record(
            $capture,
            EncounterCaptureAudit::EVENT_ANALYZED,
            array_merge(
                ['attempts_analysis' => $domain->attemptsAnalysis()],
                is_array($review) ? EncounterCaptureAuditService::buildAnalyzedMeta($review) : []
            )
        );

        return $this->ok($capture, 'Análisis listo.', true);
    }

    /**
     * Guardado clínico síncrono desde el draft de análisis.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function guardar(array $body): array
    {
        $capture = $this->findOpenCapture($body);
        if (is_array($capture)) {
            return $capture;
        }

        $analysis = $capture->getAnalysisResponse();
        if ($analysis === [] && $capture->getDatosExtraidos() === []) {
            return $this->fail(400, 'No hay análisis para guardar. Ejecute captura-analizar.', $capture);
        }

        $stagedFromBody = $body['staged_item_ids'] ?? $body['stagedItemIds'] ?? null;
        if (is_array($stagedFromBody)) {
            $capture->setStagedItemIds(array_map('strval', $stagedFromBody));
        }

        $datosExtraidos = $body['datosExtraidos'] ?? null;
        if (!is_array($datosExtraidos) || $datosExtraidos === []) {
            $datosExtraidos = $capture->getDatosExtraidos();
            if ($datosExtraidos === [] && $analysis !== []) {
                $datosExtraidos = $this->extractDatosExtraidosFromAnalizar($analysis);
            }
        }

        $resolutions = $body['resolutions'] ?? $body['resoluciones'] ?? null;
        if (!is_array($resolutions)) {
            $resolutions = [];
        }
        $analysisLocal = is_array($analysis) ? $analysis : [];
        $prevResolutions = [];
        if (isset($analysisLocal['client_resolutions']) && is_array($analysisLocal['client_resolutions'])) {
            $prevResolutions = $analysisLocal['client_resolutions'];
        }
        $mergedResolutions = array_merge($prevResolutions, $resolutions);

        $fullCheckpoint = $capture->getDatosExtraidos();
        if ($fullCheckpoint === [] && $analysisLocal !== []) {
            $fullCheckpoint = $this->extractDatosExtraidosFromAnalizar($analysisLocal);
        }
        if ($mergedResolutions !== [] && $fullCheckpoint !== []) {
            $categorias = $this->resolveCategoriasForCapture($capture, $body);
            $fullCheckpoint = (new ClinicalCaptureResolutionApplier())->apply(
                $fullCheckpoint,
                $mergedResolutions,
                $categorias
            );
            $capture->setDatosExtraidos($fullCheckpoint);
        }
        if ($mergedResolutions !== []) {
            $analysisLocal['client_resolutions'] = $mergedResolutions;
            $capture->setAnalysisResponse($analysisLocal);
        }
        if ($mergedResolutions !== [] || $fullCheckpoint !== []) {
            $capture->updated_at = date('Y-m-d H:i:s');
            $capture->save(false);
        }

        $blocking = EncounterCaptureReviewPresenter::blockingErrorFromExtraidos(
            is_array($datosExtraidos) ? $datosExtraidos : []
        );
        if ($blocking !== null) {
            $msg = trim((string) ($blocking['texto'] ?? 'No se puede guardar: el análisis tiene errores.'));

            return $this->fail(400, $msg !== '' ? $msg : 'No se puede guardar.', $capture);
        }

        $saveBody = $body;
        $saveBody['id_persona'] = $capture->subject_persona_id;
        $saveBody['subject_persona_id'] = $capture->subject_persona_id;
        $saveBody['datosExtraidos'] = $datosExtraidos;
        $saveBody['resolutions'] = $mergedResolutions;
        $saveBody['analisis_datos_extraidos'] = $fullCheckpoint !== []
            ? $fullCheckpoint
            : $capture->getDatosExtraidos();
        $saveBody['texto_original'] = $saveBody['texto_original']
            ?? $capture->transcript
            ?? '';
        $saveBody['texto_procesado'] = $saveBody['texto_procesado']
            ?? $capture->texto_procesado
            ?? $capture->transcript
            ?? '';
        if ($capture->parent_type !== null) {
            $saveBody['parent'] = $capture->parent_type;
        }
        if ($capture->parent_id !== null) {
            $saveBody['parent_id'] = $capture->parent_id;
        }
        if ($capture->analysis_cache_token) {
            $saveBody['analysis_cache_token'] = $capture->analysis_cache_token;
        }
        if ($capture->encounter_id) {
            $saveBody['id_consulta'] = $capture->encounter_id;
            $saveBody['encounter_id'] = $capture->encounter_id;
        }
        if (isset($analysis['id_configuracion']) && !isset($saveBody['id_configuracion'])) {
            $saveBody['id_configuracion'] = $analysis['id_configuracion'];
        }

        $out = $this->documentation->guardar($saveBody);
        // Si el dominio devolvió checkpoint resuelto, persistirlo para el próximo intento.
        $domain = $this->captureRows->toAggregate($capture);
        if (isset($out['analisis_datos_extraidos']) && is_array($out['analisis_datos_extraidos'])) {
            $domain->replaceDatosExtraidos($out['analisis_datos_extraidos']);
        } elseif (empty($out['success']) && $fullCheckpoint !== []) {
            $domain->replaceDatosExtraidos($fullCheckpoint);
        }

        $reviewForAudit = is_array($analysis['capture_review'] ?? null) ? $analysis['capture_review'] : [];
        $acceptanceMeta = EncounterCaptureAuditService::buildAcceptanceMeta(
            $reviewForAudit,
            $capture->getStagedItemIds(),
            $mergedResolutions !== [] ? $mergedResolutions : null
        );

        if (empty($out['success'])) {
            $msg = trim((string) ($out['message'] ?? 'Error al guardar.'));
            $domain->markSaveFailed($msg !== '' ? $msg : 'Error al guardar.');
            $capture = $this->persistDomain($domain);
            $this->audit->record($capture, EncounterCaptureAudit::EVENT_SAVE_FAILED, array_merge(
                [
                    'attempts_save' => $domain->attemptsSave(),
                    'error_code' => 'save_failed',
                ],
                $acceptanceMeta
            ));
            $status = (int) ($out['__statusCode'] ?? 500);

            return $this->fail($status > 0 ? $status : 500, $capture->last_error, $capture, [
                'guardar' => $out,
            ]);
        }

        $encounterId = isset($out['encounter_id']) ? (int) $out['encounter_id'] : null;
        $domain->complete($encounterId);
        $capture = $this->persistDomain($domain);
        $this->audit->record($capture, EncounterCaptureAudit::EVENT_SAVED, array_merge(
            ['attempts_save' => $domain->attemptsSave()],
            $acceptanceMeta
        ));

        // Tras completar, el audio crudo ya no es necesario para el pipeline.
        $this->deleteAudioFile($capture);

        $payload = $this->toApiArray($capture);
        $payload['guardar'] = $out;

        return [
            'success' => true,
            'message' => (string) ($out['message'] ?? 'Captura guardada.'),
            'capture' => $payload,
            'guardar' => $out,
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function listar(array $query): array
    {
        $subjectPersonaId = (int) ($query['id_persona'] ?? $query['subject_persona_id'] ?? 0);
        if ($subjectPersonaId <= 0) {
            return $this->fail(400, 'Se requiere id_persona.');
        }

        $q = EncounterCapture::find()
            ->where(['subject_persona_id' => $subjectPersonaId])
            ->andWhere(['stage' => EncounterCapture::openStageValues()])
            ->orderBy(['updated_at' => SORT_DESC]);

        $parent = isset($query['parent']) ? trim((string) $query['parent']) : '';
        if ($parent !== '') {
            $q->andWhere(['parent_type' => $parent]);
        }
        if (isset($query['parent_id']) && $query['parent_id'] !== '' && $query['parent_id'] !== null) {
            $q->andWhere(['parent_id' => (int) $query['parent_id']]);
        }

        $items = [];
        foreach ($q->limit(50)->all() as $row) {
            /** @var EncounterCapture $row */
            // Listado liviano: el análisis completo va en captura/ver.
            $items[] = $this->toApiArray($row, false);
        }

        return [
            'success' => true,
            'message' => 'OK',
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function ver(array $body): array
    {
        $capture = $this->findCapture($body, false);
        if (is_array($capture)) {
            return $capture;
        }

        return $this->ok($capture, 'OK', true);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function descartar(array $body): array
    {
        $capture = $this->findCapture($body, false);
        if (is_array($capture)) {
            return $capture;
        }

        $domain = $this->captureRows->toAggregate($capture);
        if ($domain->stage() === ClinicalCaptureStage::COMPLETED) {
            return $this->fail(409, 'No se puede descartar una captura ya completada.', $capture);
        }

        $this->deleteAudioFile($capture);
        $hadAnalysis = $capture->getAnalysisResponse() !== [];
        $previousStage = $capture->stage;
        try {
            $domain->discard();
        } catch (\InvalidArgumentException $e) {
            return $this->fail(409, $e->getMessage(), $capture);
        }
        $capture = $this->persistDomain($domain);
        $this->audit->record($capture, EncounterCaptureAudit::EVENT_DISCARDED, [
            'previous_stage' => $previousStage,
            'previous_had_analysis' => $hadAnalysis,
        ]);

        return $this->ok($capture, 'Captura descartada.');
    }

    /**
     * Aplica resoluciones del profesional a issues pendientes y regenera capture_review.
     *
     * Body: capture_id|client_capture_id, resolutions = { issue_id: value }
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function aplicarResoluciones(array $body): array
    {
        $capture = $this->findOpenCapture($body);
        if (is_array($capture)) {
            return $capture;
        }

        $domain = $this->captureRows->toAggregate($capture);
        if (!in_array($domain->stage(), [
            ClinicalCaptureStage::READY_FOR_REVIEW,
            ClinicalCaptureStage::SAVE_FAILED,
        ], true)) {
            return $this->fail(409, 'La captura no tiene análisis para resolver.', $capture);
        }

        $resolutions = $body['resolutions'] ?? $body['resoluciones'] ?? null;
        if (!is_array($resolutions) || $resolutions === []) {
            return $this->fail(400, 'resolutions es obligatorio.', $capture);
        }

        $datos = $domain->datosExtraidos();
        if ($datos === []) {
            $datos = $this->extractDatosExtraidosFromAnalizar($domain->analysisResponse());
        }
        if ($datos === []) {
            return $this->fail(400, 'No hay datos extraídos para resolver.', $capture);
        }

        $categorias = $this->resolveCategoriasForCapture($capture, $body);
        $datos = (new ClinicalCaptureResolutionApplier())->apply($datos, $resolutions, $categorias);

        $completeness = (new EncounterCaptureCompletenessValidator())->validate($datos, $categorias);
        $analysis = $domain->analysisResponse();
        $textoOriginal = trim((string) ($analysis['texto_original'] ?? $domain->transcript() ?? ''));
        $textoProcesado = isset($analysis['texto_procesado'])
            ? (string) $analysis['texto_procesado']
            : ($domain->textoProcesado() !== null ? (string) $domain->textoProcesado() : null);
        $review = (new EncounterCaptureReviewPresenter())->build(
            $datos,
            $categorias,
            $textoOriginal,
            $textoProcesado,
            ($completeness['tiene_datos_faltantes'] ?? false) === true,
            $completeness
        );
        $review = $this->applyEpisodeDedupToReview($capture, $review, $textoOriginal);

        if ($analysis === []) {
            $analysis = ['success' => true];
        }
        if (isset($analysis['datos']) && is_array($analysis['datos'])) {
            $analysis['datos']['datosExtraidos'] = $datos;
        }
        $analysis['datosExtraidos'] = $datos;
        $analysis['capture_review'] = $review;
        $analysis['puede_confirmar'] = $review['puede_confirmar'] ?? false;
        $analysis['tiene_datos_faltantes'] = $review['tiene_datos_faltantes'] ?? false;
        $analysis['datos_faltantes_detalle'] = $review['datos_faltantes_detalle'] ?? [
            'missing_categories' => $completeness['missing_categories'] ?? [],
            'incomplete_items' => $completeness['incomplete_items'] ?? [],
            'issues' => $completeness['issues'] ?? [],
            'message' => $completeness['message'] ?? '',
        ];

        try {
            $domain->applyResolutionSnapshot($datos, $analysis);
            $capture = $this->persistDomain($domain);
        } catch (\InvalidArgumentException $e) {
            return $this->fail(409, $e->getMessage(), $capture);
        } catch (\Throwable $e) {
            return $this->fail(500, 'No se pudieron guardar las resoluciones.', $capture);
        }

        $this->audit->record($capture, EncounterCaptureAudit::EVENT_RESOLUTIONS_APPLIED, [
            'issue_ids' => array_values(array_map('strval', array_keys($resolutions))),
            'puede_confirmar' => ($review['puede_confirmar'] ?? false) === true,
        ]);

        return $this->ok($capture, 'Resoluciones aplicadas.', true);
    }

    /**
     * @param array<string, mixed> $body
     * @return list<array<string, mixed>>
     */
    private function resolveCategoriasForCapture(EncounterCapture $capture, array $body): array
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
     * @return array{path: string, mime: string, filename: string}|array<string, mixed>
     */
    public function resolveAudioDownload(array $query)
    {
        $capture = $this->findCapture($query, false);
        if (is_array($capture)) {
            return $capture;
        }
        if (!$capture->hasAudio()) {
            return $this->fail(404, 'La captura no tiene audio.', $capture);
        }
        $absolute = $this->absoluteAudioPath($capture);
        if ($absolute === null || !is_file($absolute)) {
            return $this->fail(404, 'Archivo de audio no encontrado.', $capture);
        }

        return [
            'path' => $absolute,
            'mime' => $capture->audio_mime ?: 'audio/mp4',
            'filename' => basename($absolute),
            'capture' => $capture,
        ];
    }

    /**
     * Persiste el aggregate y devuelve el AR para audit / respuesta API.
     */
    private function persistDomain(ClinicalCapture $domain): EncounterCapture
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
    private function findOpenCapture(array $body)
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
    private function findCapture(array $body, bool $openOnly)
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
     * @return array<string, mixed>|null error response
     */
    /**
     * Guarda el archivo en disco; no muta el aggregate.
     *
     * @return array{relative: string, mime: string|null}|array{__fail: array<string, mixed>}
     */
    private function storeUploadedAudioFile(string $clientCaptureId, ?string $previousRelativePath, UploadedFile $file): array
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

    private function absoluteAudioPath(EncounterCapture $capture): ?string
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

    private function deleteAudioFile(EncounterCapture $capture): void
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
    private function normalizeMultipartJsonFields(array $body): array
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
    private function shouldEvaluateDeviceStt(array $stt): bool
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
    private function ok(EncounterCapture $capture, string $message, bool $includeAnalysis = false): array
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
    private function fail(int $status, string $message, ?EncounterCapture $capture = null, array $extra = []): array
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
                $refined = DerivacionInput::refineDatosExtraidos($extraidos, $categorias);
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
    private function extractDatosExtraidosFromAnalizar(array $out): array
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
    private function applyEpisodeDedupToReview(
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
