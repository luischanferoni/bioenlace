<?php

namespace common\components\Domain\Clinical\Workflow;

use common\components\Domain\Clinical\Emergency\Service\GuardiaEncounterOutcomeService;
use common\components\Domain\Clinical\Capture\ClinicalCaptureResolutionApplier;
use common\components\Domain\Clinical\Enum\EncounterStatus;
use common\components\Domain\Clinical\Service\CarePlanLifecycleService;
use common\components\Domain\Clinical\Service\CarePlanService;
use common\components\Domain\Clinical\Service\ConditionLifecycleService;
use common\components\Domain\Clinical\Service\EncounterAutomaticCodingService;
use common\components\Domain\Clinical\Service\EncounterLifecycleService;
use common\components\Domain\Clinical\Service\MedicationRequestService;
use common\components\Domain\Clinical\Service\ServiceRequestService;
use common\components\Domain\Clinical\Service\TreatmentRequestSnomedCodingService;
use common\components\Domain\Clinical\Specialty\EncounterDefinitionSpecialtyRegistry;
use common\components\Domain\Clinical\Specialty\Inpatient\InpatientEncounterAuxService;
use common\components\Domain\Clinical\Specialty\Odontology\OdontologyEncounterService;
use common\components\Domain\Clinical\Specialty\Ophthalmology\OphthalmologyEncounterService;
use common\components\Domain\Clinical\Legacy\ConsultaProcesamientoService;
use common\components\Domain\Clinical\Presentation\EncounterCaptureReviewPresenter;
use common\models\Clinical\Condition;
use common\models\Clinical\Encounter;
use common\models\Clinical\EncounterDefinition;
use common\models\ConsultaAtencionesEnfermeria;
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

            $stagedItemIds = self::normalizeStagedItemIds(
                $body['staged_item_ids'] ?? $body['stagedItemIds'] ?? null
            );
            $resolutions = $body['resolutions'] ?? $body['resoluciones'] ?? null;
            if (!is_array($resolutions)) {
                $resolutions = [];
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
                'staged_item_ids_count' => count($stagedItemIds),
                'resolutions_count' => count($resolutions),
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

                return $this->clientGuardarResponse($out);
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

            $fullMeta = $this->resolveFullAnalysisExtraidosWithMeta($body);
            $fullExtraidos = $fullMeta['extraidos'];
            $diagnostico['backup_fuentes'] = $fullMeta['fuentes'];
            $diagnostico['cache'] = $fullMeta['cache'];
            $logger->registrar('BACKUP', null, [
                'fuentes' => $fullMeta['fuentes'],
                'cache' => $fullMeta['cache'],
                'counts' => self::countCategories($fullExtraidos),
            ], ['metodo' => 'resolveFullAnalysisExtraidos']);

            $categorias = [];
            $idCfgEarly = is_numeric($idConfiguracion) ? (int) $idConfiguracion : 0;
            if ($idCfgEarly > 0) {
                $defEarly = EncounterDefinition::findOne($idCfgEarly);
                if ($defEarly !== null) {
                    $categorias = EncounterDefinition::getCategoriasParaPrompt($defEarly);
                }
            }

            $applier = new ClinicalCaptureResolutionApplier();
            // Resolutions usan índices del análisis completo (Medicación::1). Si el cliente
            // ya filtró filas, aplicar sobre el full y luego recortar por staged_item_ids.
            // Preferir checkpoint del capture (analisis_datos_extraidos) sobre cache inmutable:
            // ahí viven resolutions de intentos previos de guardado.
            $checkpoint = [];
            if (isset($body['analisis_datos_extraidos']) && is_array($body['analisis_datos_extraidos'])) {
                $checkpoint = $body['analisis_datos_extraidos'];
            } elseif (isset($body['analisisDatosExtraidos']) && is_array($body['analisisDatosExtraidos'])) {
                $checkpoint = $body['analisisDatosExtraidos'];
            }
            if ($checkpoint !== [] && isset($checkpoint['datosExtraidos']) && is_array($checkpoint['datosExtraidos'])) {
                $checkpoint = $checkpoint['datosExtraidos'];
            }

            if ($stagedItemIds !== []) {
                if ($checkpoint !== [] && self::datosExtraidosLooksLikeCategories($checkpoint)) {
                    $working = $checkpoint;
                } elseif ($fullExtraidos !== []) {
                    $working = $fullExtraidos;
                } else {
                    $working = $datosExtraidos;
                }
            } elseif ($fullExtraidos !== []) {
                $working = self::enrichExtraidosFromFullAnalysis($datosExtraidos, $fullExtraidos);
            } else {
                $working = $datosExtraidos;
            }

            if ($resolutions !== []) {
                $working = $applier->apply($working, $resolutions, $categorias);
            }

            $stagedIndexMap = [];
            if ($stagedItemIds !== []) {
                $stagedIndexMap = $applier->stagedIndexMap($stagedItemIds);
                $datosExtraidos = $applier->filterByStagedItemIds($working, $stagedItemIds, $categorias);
            } else {
                $datosExtraidos = $working;
            }
            $body['datosExtraidos'] = $datosExtraidos;
            // Checkpoint resuelto (índices originales) para el caller / próximo intento.
            $body['analisis_datos_extraidos'] = $working;

            $diagnostico['final_keys'] = array_keys($datosExtraidos);
            $diagnostico['final_counts'] = self::countCategories($datosExtraidos);
            $logger->registrar('FINAL_EXTRAIDOS', null, [
                'keys' => $diagnostico['final_keys'],
                'counts' => $diagnostico['final_counts'],
                'payload_preview' => self::previewExtraidos($datosExtraidos),
                'staged_item_ids' => $stagedItemIds,
            ], ['metodo' => 'tras apply+filter staged']);

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

                return $this->clientGuardarResponse($out);
            }

            $configuracion = EncounterDefinition::findOne((int) $idConfiguracion);
            if (!$configuracion) {
                $out = $this->error(400, 'Configuración de encounter no encontrada.');
                $out['diagnostico_guardar'] = $diagnostico;
                $logger->finalizar($out);

                return $this->clientGuardarResponse($out);
            }

            $categorias = EncounterDefinition::getCategoriasParaPrompt($configuracion);
            $completeness = (new EncounterCaptureCompletenessValidator())->validate(
                $datosExtraidos,
                $categorias
            );
            if ($completeness['tiene_datos_faltantes'] === true && $stagedIndexMap !== []) {
                $completeness = $applier->remapCompletenessToOriginalIndices(
                    $completeness,
                    $stagedIndexMap
                );
            }
            if ($completeness['tiene_datos_faltantes'] === true) {
                $message = trim((string) ($completeness['message'] ?? ''));
                if ($message === '') {
                    $message = 'Faltan categorías u obligatorios de captura. Completá el texto y volvé a analizar.';
                }
                $out = $this->error(400, $message, [
                    'datos_faltantes_detalle' => [
                        'missing_categories' => $completeness['missing_categories'],
                        'incomplete_items' => $completeness['incomplete_items'],
                        'issues' => $completeness['issues'] ?? [],
                        'message' => $completeness['message'],
                    ],
                ]);
                $out['tiene_datos_faltantes'] = true;
                $out['diagnostico_guardar'] = $diagnostico;
                $out['analisis_datos_extraidos'] = $working;
                $logger->finalizar($out);

                return $this->clientGuardarResponse($out);
            }

            $parentKey = strtoupper(trim((string) ($body['parent'] ?? '')));
            $parentIdBody = (int) ($body['parent_id'] ?? 0);
            $noteText = (string) ($this->resolveCaptureNote($body) ?? $body['texto_original'] ?? $body['consulta_texto'] ?? '');
            if (
                $parentIdBody > 0
                && (int) $idPersona > 0
                && in_array($parentKey, [Encounter::PARENT_INTERNACION, Encounter::PARENT_GUARDIA], true)
            ) {
                $dedupSvc = new \common\components\Domain\Clinical\Service\EpisodeCaptureDedupService();
                if ($dedupSvc->isNoteDuplicateOfPriorEvolutions(
                    $noteText,
                    $parentKey,
                    $parentIdBody,
                    (int) $idPersona
                )) {
                    $msg = 'Esta nota es casi idéntica a una evolución previa del mismo episodio. '
                        . 'Editá el texto para documentar los cambios clínicos.';
                    $out = $this->error(400, $msg, [
                        'episode_note_duplicate' => true,
                    ]);
                    $out['diagnostico_guardar'] = $diagnostico;
                    $logger->finalizar($out);

                    return $this->clientGuardarResponse($out);
                }
            }

            $paciente = $this->lifecycle->findSubject((int) $idPersona);
            if (!$paciente) {
                $out = $this->error(400, 'Paciente no encontrado.');
                $out['diagnostico_guardar'] = $diagnostico;
                $logger->finalizar($out);

                return $this->clientGuardarResponse($out);
            }

            // Toda la escritura clínica (nota/motivos/MR/SR/care_plan/finalize) va en una
            // TX corta y se commitea ANTES de cualquier llamada IA. En hosting compartido
            // una TX abierta durante codificación (~1–2s) corre riesgo de reconnect y
            // se pierde lo no committeado; la nota/conditions post-IA sí quedaban.
            $carePlanOptions = $this->resolveCarePlanOptionsFromBody($body);
            $conditionResolutions = $body['condition_resolutions'] ?? $body['conditionResolutions'] ?? [];
            $carePlanResolutions = $body['care_plan_resolutions'] ?? $body['carePlanResolutions'] ?? [];
            if (!is_array($conditionResolutions)) {
                $conditionResolutions = [];
            }
            if (!is_array($carePlanResolutions)) {
                $carePlanResolutions = [];
            }

            $encounter = $this->runInDbTransaction(function () use (
                $encounterId,
                $body,
                $paciente,
                $configuracion,
                &$diagnostico,
                $datosExtraidos,
                $logger,
                $carePlanOptions,
                $conditionResolutions,
                $carePlanResolutions,
                $idPersona
            ) {
                $encounter = $this->resolveEncounter($encounterId, $body, $paciente, $configuracion);
                $diagnostico['por_modelo'] = $this->persistExtractedData(
                    $encounter,
                    $configuracion,
                    $datosExtraidos,
                    $logger
                );
                $subjectId = (int) ($encounter->subject_persona_id ?: $idPersona);
                if ($conditionResolutions !== []) {
                    $diagnostico['condition_resolutions'] = count(
                        (new ConditionLifecycleService())->applyResolutions($conditionResolutions, $subjectId)
                    );
                }
                if ($carePlanResolutions !== []) {
                    $diagnostico['care_plan_resolutions'] = count(
                        (new CarePlanLifecycleService())->applyResolutions($carePlanResolutions, $subjectId)
                    );
                }
                $encounter = $this->lifecycle->onCaptureDocumented($encounter, $carePlanOptions);
                $this->forcePersistCaptureNote($encounter, $body);

                return $encounter;
            });

            $logger->registrar('CHECKPOINT', null, $this->snapshotPersistido($encounter), [
                'metodo' => 'tras persist+finalize (commit)',
            ]);

            try {
                $diagnostico['guardia_outcome'] = (new GuardiaEncounterOutcomeService())
                    ->applyAfterDocumentation($encounter, is_array($datosExtraidos) ? $datosExtraidos : []);
            } catch (\Throwable $e) {
                Yii::warning(
                    'encounter.guardar guardia outcome: ' . $e->getMessage(),
                    'encounter-doc'
                );
            }

            // Forzar conexión nueva antes de la IA (evita "MySQL server has gone away"
            // sobre el handle idle y commits fantasmas).
            try {
                Yii::$app->db->close();
            } catch (\Throwable $e) {
                Yii::warning('encounter.guardar db close pre-coding: ' . $e->getMessage(), 'encounter-doc');
            }

            $diagnostico['coding'] = ['ok' => true, 'saved' => 0, 'error' => null];
            try {
                $savedCoding = EncounterAutomaticCodingService::codeAndPersistForEncounter(
                    $encounter,
                    $datosExtraidos,
                    $configuracion
                );
                $savedTreatmentCoding = (new TreatmentRequestSnomedCodingService())
                    ->codeAndPersistForEncounter($encounter);
                $diagnostico['coding']['diagnosis_saved'] = (int) $savedCoding;
                $diagnostico['coding']['treatment_request_saved'] = (int) $savedTreatmentCoding;
                $diagnostico['coding']['saved'] = (int) $savedCoding + (int) $savedTreatmentCoding;
            } catch (\Throwable $e) {
                // La documentación clínica ya está committeada; no tumbar el guardar.
                $diagnostico['coding'] = [
                    'ok' => false,
                    'saved' => 0,
                    'error' => $e->getMessage(),
                ];
                Yii::error(
                    'encounter.guardar coding falló (docs ya persistidas): ' . $e->getMessage(),
                    'encounter-doc'
                );
                $logger->registrar('CODING_ERROR', null, $e->getMessage(), [
                    'metodo' => 'codeAndPersistForEncounter',
                ]);
            }
            $logger->registrar('CHECKPOINT', null, $this->snapshotPersistido($encounter), [
                'metodo' => 'tras codeAndPersistForEncounter',
            ]);

            $encounter->refresh();
            $persistido = $this->snapshotPersistido($encounter);
            $persistido['categorias'] = array_keys($datosExtraidos);

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

            return $this->clientGuardarResponse($out);
        } catch (\InvalidArgumentException $e) {
            Yii::warning($e->getMessage(), __METHOD__);
            $out = $this->error(400, $e->getMessage());
            $out['diagnostico_guardar'] = $diagnostico;
            if ($logger !== null) {
                $logger->registrar('ERROR', null, $e->getMessage(), ['metodo' => __METHOD__]);
                $logger->finalizar($out);
            }

            return $this->clientGuardarResponse($out);
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

            return $this->clientGuardarResponse($out);
        }
    }

    /**
     * Opciones de ciclo CarePlan al cerrar la atención.
     *
     * Body:
     * - continue_treatment / continueTreatment (bool)
     * - complete_acute / completeAcute (bool, default true)
     * - care_plan_options / carePlanOptions (mapa con las mismas claves)
     *
     * @param array<string, mixed> $body
     * @return array{continue_treatment: bool, complete_acute: bool}
     */
    private function resolveCarePlanOptionsFromBody(array $body): array
    {
        $nested = $body['care_plan_options'] ?? $body['carePlanOptions'] ?? null;
        $src = is_array($nested) ? array_merge($body, $nested) : $body;
        $continue = !empty($src['continue_treatment']) || !empty($src['continueTreatment']);
        $completeAcute = true;
        if (array_key_exists('complete_acute', $src)) {
            $completeAcute = $src['complete_acute'] !== false && $src['complete_acute'] !== 0
                && $src['complete_acute'] !== '0' && $src['complete_acute'] !== 'false';
        } elseif (array_key_exists('completeAcute', $src)) {
            $completeAcute = $src['completeAcute'] !== false && $src['completeAcute'] !== 0
                && $src['completeAcute'] !== '0' && $src['completeAcute'] !== 'false';
        }

        return [
            'continue_treatment' => $continue,
            'complete_acute' => $completeAcute,
        ];
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
     * @param mixed $raw
     * @return list<string>
     */
    private static function normalizeStagedItemIds($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $id) {
            if (!is_string($id) && !is_int($id)) {
                continue;
            }
            $id = trim((string) $id);
            if ($id !== '' && preg_match('/^.+::\d+$/u', $id) === 1) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
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

        // Episodio (internación / guardia): una evolución = un encounter.
        // Solo reutilizar un pase aún in-progress; finished → crear uno nuevo.
        $isEpisodeParent = in_array(
            $parent,
            [Encounter::PARENT_INTERNACION, Encounter::PARENT_GUARDIA],
            true
        );

        $query = Encounter::find()
            ->where([
                'parent_id' => $parentId,
                'subject_persona_id' => (int) $paciente->id_persona,
                'deleted_at' => null,
            ])
            ->andWhere([
                'or',
                ['parent_type' => $parent],
                ['parent_type' => Encounter::PARENT_CLASSES[$parent] ?? '__none__'],
            ]);

        if ($isEpisodeParent) {
            $query->andWhere(['status' => EncounterStatus::IN_PROGRESS]);
        }

        $existing = $query->orderBy(['id' => SORT_DESC])->one();

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
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    private function runInDbTransaction(callable $fn)
    {
        $tx = Yii::$app->db->beginTransaction();
        try {
            $result = $fn();
            $tx->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($tx->isActive) {
                $tx->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array{
     *   note: bool,
     *   reason_text: bool,
     *   reason_text_value: string,
     *   conditions: int,
     *   medication_requests: int,
     *   service_requests: int,
     *   care_plans: int
     * }
     */
    private function snapshotPersistido(Encounter $encounter): array
    {
        $id = (int) $encounter->id;
        $row = Encounter::find()
            ->select(['note', 'reason_text'])
            ->where(['id' => $id])
            ->asArray()
            ->one();
        $note = is_array($row) ? trim((string) ($row['note'] ?? '')) : '';
        $reason = is_array($row) ? trim((string) ($row['reason_text'] ?? '')) : '';

        return [
            'note' => $note !== '',
            'reason_text' => $reason !== '',
            'reason_text_value' => mb_substr($reason, 0, 120),
            'conditions' => (int) \common\models\Clinical\Condition::find()
                ->where(['encounter_id' => $id, 'deleted_at' => null])
                ->count(),
            'medication_requests' => (int) \common\models\Clinical\MedicationRequest::find()
                ->where(['encounter_id' => $id, 'deleted_at' => null])
                ->count(),
            'service_requests' => (int) \common\models\Clinical\ServiceRequest::find()
                ->where(['encounter_id' => $id, 'deleted_at' => null])
                ->count(),
            'care_plans' => (int) \common\models\Clinical\CarePlan::find()
                ->where(['encounter_id' => $id, 'deleted_at' => null])
                ->count(),
        ];
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
                if ($logger !== null) {
                    $logger->registrar('PERSIST', null, $stat, ['metodo' => $modelo]);
                }
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
                if ($logger !== null) {
                    $logger->registrar('PERSIST', null, $stat, ['metodo' => $modelo]);
                }
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
                case 'ConsultaAtencionesEnfermeria':
                    $created = $this->persistSignosVitalesEnfermeria($encounter, $payload);
                    $stat['accion'] = 'signos_vitales';
                    $stat['created'] = $created;
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
            if ($logger !== null) {
                $logger->registrar('PERSIST', null, $stat, ['metodo' => $modelo]);
            }
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
        $dedup = new \common\components\Domain\Clinical\Service\EpisodeCaptureDedupService();
        $presentation = new \common\components\Domain\Clinical\Service\ConditionPresentationService();
        $episodeIds = $this->episodeEncounterIdsFor($encounter);
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
            $key = $presentation->dedupeKeyForLabel(
                (string) $condition->display,
                (string) $condition->code
            );
            if (
                $key !== ''
                && $episodeIds !== []
                && $dedup->hasActiveConditionKey((int) $encounter->subject_persona_id, $episodeIds, $key)
            ) {
                Yii::info(
                    'Skip condition duplicada en episodio: ' . $key,
                    'encounter-doc'
                );
                continue;
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
        $dedup = new \common\components\Domain\Clinical\Service\EpisodeCaptureDedupService();
        $episodeIds = $this->episodeEncounterIdsFor($encounter);
        foreach ($rows as $row) {
            $display = MedicationRequestService::resolveMedicationDisplay($row);
            $displayKey = $dedup->normalizeKey($display);
            if (
                $displayKey !== ''
                && $episodeIds !== []
                && $dedup->hasActiveMedicationDisplay($episodeIds, $displayKey)
            ) {
                Yii::info('Skip medication duplicada en episodio: ' . $display, 'encounter-doc');
                continue;
            }
            try {
                $this->medications->createFromExtractedRow($encounter, $carePlan, $row);
            } catch (\InvalidArgumentException $e) {
                Yii::info('Skip medication vacío: ' . $e->getMessage(), 'encounter-doc');
            }
        }
    }

    /**
     * @return list<int>
     */
    private function episodeEncounterIdsFor(Encounter $encounter): array
    {
        $parent = strtoupper(trim((string) ($encounter->parent_type ?? '')));
        // parent_type puede ser FQCN; normalizar a clave corta.
        foreach (Encounter::PARENT_CLASSES as $key => $fqcn) {
            if ($parent === $key || $parent === $fqcn) {
                $parent = $key;
                break;
            }
        }
        $parentId = (int) ($encounter->parent_id ?? 0);
        $subjectId = (int) ($encounter->subject_persona_id ?? 0);
        if (
            $parentId <= 0
            || $subjectId <= 0
            || !in_array($parent, [Encounter::PARENT_INTERNACION, Encounter::PARENT_GUARDIA], true)
        ) {
            return [(int) $encounter->id];
        }

        return (new \common\components\Domain\Clinical\Service\EpisodeCaptureDedupService())
            ->listEpisodeEncounterIds($parent, $parentId, $subjectId);
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
    /**
     * Persiste signos vitales extraídos en la captura como atención de enfermería del encounter
     * (alimenta SV del episodio).
     *
     * @param mixed $payload
     */
    private function persistSignosVitalesEnfermeria(Encounter $encounter, $payload): int
    {
        $datos = $this->normalizeSignosVitalesPayload($payload);
        if ($datos === []) {
            return 0;
        }

        $row = new ConsultaAtencionesEnfermeria();
        $row->encounter_id = (int) $encounter->id;
        $row->id_persona = (int) $encounter->subject_persona_id;
        $row->id_efector = (int) ($encounter->id_efector ?? 0) ?: null;
        $row->fecha_creacion = date('Y-m-d H:i:s');
        $row->hora_creacion = date('H:i:s');
        $row->datos = json_encode($datos, JSON_UNESCAPED_UNICODE);
        $row->syncProfesionalEfectorServicioFromContext();
        if (!$row->save()) {
            Yii::warning(
                'No se pudo persistir signos vitales: ' . json_encode($row->errors, JSON_UNESCAPED_UNICODE),
                'encounter-doc'
            );

            return 0;
        }

        return 1;
    }

    /**
     * @param mixed $payload
     * @return array<string, mixed>
     */
    private function normalizeSignosVitalesPayload($payload): array
    {
        if (!is_array($payload)) {
            return [];
        }
        // Lista de un solo objeto (extracción IA).
        if (isset($payload[0]) && is_array($payload[0])) {
            $merged = [];
            foreach ($payload as $row) {
                if (is_array($row)) {
                    $merged = array_merge($merged, $row);
                }
            }
            $payload = $merged;
        }

        $map = [
            'ta_sistolica' => 'sistolica',
            'ta sistolica' => 'sistolica',
            'sistolica' => 'sistolica',
            'bp_sys' => 'sistolica',
            'ta_diastolica' => 'diastolica',
            'ta diastolica' => 'diastolica',
            'diastolica' => 'diastolica',
            'bp_dia' => 'diastolica',
            'frecuencia cardiaca' => 'fc',
            'frecuencia_cardiaca' => 'fc',
            'fc' => 'fc',
            'hr' => 'fc',
            'frecuencia respiratoria' => 'fr',
            'frecuencia_respiratoria' => 'fr',
            'fr' => 'fr',
            'rr' => 'fr',
            'saturacion o2' => 'sat_o2',
            'saturacion' => 'sat_o2',
            'sat_o2' => 'sat_o2',
            'spo2' => 'sat_o2',
            'temperatura' => 'temperatura',
            'temp' => 'temperatura',
            'temp_c' => 'temperatura',
            'glucemia' => 'glucemia_capilar',
            'glucemia_capilar' => 'glucemia_capilar',
            'glucose' => 'glucemia_capilar',
            'glasgow' => 'glasgow',
            'peso' => 'peso',
            'talla' => 'talla',
        ];

        $out = [];
        foreach ($payload as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $key = strtolower(trim((string) $k));
            $key = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $key);
            if (!isset($map[$key])) {
                continue;
            }
            $canon = $map[$key];
            if (is_numeric($v) || (is_string($v) && is_numeric(str_replace(',', '.', $v)))) {
                $out[$canon] = is_string($v) ? str_replace(',', '.', $v) : $v;
            } else {
                $out[$canon] = $v;
            }
        }

        if (isset($out['sistolica'], $out['diastolica'])) {
            $out['TensionArterial1'] = [
                'sistolica' => $out['sistolica'],
                'diastolica' => $out['diastolica'],
            ];
        }

        return $out;
    }

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
     * Quita telemetría interna del payload al cliente (sigue en logs de guardar).
     *
     * @param array<string, mixed> $out
     * @return array<string, mixed>
     */
    private function clientGuardarResponse(array $out): array
    {
        unset($out['diagnostico_guardar']);

        return $out;
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
