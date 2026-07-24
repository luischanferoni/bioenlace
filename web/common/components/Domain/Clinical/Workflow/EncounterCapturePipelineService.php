<?php

namespace common\components\Domain\Clinical\Workflow;

use common\components\Domain\Clinical\Legacy\ConsultaProcesamientoService;
use common\components\Domain\Clinical\Presentation\EncounterCaptureReviewPresenter;
use common\components\Domain\Clinical\SpeechToText\ClinicalSpeechInputResolver;
use common\components\Platform\Ai\SpeechToText\DeviceSttQualityAssessor;
use common\components\Platform\Ai\SpeechToText\SpeechToTextManager;
use common\components\Platform\Ai\SpeechToText\SttConfigService;
use common\models\Clinical\EncounterCapture;
use Yii;
use yii\web\UploadedFile;

/**
 * Pipeline síncrono de captura clínica por etapas.
 * Cada método HTTP avanza y persiste el checkpoint (sin jobs / pull-push).
 */
final class EncounterCapturePipelineService
{
    private const AUDIO_DIR = 'uploads/encounter_capture';

    private EncounterDocumentationService $documentation;

    public function __construct(?EncounterDocumentationService $documentation = null)
    {
        $this->documentation = $documentation ?? new EncounterDocumentationService();
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

        $now = date('Y-m-d H:i:s');
        $capture = EncounterCapture::findOne(['client_capture_id' => $clientId]);
        if ($capture === null) {
            $capture = new EncounterCapture();
            $capture->client_capture_id = $clientId;
            $capture->subject_persona_id = $subjectPersonaId;
            $capture->parent_type = $parent;
            $capture->parent_id = $parentId;
            $capture->created_by_user_id = $userId;
            $capture->stage = EncounterCapture::STAGE_UPLOADED;
            $capture->created_at = $now;
            $capture->attempts_stt = 0;
            $capture->attempts_analysis = 0;
            $capture->attempts_save = 0;
        } else {
            if ((int) $capture->subject_persona_id !== $subjectPersonaId) {
                return $this->fail(409, 'client_capture_id ya existe para otra persona.');
            }
            if ($capture->stage === EncounterCapture::STAGE_COMPLETED) {
                return $this->fail(409, 'La captura ya fue completada.');
            }
            if ($capture->stage === EncounterCapture::STAGE_DISCARDED) {
                return $this->fail(409, 'La captura fue descartada. Use un nuevo client_capture_id.');
            }
        }

        $capture->updated_at = $now;
        $capture->last_error = null;

        if ($file !== null) {
            $saved = $this->persistUploadedAudio($capture, $file);
            if ($saved !== null) {
                return $saved;
            }
            if ($capture->stage === EncounterCapture::STAGE_STT_FAILED
                || $capture->stage === EncounterCapture::STAGE_ANALYSIS_FAILED
                || $capture->stage === EncounterCapture::STAGE_SAVE_FAILED
                || $capture->stage === EncounterCapture::STAGE_TRANSCRIBED
                || $capture->stage === EncounterCapture::STAGE_READY_FOR_REVIEW
            ) {
                // Reemplazo de audio: vuelve a checkpoint de upload.
                $capture->stage = EncounterCapture::STAGE_UPLOADED;
                $capture->transcript = null;
                $capture->texto_procesado = null;
                $capture->setDatosExtraidos(null);
                $capture->setAnalysisResponse(null);
                $capture->analysis_cache_token = null;
                $capture->setStagedItemIds(null);
            } elseif ($capture->isNewRecord || $capture->stage === EncounterCapture::STAGE_UPLOADED) {
                $capture->stage = EncounterCapture::STAGE_UPLOADED;
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
                $capture->transcript = $texto;
                $capture->setSttMeta(array_merge($stt, [
                    'provenance' => ClinicalSpeechInputResolver::PROVENANCE_DEVICE,
                    'quality' => $quality,
                ]));
                $capture->stage = EncounterCapture::STAGE_TRANSCRIBED;
            } elseif ($capture->hasAudio()) {
                // Texto de dispositivo no confiable: queda UPLOADED para STT servidor.
                $capture->setSttMeta(array_merge($stt, [
                    'quality' => $quality,
                    'pending_server_stt' => true,
                ]));
                $capture->stage = EncounterCapture::STAGE_UPLOADED;
            } else {
                // Sin audio y texto malo: igual aceptamos como transcript editable.
                $capture->transcript = $texto;
                $capture->setSttMeta(array_merge($stt, [
                    'provenance' => ClinicalSpeechInputResolver::PROVENANCE_TEXT_ONLY,
                    'quality' => $quality,
                ]));
                $capture->stage = EncounterCapture::STAGE_TRANSCRIBED;
            }
        } elseif ($texto !== '' && $forceServer && !$capture->hasAudio()) {
            $capture->transcript = $texto;
            $capture->setSttMeta(array_merge($stt, [
                'provenance' => ClinicalSpeechInputResolver::PROVENANCE_TEXT_ONLY,
            ]));
            $capture->stage = EncounterCapture::STAGE_TRANSCRIBED;
        } elseif (!$capture->hasAudio() && $texto === '') {
            return $this->fail(400, 'Envíe audio (file) y/o texto de la consulta.');
        } elseif ($capture->hasAudio() && $texto === '') {
            $capture->stage = EncounterCapture::STAGE_UPLOADED;
            if ($stt !== []) {
                $capture->setSttMeta($stt);
            }
        }

        if (!$capture->save()) {
            return $this->fail(500, 'No se pudo persistir la captura: ' . implode(', ', $capture->getFirstErrors()));
        }

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

        if ($capture->hasTranscript()
            && in_array($capture->stage, [
                EncounterCapture::STAGE_TRANSCRIBED,
                EncounterCapture::STAGE_ANALYSIS_FAILED,
                EncounterCapture::STAGE_READY_FOR_REVIEW,
                EncounterCapture::STAGE_SAVE_FAILED,
            ], true)
            && empty($body['force'])
        ) {
            return $this->ok($capture, 'Ya hay transcripción; no se reejecutó STT.');
        }

        if (!$capture->hasAudio()) {
            return $this->fail(400, 'La captura no tiene audio en servidor para transcribir.', $capture);
        }

        $absolute = $this->absoluteAudioPath($capture);
        if ($absolute === null || !is_file($absolute)) {
            $capture->stage = EncounterCapture::STAGE_STT_FAILED;
            $capture->last_error = 'Archivo de audio no encontrado en servidor.';
            $capture->attempts_stt = (int) $capture->attempts_stt + 1;
            $capture->updated_at = date('Y-m-d H:i:s');
            $capture->save(false);

            return $this->fail(404, $capture->last_error, $capture);
        }

        if (!SttConfigService::isServerEnabled()) {
            $capture->stage = EncounterCapture::STAGE_STT_FAILED;
            $capture->last_error = 'La transcripción en servidor está deshabilitada.';
            $capture->attempts_stt = (int) $capture->attempts_stt + 1;
            $capture->updated_at = date('Y-m-d H:i:s');
            $capture->save(false);

            return $this->fail(400, $capture->last_error, $capture);
        }

        $modelo = (string) ($body['modelo'] ?? 'economico');
        $result = SpeechToTextManager::transcribir($absolute, $modelo);
        $texto = trim((string) ($result['texto'] ?? ''));

        $capture->attempts_stt = (int) $capture->attempts_stt + 1;
        $capture->updated_at = date('Y-m-d H:i:s');

        if ($texto === '') {
            $err = trim((string) ($result['error'] ?? 'No se pudo transcribir el audio.'));
            $capture->stage = EncounterCapture::STAGE_STT_FAILED;
            $capture->last_error = $err !== '' ? $err : 'No se pudo transcribir el audio.';
            $capture->save(false);

            return $this->fail(502, $capture->last_error, $capture);
        }

        $capture->transcript = $texto;
        $meta = $capture->getSttMeta();
        $meta['provenance'] = ClinicalSpeechInputResolver::PROVENANCE_SERVER;
        $meta['server_stt'] = [
            'confidence' => $result['confidence'] ?? null,
            'modelo_usado' => $result['modelo_usado'] ?? null,
        ];
        $capture->setSttMeta($meta);
        $capture->stage = EncounterCapture::STAGE_TRANSCRIBED;
        $capture->last_error = null;
        $capture->save(false);

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

        $textoOverride = trim((string) ($body['consulta'] ?? $body['texto'] ?? ''));
        if ($textoOverride !== '') {
            $capture->transcript = $textoOverride;
        }

        if (!$capture->hasTranscript()) {
            return $this->fail(400, 'No hay transcripción. Ejecute captura-transcribir o envíe texto.', $capture);
        }

        if ($capture->stage === EncounterCapture::STAGE_READY_FOR_REVIEW
            && $capture->getAnalysisResponse() !== []
            && empty($body['force'])
        ) {
            return $this->ok($capture, 'Análisis ya disponible.', true);
        }

        $analyzeBody = $body;
        $analyzeBody['consulta'] = $capture->transcript;
        $analyzeBody['id_persona'] = $capture->subject_persona_id;
        $analyzeBody['subject_persona_id'] = $capture->subject_persona_id;
        if ($capture->parent_type !== null) {
            $analyzeBody['parent'] = $capture->parent_type;
        }
        if ($capture->parent_id !== null) {
            $analyzeBody['parent_id'] = $capture->parent_id;
        }
        // No reenviar audio: el checkpoint es el transcript.
        unset($analyzeBody['audio'], $analyzeBody['audio_data'], $analyzeBody['file']);
        $analyzeBody['stt'] = array_merge($capture->getSttMeta(), [
            'text' => $capture->transcript,
            'force_server' => false,
        ]);

        $out = (new ConsultaProcesamientoService())->analizar($analyzeBody);
        $capture->attempts_analysis = (int) $capture->attempts_analysis + 1;
        $capture->updated_at = date('Y-m-d H:i:s');

        if (empty($out['success'])) {
            $msg = trim((string) ($out['message'] ?? 'Error al analizar la consulta.'));
            $capture->stage = EncounterCapture::STAGE_ANALYSIS_FAILED;
            $capture->last_error = $msg !== '' ? $msg : 'Error al analizar la consulta.';
            $capture->save(false);
            $status = (int) ($out['__statusCode'] ?? 500);

            return $this->fail($status > 0 ? $status : 500, $capture->last_error, $capture);
        }

        $capture->texto_procesado = isset($out['texto_procesado'])
            ? (string) $out['texto_procesado']
            : $capture->transcript;
        $extraidos = $out['datosExtraidos'] ?? $out['datos_extraidos'] ?? [];
        $capture->setDatosExtraidos(is_array($extraidos) ? $extraidos : []);
        $capture->analysis_cache_token = isset($out['analysis_cache_token'])
            ? (string) $out['analysis_cache_token']
            : null;
        $capture->setAnalysisResponse($out);
        $capture->encounter_id = isset($out['encounter_id'])
            ? (int) $out['encounter_id']
            : (isset($out['id_consulta']) ? (int) $out['id_consulta'] : $capture->encounter_id);

        $review = $out['capture_review'] ?? null;
        if (is_array($review) && isset($review['default_staged_item_ids']) && is_array($review['default_staged_item_ids'])) {
            $capture->setStagedItemIds(array_map('strval', $review['default_staged_item_ids']));
        }

        $capture->stage = EncounterCapture::STAGE_READY_FOR_REVIEW;
        $capture->last_error = null;
        $capture->save(false);

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
        if (!is_array($datosExtraidos)) {
            $datosExtraidos = $capture->getDatosExtraidos();
            $stagedIds = $capture->getStagedItemIds();
            if ($stagedIds !== [] && isset($analysis['capture_review']) && is_array($analysis['capture_review'])) {
                // Si el cliente no envió filtrado, usar extracción completa del análisis;
                // el filtrado por selección lo hace el cliente normalmente.
                $datosExtraidos = $capture->getDatosExtraidos();
            }
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
        if (!isset($saveBody['analisis_datos_extraidos']) && !isset($saveBody['analisisDatosExtraidos'])) {
            $saveBody['analisis_datos_extraidos'] = $capture->getDatosExtraidos();
        }
        if ($capture->encounter_id) {
            $saveBody['id_consulta'] = $capture->encounter_id;
            $saveBody['encounter_id'] = $capture->encounter_id;
        }
        if (isset($analysis['id_configuracion']) && !isset($saveBody['id_configuracion'])) {
            $saveBody['id_configuracion'] = $analysis['id_configuracion'];
        }

        $out = $this->documentation->guardar($saveBody);
        $capture->attempts_save = (int) $capture->attempts_save + 1;
        $capture->updated_at = date('Y-m-d H:i:s');
        $capture->setStagedItemIds($capture->getStagedItemIds());

        if (empty($out['success'])) {
            $msg = trim((string) ($out['message'] ?? 'Error al guardar.'));
            $capture->stage = EncounterCapture::STAGE_SAVE_FAILED;
            $capture->last_error = $msg !== '' ? $msg : 'Error al guardar.';
            $capture->save(false);
            $status = (int) ($out['__statusCode'] ?? 500);

            return $this->fail($status > 0 ? $status : 500, $capture->last_error, $capture, [
                'guardar' => $out,
            ]);
        }

        $capture->stage = EncounterCapture::STAGE_COMPLETED;
        $capture->last_error = null;
        if (isset($out['encounter_id'])) {
            $capture->encounter_id = (int) $out['encounter_id'];
        }
        $capture->save(false);

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
            $includeAnalysis = in_array($row->stage, [
                EncounterCapture::STAGE_READY_FOR_REVIEW,
                EncounterCapture::STAGE_SAVE_FAILED,
            ], true);
            $items[] = $this->toApiArray($row, $includeAnalysis);
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

        if ($capture->stage === EncounterCapture::STAGE_COMPLETED) {
            return $this->fail(409, 'No se puede descartar una captura ya completada.', $capture);
        }

        $this->deleteAudioFile($capture);
        $capture->stage = EncounterCapture::STAGE_DISCARDED;
        $capture->last_error = null;
        $capture->audio_relative_path = null;
        $capture->updated_at = date('Y-m-d H:i:s');
        $capture->save(false);

        return $this->ok($capture, 'Captura descartada.');
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
    private function persistUploadedAudio(EncounterCapture $capture, UploadedFile $file): ?array
    {
        if (!$file->tempName) {
            return $this->fail(400, 'Archivo de audio inválido.');
        }

        $ext = strtolower((string) ($file->getExtension() ?: pathinfo((string) $file->name, PATHINFO_EXTENSION)));
        if ($ext === '') {
            $ext = 'm4a';
        }
        if (!preg_match('/^[a-z0-9]+$/', $ext)) {
            return $this->fail(400, 'Extensión de audio no permitida.');
        }

        $dirRelative = self::AUDIO_DIR . '/' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $capture->client_capture_id);
        $basePath = Yii::getAlias('@frontend/web') . '/' . $dirRelative;
        if (!is_dir($basePath) && !@mkdir($basePath, 0755, true)) {
            return $this->fail(500, 'No se pudo crear el directorio de audio.');
        }

        $filename = 'audio_' . date('YmdHis') . '_' . uniqid() . '.' . $ext;
        $relative = $dirRelative . '/' . $filename;
        $fullPath = Yii::getAlias('@frontend/web') . '/' . $relative;

        if ($capture->hasAudio()) {
            $this->deleteAudioFile($capture);
        }

        if (!$file->saveAs($fullPath)) {
            return $this->fail(500, 'Error al guardar el archivo de audio.');
        }

        $capture->audio_relative_path = $relative;
        $capture->audio_mime = $file->type ?: $this->guessMime($ext);

        return null;
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
     * @return array<string, mixed>
     */
    public function toApiArray(EncounterCapture $capture, bool $includeAnalysis = false): array
    {
        $out = [
            'id' => (int) $capture->id,
            'client_capture_id' => $capture->client_capture_id,
            'subject_persona_id' => (int) $capture->subject_persona_id,
            'parent' => $capture->parent_type,
            'parent_id' => $capture->parent_id !== null ? (int) $capture->parent_id : null,
            'encounter_id' => $capture->encounter_id !== null ? (int) $capture->encounter_id : null,
            'stage' => $capture->stage,
            'has_audio' => $capture->hasAudio(),
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

        if ($includeAnalysis) {
            $out['datosExtraidos'] = $capture->getDatosExtraidos();
            $analysis = $capture->getAnalysisResponse();
            if ($analysis !== []) {
                $out['analysis'] = $analysis;
                // Compat: campos top-level que ya consume Flutter/web.
                foreach ([
                    'texto_original',
                    'texto_procesado',
                    'datosExtraidos',
                    'capture_review',
                    'analysis_cache_token',
                    'id_configuracion',
                    'encounter_id',
                    'id_consulta',
                    'success',
                ] as $key) {
                    if (array_key_exists($key, $analysis) && !array_key_exists($key, $out)) {
                        $out[$key] = $analysis[$key];
                    }
                }
            }
        }

        return $out;
    }
}
