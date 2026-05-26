<?php

namespace common\components\Clinical\Workflow;

use common\components\Clinical\Service\CarePlanService;
use common\components\Clinical\Service\EncounterLifecycleService;
use common\components\Clinical\Service\MedicationRequestService;
use common\components\Clinical\Service\ServiceRequestService;
use common\components\Clinical\Specialty\EncounterDefinitionSpecialtyRegistry;
use common\components\Clinical\Specialty\Inpatient\InpatientEncounterAuxService;
use common\components\Clinical\Specialty\Odontology\OdontologyEncounterService;
use common\components\Clinical\Specialty\Ophthalmology\OphthalmologyEncounterService;
use common\components\Clinical\Legacy\ConsultaProcesamientoService;
use common\models\Clinical\Condition;
use common\models\Clinical\Encounter;
use common\models\Clinical\EncounterDefinition;
use common\models\ConsultasConfiguracion;
use common\models\DiagnosticoConsulta;
use common\models\Persona;
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
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function guardar(array $body): array
    {
        try {
            $idConfiguracion = $body['id_configuracion'] ?? null;
            $idPersona = $this->lifecycle->resolveSubjectPersonaId($body);
            $datosExtraidos = $body['datosExtraidos'] ?? [];
            $encounterId = $body['encounter_id'] ?? $body['id_consulta'] ?? null;

            if (!$idConfiguracion) {
                $idServicio = Yii::$app->user->getServicioActual();
                $encounterClass = Yii::$app->user->getEncounterClass();
                if ($idServicio && $encounterClass) {
                    [, , , $idConfiguracion] = EncounterDefinition::getUrlPorServicioYEncounterClass($idServicio, $encounterClass);
                }
            }

            if (!$idConfiguracion || !$idPersona) {
                return $this->error(400, 'Faltan id_configuracion e id_persona (o subject_persona_id).', [
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

        $encounterClass = Yii::$app->user->getEncounterClass() ?: Encounter::ENCOUNTER_CLASS_AMB;
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
            'service_id' => Yii::$app->user->getServicioActual(),
            'efector_id' => Yii::$app->user->getIdEfector(),
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
        $categorias = ConsultasConfiguracion::getCategoriasParaPrompt($configuracion);
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
}
