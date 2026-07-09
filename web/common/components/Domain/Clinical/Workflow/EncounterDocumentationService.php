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
            $encounterId = $body['encounter_id'] ?? $body['id_consulta'] ?? null;

            $blockingError = EncounterCaptureReviewPresenter::blockingErrorFromExtraidos($datosExtraidos);
            if ($blockingError !== null) {
                $message = trim((string) ($blockingError['texto'] ?? ''));
                if ($message === '') {
                    $message = 'No se puede guardar: el análisis tiene errores.';
                }

                return $this->error(400, $message, [
                    'tipo' => $blockingError['tipo'] ?? 'error_sistema',
                ]);
            }

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
                return $this->error(400, $this->missingCaptureContextMessage($idConfiguracion, $idPersona), [
                    'id_configuracion' => $idConfiguracion ? 'ok' : 'falta',
                    'id_persona' => $idPersona ? 'ok' : 'falta',
                ]);
            }

            $configuracion = EncounterDefinition::findOne($idConfiguracion);
            if (!$configuracion) {
                return $this->error(400, 'Configuración de encounter no encontrada.');
            }

            $paciente = $this->lifecycle->findSubject((int) $idPersona);
            if (!$paciente) {
                return $this->error(400, 'Paciente no encontrado.');
            }

            $tx = Yii::$app->db->beginTransaction();
            try {
                $encounter = $this->resolveEncounter($encounterId, $body, $paciente, $configuracion);
                $this->persistExtractedData($encounter, $configuracion, $datosExtraidos);
                EncounterAutomaticCodingService::codeAndPersistForEncounter($encounter, $datosExtraidos, $configuracion);

                $tx->commit();

                return [
                    '__statusCode' => 200,
                    'success' => true,
                    'message' => 'Encounter guardado correctamente.',
                    'encounter_id' => $encounter->id,
                    'id_consulta' => $encounter->id,
                ];
            } catch (\Throwable $e) {
                $tx->rollBack();
                throw $e;
            }
        } catch (\Throwable $e) {
            Yii::error($e->getMessage(), __METHOD__);

            return $this->error(500, 'Error al guardar encounter: ' . $e->getMessage());
        }
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
        if ($encounterId) {
            $encounter = Encounter::findOne($encounterId);
            if (!$encounter) {
                throw new \RuntimeException('Encounter no encontrado');
            }
            if (isset($body['texto_procesado']) || isset($body['observacion'])) {
                $encounter->note = $body['texto_procesado'] ?? $body['observacion'];
            }
            if (isset($body['motivo_consulta'])) {
                $encounter->reason_text = $body['motivo_consulta'];
            }
            $encounter->save(false);

            return $encounter;
        }

        $encounterClass = ClinicalOperationalContextResolver::resolveEncounterClass($body);
        [$idPes, $idServicio] = array_slice(ClinicalOperationalContextResolver::resolve($body), 0, 2);
        $efectorId = Yii::$app->user->getIdEfector();
        if (($efectorId === null || $efectorId === '' || (int) $efectorId <= 0) && $idPes > 0) {
            $pes = \common\models\ProfesionalEfectorServicio::findOne($idPes);
            if ($pes !== null && (int) $pes->id_efector > 0) {
                $efectorId = (int) $pes->id_efector;
            }
        }
        $parentType = null;
        $parentId = null;
        if (!empty($body['parent']) && !empty($body['parent_id'])) {
            $parentKey = $body['parent'];
            if (!empty(Encounter::PARENT_CLASSES[$parentKey] ?? null)) {
                $parentType = Encounter::PARENT_CLASSES[$parentKey];
                $parentId = (int) $body['parent_id'];
            }
        }

        return $this->lifecycle->start([
            'subject_persona_id' => $paciente->id_persona,
            'encounter_class' => $encounterClass,
            'service_id' => $idServicio ?: Yii::$app->user->getServicioActual(),
            'efector_id' => $efectorId ?: null,
            'id_profesional_efector_servicio' => $idPes > 0 ? $idPes : Yii::$app->user->getIdProfesionalEfectorServicio(),
            'parent_type' => $parentType,
            'parent_id' => $parentId,
            'reason_text' => $body['motivo_consulta'] ?? $body['consulta_inicial'] ?? $body['texto_original'] ?? null,
            'note' => $body['texto_procesado'] ?? $body['observacion'] ?? null,
            'workflow_step' => 0,
        ]);
    }

    /**
     * @param array<string, mixed> $datosExtraidos
     */
    private function persistExtractedData(
        Encounter $encounter,
        EncounterDefinition $configuracion,
        array $datosExtraidos
    ): void {
        $categorias = EncounterDefinition::getCategoriasParaPrompt($configuracion);
        $carePlan = null;

        foreach ($categorias as $categoria) {
            $modelo = $categoria['modelo'] ?? null;
            if (!$modelo || !isset($datosExtraidos[$modelo])) {
                continue;
            }
            if (!$this->specialtyRegistry->isModelAllowed($configuracion, $modelo)) {
                continue;
            }
            $payload = $datosExtraidos[$modelo];

            switch ($modelo) {
                case 'DiagnosticoConsulta':
                    $this->persistConditions($encounter, $payload);
                    break;
                case 'ConsultaMedicamentos':
                    $carePlan = $carePlan ?? $this->carePlans->createAcutePlanForEncounter(
                        (int) $encounter->subject_persona_id,
                        (int) $encounter->id
                    );
                    $this->persistMedications($encounter, $carePlan, $payload);
                    break;
                case 'ConsultaPracticas':
                case 'ConsultaDerivaciones':
                    $this->persistServiceRequests($encounter, $payload, $modelo);
                    break;
                case 'ConsultaOdontologiaPracticas':
                    $carePlan = $this->odontology->persistPractices($encounter, $payload, $carePlan);
                    break;
                case 'ConsultaOdontologiaDiagnosticos':
                    $this->odontology->persistDiagnostics($encounter, $payload);
                    break;
                case 'ConsultaOdontologiaEstados':
                    $this->odontology->persistToothStates($encounter, $payload);
                    break;
                case 'ConsultaPracticasOftalmologia':
                case 'ConsultaPracticasOftalmologiaEstudios':
                    $this->ophthalmology->persistPractices($encounter, $payload);
                    break;
                case 'ConsultasRecetaLentes':
                    $this->ophthalmology->persistLensPrescription($encounter, $payload);
                    break;
                case 'ConsultaBalanceHidrico':
                    $this->persistFluidBalances($encounter, $payload);
                    break;
                case 'ConsultaRegimen':
                    $this->persistRegimens($encounter, $payload);
                    break;
                case 'ConsultaSuministroMedicamento':
                    $this->persistMedicationSupplies($encounter, $payload);
                    break;
            }
        }
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
                $condition->display = $row['termino'] ?? $row['descripcion'] ?? null;
                $condition->clinical_status = $row['condition_clinical_status']
                    ?? DiagnosticoConsulta::CLINICAL_STATUS_ACTIVE;
                $condition->verification_status = $row['condition_verification_status']
                    ?? DiagnosticoConsulta::VERIFICATION_STATUS_CONFIRMED;
            } else {
                $condition->code = (string) $row;
                $condition->clinical_status = DiagnosticoConsulta::CLINICAL_STATUS_ACTIVE;
                $condition->verification_status = DiagnosticoConsulta::VERIFICATION_STATUS_CONFIRMED;
            }
            if ($condition->code === '') {
                continue;
            }
            $condition->recorded_date = date('Y-m-d H:i:s');
            $condition->save(false);
        }
    }

    /**
     * @param mixed $payload
     */
    private function persistMedications(Encounter $encounter, \common\models\Clinical\CarePlan $carePlan, $payload): void
    {
        if (!is_array($payload)) {
            return;
        }
        foreach ($payload as $row) {
            if (!is_array($row)) {
                continue;
            }
            $this->medications->createFromExtractedRow($encounter, $carePlan, $row);
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
    private function persistServiceRequests(Encounter $encounter, $payload, string $modelo): void
    {
        if (!is_array($payload)) {
            return;
        }
        foreach ($payload as $row) {
            $this->serviceRequests->createFromExtractedRow($encounter, $row, $modelo);
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
