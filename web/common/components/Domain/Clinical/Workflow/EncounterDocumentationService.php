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
                $encounter = $this->lifecycle->onCaptureDocumented($encounter);

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
        if (isset($body['texto_procesado']) || isset($body['observacion'])) {
            $note = $body['texto_procesado'] ?? $body['observacion'];
            $encounter->note = is_string($note) ? $note : (string) $note;
        } elseif (isset($body['texto_original']) && trim((string) $body['texto_original']) !== '') {
            $encounter->note = (string) $body['texto_original'];
        }
        if (isset($body['motivo_consulta']) && trim((string) $body['motivo_consulta']) !== '') {
            $encounter->reason_text = (string) $body['motivo_consulta'];
        } elseif (
            (trim((string) ($encounter->reason_text ?? '')) === '')
            && isset($body['texto_original'])
            && trim((string) $body['texto_original']) !== ''
        ) {
            $encounter->reason_text = (string) $body['texto_original'];
        }
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
     */
    private function persistExtractedData(
        Encounter $encounter,
        EncounterDefinition $configuracion,
        array $datosExtraidos
    ): void {
        $this->assertEncounterPersisted($encounter);
        $categorias = EncounterDefinition::getCategoriasParaPrompt($configuracion);
        $carePlan = null;

        foreach ($categorias as $categoria) {
            $modelo = $categoria['modelo'] ?? null;
            if (!$modelo) {
                continue;
            }
            $payload = $this->resolvePayloadForCategoria($datosExtraidos, $categoria);
            if ($payload === null) {
                continue;
            }
            if (!$this->specialtyRegistry->isModelAllowed($configuracion, $modelo)) {
                continue;
            }

            switch ($modelo) {
                case 'DiagnosticoConsulta':
                    $this->persistConditions($encounter, $payload);
                    break;
                case 'ConsultaMedicamentos':
                    $medicationRows = MedicationRequestService::normalizeExtractedMedicationPayload($payload);
                    if ($medicationRows === []) {
                        break;
                    }
                    $carePlan = $carePlan ?? $this->carePlans->createAcutePlanForEncounter(
                        (int) $encounter->subject_persona_id,
                        (int) $encounter->id
                    );
                    $this->persistMedications($encounter, $carePlan, $medicationRows);
                    break;
                case 'ConsultaPracticas':
                case 'ConsultaDerivaciones':
                    if ($modelo === 'ConsultaPracticas') {
                        $rows = is_array($payload) ? $payload : [];
                        if ($rows !== []) {
                            $carePlan = $carePlan ?? $this->carePlans->createAcutePlanForEncounter(
                                (int) $encounter->subject_persona_id,
                                (int) $encounter->id
                            );
                        }
                        $this->persistServiceRequests($encounter, $payload, $modelo, $carePlan);
                    } else {
                        $this->persistServiceRequests($encounter, $payload, $modelo, null);
                    }
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

    private function normalizeExtractionKey(string $key): string
    {
        $folded = strtr(mb_strtolower(trim($key), 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/', '', $folded) ?? $folded;
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
        $rows = MedicationRequestService::normalizeExtractedMedicationPayload($payload);
        foreach ($rows as $row) {
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
