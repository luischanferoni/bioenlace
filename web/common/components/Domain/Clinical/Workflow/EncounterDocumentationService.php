<?php

namespace common\components\Domain\Clinical\Workflow;

use common\components\Domain\Clinical\Service\CarePlanService;
use common\components\Domain\Clinical\Service\EncounterAutomaticCodingService;
use common\components\Domain\Clinical\Service\EncounterLifecycleService;
use common\components\Domain\Clinical\Service\MedicationRequestService;
use common\components\Domain\Clinical\Service\ServiceRequestService;
use common\components\Domain\Clinical\Specialty\EncounterDefinitionSpecialtyRegistry;
use common\components\Domain\Clinical\Specialty\Inpatient\InpatientEncounterAuxService;
use common\components\Domain\Clinical\Specialty\Odontology\OdontologyEncounterService;
use common\components\Domain\Clinical\Specialty\Ophthalmology\OphthalmologyEncounterService;
use common\components\Domain\Clinical\Legacy\ConsultaProcesamientoService;
use common\components\Domain\Clinical\Presentation\EncounterCaptureReviewPresenter;
use common\models\Clinical\Condition;
use common\models\Clinical\Encounter;
use common\models\Clinical\EncounterDefinition;
use common\models\DiagnosticoConsulta;
use common\models\Person\Persona;
use common\models\Scheduling\Turno;
use Yii;
use yii\base\Component;

/**
 * Captura y persistencia clínica sobre {@see Encounter} (reemplazo progresivo de ConsultaProcesamientoService).
 */
class EncounterDocumentationService extends Component
{
    private EncounterLifecycleService $lifecycle;
    private CarePlanService $carePlans;
    private MedicationRequestService $medications;
    private ServiceRequestService $serviceRequests;
    private OdontologyEncounterService $odontology;
    private OphthalmologyEncounterService $ophthalmology;
    private InpatientEncounterAuxService $inpatientAux;
    private EncounterDefinitionSpecialtyRegistry $specialtyRegistry;

    public function __construct(
        $config = [],
        EncounterLifecycleService $lifecycle = null,
        CarePlanService $carePlans = null,
        MedicationRequestService $medications = null,
        ServiceRequestService $serviceRequests = null,
        OdontologyEncounterService $odontology = null,
        OphthalmologyEncounterService $ophthalmology = null,
        InpatientEncounterAuxService $inpatientAux = null,
        EncounterDefinitionSpecialtyRegistry $specialtyRegistry = null
    ) {
        $this->lifecycle = $lifecycle ?? new EncounterLifecycleService();
        $this->carePlans = $carePlans ?? new CarePlanService();
        $this->medications = $medications ?? new MedicationRequestService($this->carePlans);
        $this->serviceRequests = $serviceRequests ?? new ServiceRequestService($this->carePlans);
        $this->odontology = $odontology ?? new OdontologyEncounterService($this->carePlans);
        $this->ophthalmology = $ophthalmology ?? new OphthalmologyEncounterService();
        $this->inpatientAux = $inpatientAux ?? new InpatientEncounterAuxService();
        $this->specialtyRegistry = $specialtyRegistry ?? new EncounterDefinitionSpecialtyRegistry();
        parent::__construct($config);
    }

    /**
     * Análisis IA — delega al pipeline existente (sin persistir en tablas legacy).
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function analizar(array $body): array
    {
        return (new ConsultaProcesamientoService())->analizar($body);
    }

    /**
     * Análisis IA sobre texto ya transcrito/procesado (sin resolver STT).
     *
     * @return array<string, mixed>
     */
    public function analizarTextoProcesado(
        string $textoProcesado,
        ?string $nombreServicio,
        $idConfiguracion,
        ?int $subjectPersonaId = null
    ): array {
        $legacy = new ConsultaProcesamientoService();

        return $legacy->analizarConsultaConIA(
            $textoProcesado,
            $nombreServicio,
            $legacy->getModelosPorConfiguracion($idConfiguracion),
            $subjectPersonaId
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function guardar(array $body): array
    {
        $logger = null;
        $diagnostico = [
            'staged_keys' => [],
            'staged_counts' => [],
            'backup_fuentes' => [],
            'final_keys' => [],
            'final_counts' => [],
            'por_modelo' => [],
            'cache' => null,
        ];
        try {
            $idConfiguracion = $body['id_configuracion'] ?? null;
            if (is_string($idConfiguracion) && trim($idConfiguracion) !== '') {
                $idConfiguracion = (int) $idConfiguracion;
            }
            $idPersona = $this->lifecycle->resolveSubjectPersonaId($body);
            $datosExtraidos = $body['datosExtraidos'] ?? [];
            if (is_string($datosExtraidos)) {
                $decoded = json_decode($datosExtraidos, true);
                $datosExtraidos = is_array($decoded) ? $decoded : [];
            }
            $encounterId = $this->normalizeEncounterIdFromBody($body, $idConfiguracion);

            $notePreview = $this->resolveCaptureNote($body) ?? '';
            $logger = EncounterGuardarLogger::iniciar($notePreview !== '' ? $notePreview : '(sin nota)', [
                'id_persona' => $idPersona,
                'encounter_id' => $encounterId,
                'parent' => $body['parent'] ?? null,
                'parent_id' => $body['parent_id'] ?? null,
                'id_configuracion' => $idConfiguracion,
            ]);
            $logger->registrar('REQUEST', null, [
                'body_keys' => array_keys($body),
                'has_analisis_datos_extraidos' => array_key_exists('analisis_datos_extraidos', $body)
                    || array_key_exists('analisisDatosExtraidos', $body),
                'has_analysis_cache_token' => trim((string) ($body['analysis_cache_token'] ?? $body['analisis_cache_token'] ?? '')) !== '',
                'datosExtraidos_type' => gettype($body['datosExtraidos'] ?? null),
            ], ['metodo' => 'EncounterDocumentationService::guardar']);

            $blockingError = EncounterCaptureReviewPresenter::blockingErrorFromExtraidos($datosExtraidos);
            if ($blockingError !== null) {
                $message = trim((string) ($blockingError['texto'] ?? ''));
                if ($message === '') {
                    $message = 'No se puede guardar: el análisis tiene errores.';
                }
                $out = $this->error(400, $message, [
                    'tipo' => $blockingError['tipo'] ?? 'error_sistema',
                ]);
                $logger->finalizar($out);

                return $out;
            }

            // Defensa: no perder categories si el cliente envió mapas anidados o string.
            if ($datosExtraidos !== [] && !self::datosExtraidosLooksLikeCategories($datosExtraidos)) {
                $inner = $datosExtraidos['datosExtraidos'] ?? null;
                if (is_array($inner)) {
                    $datosExtraidos = $inner;
                }
            }

            $diagnostico['staged_keys'] = array_keys($datosExtraidos);
            $diagnostico['staged_counts'] = self::countCategories($datosExtraidos);
            $logger->registrar('STAGE', null, [
                'keys' => $diagnostico['staged_keys'],
                'counts' => $diagnostico['staged_counts'],
            ], ['metodo' => 'datosExtraidos staged']);

            // Si el stage quedó incompleto, completar con el análisis completo (backup cliente + cache servidor).
            $fullMeta = $this->resolveFullAnalysisExtraidosWithMeta($body);
            $fullExtraidos = $fullMeta['extraidos'];
            $diagnostico['backup_fuentes'] = $fullMeta['fuentes'];
            $diagnostico['cache'] = $fullMeta['cache'];
            $logger->registrar('BACKUP', null, [
                'fuentes' => $fullMeta['fuentes'],
                'cache' => $fullMeta['cache'],
                'counts' => self::countCategories($fullExtraidos),
            ], ['metodo' => 'resolveFullAnalysisExtraidos']);

            if ($fullExtraidos !== []) {
                $datosExtraidos = self::enrichExtraidosFromFullAnalysis($datosExtraidos, $fullExtraidos);
            }

            $diagnostico['final_keys'] = array_keys($datosExtraidos);
            $diagnostico['final_counts'] = self::countCategories($datosExtraidos);
            $logger->registrar('FINAL_EXTRAIDOS', null, [
                'keys' => $diagnostico['final_keys'],
                'counts' => $diagnostico['final_counts'],
                'payload_preview' => self::previewExtraidos($datosExtraidos),
            ], ['metodo' => 'tras enrich']);

            Yii::info(
                'encounter.guardar categorias=' . implode(',', array_keys($datosExtraidos))
                . ' note_body=' . ($this->resolveCaptureNote($body) !== null ? 'si' : 'no')
                . ' full_backup=' . ($fullExtraidos !== [] ? 'si' : 'no')
                . ' fuentes=' . implode(',', $fullMeta['fuentes']),
                'encounter-doc'
            );

            if (!$idConfiguracion) {
                $definition = (new EncounterDefinitionBootstrapService())->resolveFromCaptureBody(
                    $body,
                    $idPersona
                );
                if ($definition !== null) {
                    $idConfiguracion = $definition->id;
                }
            }

            if (!$idConfiguracion || !$idPersona) {
                $out = $this->error(400, $this->missingCaptureContextMessage($idConfiguracion, $idPersona), [
                    'id_configuracion' => $idConfiguracion ? 'ok' : 'falta',
                    'id_persona' => $idPersona ? 'ok' : 'falta',
                ]);
                $out['diagnostico_guardar'] = $diagnostico;
                $logger->finalizar($out);

                return $out;
            }

            $configuracion = EncounterDefinition::findOne($idConfiguracion);
            if (!$configuracion) {
                $out = $this->error(400, 'Configuración de encounter no encontrada.');
                $out['diagnostico_guardar'] = $diagnostico;
                $logger->finalizar($out);

                return $out;
            }

            $paciente = $this->lifecycle->findSubject((int) $idPersona);
            if (!$paciente) {
                $out = $this->error(400, 'Paciente no encontrado.');
                $out['diagnostico_guardar'] = $diagnostico;
                $logger->finalizar($out);

                return $out;
            }

            $tx = Yii::$app->db->beginTransaction();
            try {
                $encounter = $this->resolveEncounter($encounterId, $body, $paciente, $configuracion);
                $diagnostico['por_modelo'] = $this->persistExtractedData($encounter, $configuracion, $datosExtraidos, $logger);
                EncounterAutomaticCodingService::codeAndPersistForEncounter($encounter, $datosExtraidos, $configuracion);
                $encounter = $this->lifecycle->onCaptureDocumented($encounter);
                // finalize() solo toca status/period_end; reasegurar note tras el ciclo.
                $this->forcePersistCaptureNote($encounter, $body);

                $tx->commit();

                $encounter->refresh();

                $persistido = [
                    'note' => trim((string) ($encounter->note ?? '')) !== '',
                    'reason_text' => trim((string) ($encounter->reason_text ?? '')) !== '',
                    'reason_text_value' => mb_substr(trim((string) ($encounter->reason_text ?? '')), 0, 120),
                    'categorias' => array_keys($datosExtraidos),
                    'conditions' => (int) \common\models\Clinical\Condition::find()
                        ->where(['encounter_id' => $encounter->id, 'deleted_at' => null])
                        ->count(),
                    'medication_requests' => (int) \common\models\Clinical\MedicationRequest::find()
                        ->where(['encounter_id' => $encounter->id, 'deleted_at' => null])
                        ->count(),
                    'service_requests' => (int) \common\models\Clinical\ServiceRequest::find()
                        ->where(['encounter_id' => $encounter->id, 'deleted_at' => null])
                        ->count(),
                    'care_plans' => (int) \common\models\Clinical\CarePlan::find()
                        ->where(['encounter_id' => $encounter->id, 'deleted_at' => null])
                        ->count(),
                ];

                $out = [
                    '__statusCode' => 200,
                    'success' => true,
                    'message' => 'Encounter guardado correctamente.',
                    'encounter_id' => $encounter->id,
                    'id_consulta' => $encounter->id,
                    'persistido' => $persistido,
                    'diagnostico_guardar' => $diagnostico,
                    'log_id' => $logger->getId(),
                    'log_archivo' => $logger->getArchivoLog(),
                    'persist_incomplete' => empty($persistido['note'])
                        || (
                            (int) ($persistido['medication_requests'] ?? 0) <= 0
                            && !empty($diagnostico['final_counts']['Medicación'])
                        )
                        || (
                            empty($persistido['reason_text'])
                            && !empty($diagnostico['final_counts']['Motivos de consulta'])
                        ),
                ];
                if (!empty($out['persist_incomplete'])) {
                    $out['message'] = 'Encounter guardado con datos incompletos. Revisá medicación/motivos/indicaciones.';
                }
                $logger->finalizar($out);

                return $out;
            } catch (\Throwable $e) {
                $tx->rollBack();
                throw $e;
            }
        } catch (\Throwable $e) {
            Yii::error($e->getMessage(), __METHOD__);
            $out = $this->error(500, 'Error al guardar encounter: ' . $e->getMessage());
            $out['diagnostico_guardar'] = $diagnostico;
            if ($logger !== null) {
                $logger->registrar('ERROR', null, $e->getMessage() . "\n" . $e->getTraceAsString(), [
                    'metodo' => __METHOD__,
                ]);
                $logger->finalizar($out);
            }

            return $out;
        }
    }

    /**
     * @param array<string, mixed> $datosExtraidos
     */
    private static function datosExtraidosLooksLikeCategories(array $datosExtraidos): bool
    {
        foreach ($datosExtraidos as $key => $value) {
            if (!is_string($key) || $key === '' || $key === 'Error') {
                continue;
            }
            if (is_array($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $body
     * @return array{
     *   extraidos: array<string, mixed>,
     *   fuentes: list<string>,
     *   cache: array<string, mixed>|null
     * }
     */
    private function resolveFullAnalysisExtraidosWithMeta(array $body): array
    {
        $merged = [];
        $fuentes = [];
        $candidates = [];
        $cacheMeta = null;

        if (array_key_exists('analisis_datos_extraidos', $body) || array_key_exists('analisisDatosExtraidos', $body)) {
            $candidates[] = [
                'fuente' => 'client_analisis_datos_extraidos',
                'raw' => $body['analisis_datos_extraidos'] ?? $body['analisisDatosExtraidos'] ?? null,
            ];
        }
        $datos = $body['datos'] ?? null;
        if (is_array($datos)) {
            $candidates[] = [
                'fuente' => 'client_datos',
                'raw' => $datos['datosExtraidos'] ?? $datos,
            ];
        }

        $note = $this->resolveCaptureNote($body) ?? '';
        $cacheHit = EncounterCaptureAnalysisCache::recallWithMeta($body, $note !== '' ? $note : null);
        $cacheMeta = [
            'fuente' => $cacheHit['fuente'] ?? 'none',
            'token' => $cacheHit['token'] ?? null,
            'counts' => self::countCategories($cacheHit['extraidos'] ?? []),
        ];
        if (($cacheHit['extraidos'] ?? []) !== []) {
            // Preferir cache/DB servidor al inicio: el backup del cliente puede venir truncado.
            array_unshift($candidates, [
                'fuente' => 'server_' . ($cacheHit['fuente'] ?? 'cache'),
                'raw' => $cacheHit['extraidos'],
            ]);
        }

        foreach ($candidates as $candidate) {
            $raw = $candidate['raw'];
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $raw = is_array($decoded) ? $decoded : null;
            }
            if (!is_array($raw) || $raw === []) {
                continue;
            }
            if (isset($raw['datosExtraidos']) && is_array($raw['datosExtraidos'])) {
                $raw = $raw['datosExtraidos'];
            }
            if (!self::datosExtraidosLooksLikeCategories($raw)) {
                continue;
            }
            $before = self::countCategories($merged);
            $merged = self::enrichExtraidosFromFullAnalysis($merged, $raw);
            $after = self::countCategories($merged);
            if ($after !== $before) {
                $fuentes[] = (string) $candidate['fuente'];
            }
        }

        return [
            'extraidos' => $merged,
            'fuentes' => array_values(array_unique($fuentes)),
            'cache' => $cacheMeta,
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function resolveFullAnalysisExtraidos(array $body): array
    {
        return $this->resolveFullAnalysisExtraidosWithMeta($body)['extraidos'];
    }

    /**
     * @param array<string, mixed> $extraidos
     * @return array<string, int>
     */
    private static function countCategories(array $extraidos): array
    {
        $counts = [];
        foreach ($extraidos as $key => $rows) {
            if (!is_string($key) || $key === '' || $key === 'Error') {
                continue;
            }
            $counts[$key] = self::countExtractionRows($rows);
        }

        return $counts;
    }

    /**
     * @param array<string, mixed> $extraidos
     * @return array<string, mixed>
     */
    private static function previewExtraidos(array $extraidos): array
    {
        $out = [];
        foreach ($extraidos as $key => $rows) {
            if (!is_string($key) || $key === 'Error') {
                continue;
            }
            if (!is_array($rows)) {
                $out[$key] = $rows;
                continue;
            }
            if (self::isListArray($rows)) {
                $out[$key] = array_slice($rows, 0, 3);
            } else {
                $out[$key] = $rows;
            }
        }

        return $out;
    }

    /**
     * Completa categorías ausentes/vacías/truncadas del stage con el análisis completo.
     *
     * @param array<string, mixed> $staged
     * @param array<string, mixed> $full
     * @return array<string, mixed>
     */
    private static function enrichExtraidosFromFullAnalysis(array $staged, array $full): array
    {
        $stagedByNorm = [];
        foreach ($staged as $key => $_) {
            if (is_string($key) && $key !== '') {
                $stagedByNorm[self::normalizeExtractionKeyStatic($key)] = $key;
            }
        }

        foreach ($full as $key => $value) {
            if (!is_string($key) || $key === '' || $key === 'Error') {
                continue;
            }
            if (!is_array($value) || $value === []) {
                continue;
            }
            $norm = self::normalizeExtractionKeyStatic($key);
            $stagedKey = $stagedByNorm[$norm] ?? $key;
            $current = $staged[$stagedKey] ?? $staged[$key] ?? null;
            if ($current === null || $current === [] || $current === '') {
                $staged[$key] = $value;
                continue;
            }
            // Stage truncado (solo diagnóstico) vs análisis completo: preferir el más rico.
            if (self::countExtractionRows($value) > self::countExtractionRows($current)) {
                $staged[$key] = $value;
            }
        }

        return $staged;
    }

    /**
     * @param mixed $payload
     */
    private static function countExtractionRows($payload): int
    {
        if (!is_array($payload) || $payload === []) {
            return 0;
        }
        if (self::isListArray($payload)) {
            return count($payload);
        }

        return 1;
    }

    /**
     * @param array<mixed> $arr
     */
    private static function isListArray(array $arr): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($arr);
        }
        $i = 0;
        foreach ($arr as $k => $_) {
            if ($k !== $i) {
                return false;
            }
            $i++;
        }

        return true;
    }

    private function normalizeExtractionKey(string $key): string
    {
        return self::normalizeExtractionKeyStatic($key);
    }

    private static function normalizeExtractionKeyStatic(string $key): string
    {
        $folded = strtr(mb_strtolower(trim($key), 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/', '', $folded) ?? $folded;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function resolveEncounter(
        ?int $encounterId,
        array $body,
        Persona $paciente,
        EncounterDefinition $configuracion
    ): Encounter {
        $encounter = null;
        if ($encounterId !== null && $encounterId > 0) {
            $encounter = Encounter::findOne($encounterId);
        }
        if ($encounter === null) {
            $encounter = $this->resolveEncounterForParent($body, $paciente);
        }
        if ($encounter === null) {
            $encounter = $this->createEncounterForCapture($body, $paciente);
        }

        $this->applyCaptureTextToEncounter($encounter, $body);
        if (!$encounter->save(false)) {
            throw new \RuntimeException('No se pudo actualizar el encounter: ' . json_encode($encounter->getErrors()));
        }
        // Defensa: si save(false) no escribió la nota (p. ej. dirty attrs), forzar UPDATE.
        $this->forcePersistCaptureNote($encounter, $body);

        $this->assertEncounterPersisted($encounter);

        return $encounter;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function resolveEncounterForParent(array $body, Persona $paciente): ?Encounter
    {
        $parent = strtoupper(trim((string) ($body['parent'] ?? '')));
        $parentId = (int) ($body['parent_id'] ?? 0);
        if ($parentId <= 0) {
            return null;
        }

        if ($parent === Encounter::PARENT_TURNO) {
            $turno = Turno::findOne($parentId);
            if (
                $turno !== null
                && (int) $turno->id_persona === (int) $paciente->id_persona
            ) {
                return $this->lifecycle->ensureFromTurno($turno);
            }
        }

        $existing = Encounter::find()
            ->where([
                'parent_id' => $parentId,
                'subject_persona_id' => (int) $paciente->id_persona,
                'deleted_at' => null,
            ])
            ->andWhere([
                'or',
                ['parent_type' => $parent],
                ['parent_type' => Encounter::PARENT_CLASSES[$parent] ?? '__none__'],
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $existing instanceof Encounter ? $existing : null;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function createEncounterForCapture(array $body, Persona $paciente): Encounter
    {
        $encounterClass = ClinicalOperationalContextResolver::resolveEncounterClass($body);
        [$idPes, $idServicio] = array_slice(ClinicalOperationalContextResolver::resolve($body), 0, 2);
        $efectorId = Yii::$app->user->getIdEfector();
        if (($efectorId === null || $efectorId === '' || (int) $efectorId <= 0) && $idPes > 0) {
            $pes = \common\models\ProfesionalEfectorServicio::findOne($idPes);
            if ($pes !== null && (int) $pes->id_efector > 0) {
                $efectorId = (int) $pes->id_efector;
            }
        }

        $parentKey = strtoupper(trim((string) ($body['parent'] ?? '')));
        $parentId = (int) ($body['parent_id'] ?? 0);
        $appointmentId = null;
        if ($parentKey === Encounter::PARENT_TURNO && $parentId > 0) {
            $appointmentId = $parentId;
        }

        return $this->lifecycle->start([
            'subject_persona_id' => $paciente->id_persona,
            'encounter_class' => $encounterClass,
            'service_id' => $idServicio ?: Yii::$app->user->getServicioActual(),
            'efector_id' => $efectorId ?: null,
            'appointment_id' => $appointmentId,
            'id_profesional_efector_servicio' => $idPes > 0 ? $idPes : Yii::$app->user->getIdProfesionalEfectorServicio(),
            'parent_type' => $parentKey !== '' ? $parentKey : null,
            'parent_id' => $parentId > 0 ? $parentId : null,
            'reason_text' => $body['motivo_consulta'] ?? $body['consulta_inicial'] ?? $body['texto_original'] ?? null,
            'note' => $body['texto_procesado'] ?? $body['observacion'] ?? null,
            'workflow_step' => 0,
        ]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function applyCaptureTextToEncounter(Encounter $encounter, array $body): void
    {
        // Preferir texto procesado; si viene vacío (FormData con clave presente), caer a original.
        $note = $this->resolveCaptureNote($body);
        if ($note !== null) {
            $encounter->note = $note;
        }
        // Motivos: solo cuerpo tipado (motivo_consulta) o lo que persista ConsultaMotivos.
        // No volcar el texto clínico completo en reason_text (confunde con Motivos).
        $motivo = $this->resolveNonEmptyBodyText($body, ['motivo_consulta']);
        if ($motivo !== null) {
            $encounter->reason_text = $motivo;
        }
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string> $keys
     */
    private function resolveNonEmptyBodyText(array $body, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $body) || $body[$key] === null) {
                continue;
            }
            $text = trim(is_string($body[$key]) ? $body[$key] : (string) $body[$key]);
            if ($text === '' || strcasecmp($text, 'null') === 0) {
                continue;
            }

            return $text;
        }

        return null;
    }

    /**
     * Nota clínica del guardar: claves top-level + capture_review anidado.
     *
     * @param array<string, mixed> $body
     */
    private function resolveCaptureNote(array $body): ?string
    {
        $direct = $this->resolveNonEmptyBodyText($body, [
            'texto_procesado',
            'observacion',
            'texto_original',
            'consulta',
            'note',
        ]);
        if ($direct !== null) {
            return $direct;
        }

        $review = $body['capture_review'] ?? null;
        if (is_array($review)) {
            $fromReview = $this->resolveNonEmptyBodyText($review, [
                'texto_procesado',
                'texto_original',
            ]);
            if ($fromReview !== null) {
                return $fromReview;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function forcePersistCaptureNote(Encounter $encounter, array $body): void
    {
        $note = $this->resolveCaptureNote($body);
        if ($note === null) {
            return;
        }
        $current = trim((string) ($encounter->note ?? ''));
        if ($current === $note) {
            // Confirmar en BD por si el AR no flusheó el atributo.
            $dbNote = Encounter::find()
                ->select(['note'])
                ->where(['id' => (int) $encounter->id])
                ->scalar();
            if (is_string($dbNote) && trim($dbNote) === $note) {
                return;
            }
        }
        Encounter::updateAll(
            ['note' => $note, 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => (int) $encounter->id]
        );
        $encounter->note = $note;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function normalizeEncounterIdFromBody(array $body, $idConfiguracion): ?int
    {
        $candidates = [];
        foreach (['encounter_id', 'id_consulta'] as $key) {
            if (!array_key_exists($key, $body)) {
                continue;
            }
            $raw = $body[$key];
            if ($raw === null || $raw === '') {
                continue;
            }
            $id = (int) $raw;
            if ($id <= 0) {
                continue;
            }
            if ($idConfiguracion !== null && (int) $idConfiguracion === $id) {
                continue;
            }
            $candidates[] = $id;
        }

        foreach ($candidates as $id) {
            if (Encounter::find()->where(['id' => $id])->exists()) {
                return $id;
            }
        }

        return null;
    }

    private function assertEncounterPersisted(Encounter $encounter): void
    {
        $id = (int) ($encounter->id ?? 0);
        if ($id <= 0 || !Encounter::find()->where(['id' => $id])->exists()) {
            throw new \RuntimeException('Encounter no persistido antes de guardar datos clínicos.');
        }
    }

    /**
     * @param array<string, mixed> $datosExtraidos
     * @return array<string, array<string, mixed>>
     */
    private function persistExtractedData(
        Encounter $encounter,
        EncounterDefinition $configuracion,
        array $datosExtraidos,
        ?EncounterGuardarLogger $logger = null
    ): array {
        $this->assertEncounterPersisted($encounter);
        $categorias = EncounterDefinition::getCategoriasParaPrompt($configuracion);
        $carePlan = null;
        $stats = [];

        foreach ($categorias as $categoria) {
            $modelo = (string) ($categoria['modelo'] ?? '');
            $titulo = (string) ($categoria['titulo'] ?? '');
            if ($modelo === '') {
                continue;
            }
            $stat = [
                'titulo' => $titulo,
                'payload' => false,
                'rows' => 0,
                'accion' => 'skip',
                'detalle' => null,
            ];
            $payload = $this->resolvePayloadForCategoria($datosExtraidos, $categoria);
            if ($payload === null) {
                $stat['detalle'] = 'sin payload (keys=' . implode(',', array_keys($datosExtraidos)) . ')';
                $stats[$modelo] = $stat;
                $logger?->registrar('PERSIST', null, $stat, ['metodo' => $modelo]);
                Yii::info(
                    'encounter.guardar sin payload para modelo=' . $modelo
                    . ' titulo=' . $titulo
                    . ' keys=' . implode(',', array_keys($datosExtraidos)),
                    'encounter-doc'
                );
                continue;
            }
            $stat['payload'] = true;
            $stat['rows'] = self::countExtractionRows($payload);
            if (!$this->specialtyRegistry->isModelAllowed($configuracion, $modelo)) {
                $stat['accion'] = 'blocked_specialty';
                $stats[$modelo] = $stat;
                $logger?->registrar('PERSIST', null, $stat, ['metodo' => $modelo]);
                continue;
            }

            switch ($modelo) {
                case 'ConsultaMotivos':
                    $this->persistMotivos($encounter, $payload);
                    $stat['accion'] = 'motivos';
                    $stat['detalle'] = 'reason_text=' . mb_substr(trim((string) ($encounter->reason_text ?? '')), 0, 80);
                    break;
                case 'DiagnosticoConsulta':
                    $this->persistConditions($encounter, $payload);
                    $stat['accion'] = 'conditions_sin_codigo_omitidas_coding_auto';
                    break;
                case 'ConsultaMedicamentos':
                    $medicationRows = MedicationRequestService::normalizeExtractedMedicationPayload($payload);
                    $stat['rows'] = count($medicationRows);
                    if ($medicationRows === []) {
                        $stat['accion'] = 'medicacion_sin_filas';
                        $stat['detalle'] = 'payload_type=' . gettype($payload);
                        Yii::warning(
                            'encounter.guardar Medicación/ConsultaMedicamentos sin filas normalizables. payload_type='
                            . gettype($payload),
                            'encounter-doc'
                        );
                        break;
                    }
                    try {
                        $carePlan = $carePlan ?? $this->carePlans->createAcutePlanForEncounter(
                            (int) $encounter->subject_persona_id,
                            (int) $encounter->id
                        );
                        $stat['care_plan_id'] = $carePlan->id ?? null;
                    } catch (\Throwable $e) {
                        $stat['detalle'] = 'care_plan_error=' . $e->getMessage();
                        Yii::error('CarePlan acute no creado: ' . $e->getMessage(), 'encounter-doc');
                    }
                    $before = (int) \common\models\Clinical\MedicationRequest::find()
                        ->where(['encounter_id' => $encounter->id, 'deleted_at' => null])
                        ->count();
                    $this->persistMedications($encounter, $carePlan, $medicationRows);
                    $after = (int) \common\models\Clinical\MedicationRequest::find()
                        ->where(['encounter_id' => $encounter->id, 'deleted_at' => null])
                        ->count();
                    $stat['accion'] = 'medicacion';
                    $stat['created'] = max(0, $after - $before);
                    break;
                case 'ConsultaPracticas':
                case 'ConsultaIndicaciones':
                    $rows = is_array($payload) ? $payload : [];
                    if ($rows !== []) {
                        try {
                            $carePlan = $carePlan ?? $this->carePlans->createAcutePlanForEncounter(
                                (int) $encounter->subject_persona_id,
                                (int) $encounter->id
                            );
                            $stat['care_plan_id'] = $carePlan->id ?? null;
                        } catch (\Throwable $e) {
                            $stat['detalle'] = 'care_plan_error=' . $e->getMessage();
                            Yii::error('CarePlan acute no creado: ' . $e->getMessage(), 'encounter-doc');
                        }
                    }
                    $before = (int) \common\models\Clinical\ServiceRequest::find()
                        ->where(['encounter_id' => $encounter->id, 'deleted_at' => null])
                        ->count();
                    $this->persistServiceRequests($encounter, $payload, $modelo, $carePlan);
                    $after = (int) \common\models\Clinical\ServiceRequest::find()
                        ->where(['encounter_id' => $encounter->id, 'deleted_at' => null])
                        ->count();
                    $stat['accion'] = $modelo === 'ConsultaIndicaciones' ? 'indicaciones' : 'practicas';
                    $stat['created'] = max(0, $after - $before);
                    break;
                case 'ConsultaDerivaciones':
                    $this->persistServiceRequests($encounter, $payload, $modelo, null);
                    $stat['accion'] = 'derivaciones';
                    break;
                case 'ConsultaOdontologiaPracticas':
                    $carePlan = $this->odontology->persistPractices($encounter, $payload, $carePlan);
                    $stat['accion'] = 'odontologia_practicas';
                    break;
                case 'ConsultaOdontologiaDiagnosticos':
                    $this->odontology->persistDiagnostics($encounter, $payload);
                    $stat['accion'] = 'odontologia_dx';
                    break;
                case 'ConsultaOdontologiaEstados':
                    $this->odontology->persistToothStates($encounter, $payload);
                    $stat['accion'] = 'odontologia_estados';
                    break;
                case 'ConsultaPracticasOftalmologia':
                case 'ConsultaPracticasOftalmologiaEstudios':
                    $this->ophthalmology->persistPractices($encounter, $payload);
                    $stat['accion'] = 'oftalmologia';
                    break;
                case 'ConsultasRecetaLentes':
                    $this->ophthalmology->persistLensPrescription($encounter, $payload);
                    $stat['accion'] = 'receta_lentes';
                    break;
                case 'ConsultaBalanceHidrico':
                    $this->persistFluidBalances($encounter, $payload);
                    $stat['accion'] = 'balance_hidrico';
                    break;
                case 'ConsultaRegimen':
                    $this->persistRegimens($encounter, $payload);
                    $stat['accion'] = 'regimen';
                    break;
                case 'ConsultaSuministroMedicamento':
                    $this->persistMedicationSupplies($encounter, $payload);
                    $stat['accion'] = 'suministro';
                    break;
                default:
                    $stat['accion'] = 'modelo_no_manejado';
                    break;
            }

            $stats[$modelo] = $stat;
            $logger?->registrar('PERSIST', null, $stat, ['metodo' => $modelo]);
        }

        return $stats;
    }

    /**
     * @param array<string, mixed> $datosExtraidos
     * @param array<string, mixed> $categoria
     * @return mixed|null
     */
    private function resolvePayloadForCategoria(array $datosExtraidos, array $categoria)
    {
        $modelo = trim((string) ($categoria['modelo'] ?? ''));
        $titulo = trim((string) ($categoria['titulo'] ?? ''));
        foreach ([$titulo, $modelo] as $key) {
            if ($key !== '' && array_key_exists($key, $datosExtraidos)) {
                return $datosExtraidos[$key];
            }
        }

        // Alias sin acentos / case (p. ej. Medicacion vs Medicación).
        $candidates = array_values(array_filter([$titulo, $modelo], static function ($k) {
            return $k !== '';
        }));
        if ($candidates === []) {
            return null;
        }
        $normalizedKeys = [];
        foreach ($candidates as $key) {
            $normalizedKeys[$this->normalizeExtractionKey($key)] = true;
        }
        foreach ($datosExtraidos as $k => $value) {
            if (!is_string($k)) {
                continue;
            }
            if (isset($normalizedKeys[$this->normalizeExtractionKey($k)])) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param mixed $payload
     */
    private function persistMotivos(Encounter $encounter, $payload): void
    {
        if (!is_array($payload)) {
            return;
        }
        $parts = [];
        foreach ($payload as $row) {
            if (is_string($row)) {
                $text = trim($row);
            } elseif (is_array($row)) {
                $text = trim((string) (
                    $row['texto']
                    ?? $row['termino']
                    ?? $row['descripcion']
                    ?? $row['label']
                    ?? $row['display']
                    ?? ''
                ));
            } else {
                continue;
            }
            if ($text !== '') {
                $parts[] = $text;
            }
        }
        if ($parts === []) {
            return;
        }
        $joined = implode('; ', $parts);
        $current = trim((string) ($encounter->reason_text ?? ''));
        $encounter->reason_text = $current === '' ? $joined : ($current . "\n" . $joined);
        $encounter->save(false, ['reason_text', 'updated_at', 'updated_by']);
    }

    /**
     * @param mixed $payload
     */
    private function persistConditions(Encounter $encounter, $payload): void
    {
        if (!is_array($payload)) {
            return;
        }
        foreach ($payload as $row) {
            $condition = new Condition();
            $condition->encounter_id = $encounter->id;
            $condition->subject_persona_id = $encounter->subject_persona_id;
            if (is_array($row)) {
                $condition->code = (string) ($row['codigo'] ?? $row['codigo_cie10'] ?? $row['cie10'] ?? '');
                $condition->display = $row['termino']
                    ?? $row['descripcion']
                    ?? $row['texto']
                    ?? $row['label']
                    ?? $row['display']
                    ?? null;
                $condition->clinical_status = $row['condition_clinical_status']
                    ?? DiagnosticoConsulta::CLINICAL_STATUS_ACTIVE;
                $condition->verification_status = $row['condition_verification_status']
                    ?? DiagnosticoConsulta::VERIFICATION_STATUS_CONFIRMED;
            } else {
                $condition->code = '';
                $condition->display = trim((string) $row);
                $condition->clinical_status = DiagnosticoConsulta::CLINICAL_STATUS_ACTIVE;
                $condition->verification_status = DiagnosticoConsulta::VERIFICATION_STATUS_CONFIRMED;
            }
            // Sin código: la codificación automática completa Condition; no persistimos fila huérfana.
            if ($condition->code === '') {
                continue;
            }
            if ($condition->display === null || trim((string) $condition->display) === '') {
                $condition->display = $condition->code;
            }
            $condition->recorded_date = date('Y-m-d H:i:s');
            $condition->save(false);
        }
    }

    /**
     * @param mixed $payload
     */
    private function persistMedications(Encounter $encounter, ?\common\models\Clinical\CarePlan $carePlan, $payload): void
    {
        $rows = MedicationRequestService::normalizeExtractedMedicationPayload($payload);
        foreach ($rows as $row) {
            try {
                $this->medications->createFromExtractedRow($encounter, $carePlan, $row);
            } catch (\InvalidArgumentException $e) {
                Yii::info('Skip medication vacío: ' . $e->getMessage(), 'encounter-doc');
            }
        }
    }

    /**
     * @param mixed $payload
     */
    private function persistFluidBalances(Encounter $encounter, $payload): void
    {
        if (!is_array($payload)) {
            return;
        }
        foreach ($payload as $row) {
            if (!is_array($row)) {
                continue;
            }
            $this->inpatientAux->persistFluidBalanceRow($encounter, $row);
        }
    }

    /**
     * @param mixed $payload
     */
    private function persistRegimens(Encounter $encounter, $payload): void
    {
        if (!is_array($payload)) {
            return;
        }
        foreach ($payload as $row) {
            if (!is_array($row)) {
                continue;
            }
            $this->inpatientAux->persistRegimenRow($encounter, $row);
        }
    }

    /**
     * @param mixed $payload
     */
    private function persistMedicationSupplies(Encounter $encounter, $payload): void
    {
        if (!is_array($payload)) {
            return;
        }
        foreach ($payload as $row) {
            if (!is_array($row)) {
                continue;
            }
            $this->inpatientAux->persistMedicationSupplyRow($encounter, $row);
        }
    }

    /**
     * @param mixed $payload
     */
    private function persistServiceRequests(
        Encounter $encounter,
        $payload,
        string $modelo,
        ?\common\models\Clinical\CarePlan $carePlan = null
    ): void {
        if (!is_array($payload)) {
            return;
        }
        foreach ($payload as $row) {
            try {
                $this->serviceRequests->createFromExtractedRow($encounter, $row, $modelo, $carePlan);
            } catch (\InvalidArgumentException $e) {
                Yii::info('Skip service request vacío: ' . $e->getMessage(), 'encounter-doc');
            }
        }
    }

    /**
     * @param array<string, mixed>|null $errors
     * @return array<string, mixed>
     */
    private function error(int $code, string $message, ?array $errors = null): array
    {
        return [
            '__statusCode' => $code,
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ];
    }

    /**
     * @param int|string|null $idConfiguracion
     */
    private function missingCaptureContextMessage($idConfiguracion, ?int $idPersona): string
    {
        $parts = [];
        if (!$idConfiguracion) {
            $parts[] = 'id_configuracion (definición de encounter para el servicio)';
        }
        if (!$idPersona) {
            $parts[] = 'id_persona o subject_persona_id';
        }

        return 'Faltan datos de contexto para guardar: ' . implode(' y ', $parts) . '.';
    }
}
