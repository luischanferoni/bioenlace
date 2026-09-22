<?php

namespace common\components\Domain\Clinical\Inpatient\Application\UseCase;

use common\components\Domain\Clinical\Encounter\Domain\ConditionClinicalStatus;
use common\components\Domain\Clinical\Encounter\Domain\ConditionVerificationStatus;
use common\components\Domain\Clinical\CarePlan\Application\Service\CarePlanService;
use common\components\Domain\Clinical\CarePlan\Application\UseCase\ManageMedicationRequest;
use common\components\Domain\Clinical\CarePlan\Application\UseCase\ManageServiceRequest;
use common\components\Domain\Clinical\Inpatient\Domain\InpatientClinicalContext;
use common\models\Clinical\Condition;

/**
 * Órdenes clínicas de internación → recursos FHIR (sin tablas seg_nivel_internacion_* hijas).
 */
final class ManageInpatientOrder
{
    private ManageMedicationRequest $medications;
    private ManageServiceRequest $serviceRequests;
    private CarePlanService $carePlans;

    public function __construct(
        ?ManageMedicationRequest $medications = null,
        ?ManageServiceRequest $serviceRequests = null,
        ?CarePlanService $carePlans = null
    ) {
        $this->carePlans = $carePlans ?? new CarePlanService();
        $this->medications = $medications ?? new ManageMedicationRequest($this->carePlans);
        $this->serviceRequests = $serviceRequests ?? new ManageServiceRequest($this->carePlans);
    }

    /**
     * @param list<array<string, mixed>|object> $rows
     */
    public function persistMedicationRows(InpatientClinicalContext $ctx, array $rows): void
    {
        foreach ($rows as $row) {
            $code = trim((string) $this->rowValue($row, 'conceptId'));
            if ($code === '') {
                continue;
            }
            $cantidad = $this->rowValue($row, 'cantidad');
            $dosisDiaria = $this->rowValue($row, 'dosis_diaria');
            $indicacion = $this->rowValue($row, 'indicacion');
            $parts = array_filter([
                $cantidad !== null && $cantidad !== '' ? 'cant: ' . $cantidad : null,
                $dosisDiaria ? 'dosis diaria: ' . $dosisDiaria : null,
                $indicacion ? (string) $indicacion : null,
            ]);
            $this->medications->createFromApi($ctx->encounter, $ctx->carePlan, [
                'medication_code' => $code,
                'medication_display' => $this->resolveSnomedTerm($code),
                'dosage_text' => $parts ? implode('; ', $parts) : null,
            ]);
        }
    }

    /**
     * @param list<array<string, mixed>|object> $rows
     */
    public function persistPracticeRows(InpatientClinicalContext $ctx, array $rows): void
    {
        foreach ($rows as $row) {
            $code = trim((string) $this->rowValue($row, 'conceptId'));
            if ($code === '') {
                continue;
            }
            $resultado = $this->rowValue($row, 'resultado');
            $informe = $this->rowValue($row, 'informe');
            $note = array_filter([
                $resultado ? 'resultado: ' . $resultado : null,
                $informe ? 'informe: ' . $informe : null,
            ]);
            $this->serviceRequests->createFromApi($ctx->encounter, $ctx->carePlan, [
                'category' => 'inpatient',
                'code' => $code,
                'display' => $this->resolveSnomedTerm($code),
                'note' => $note ? implode('; ', $note) : null,
            ]);
        }
    }

    /**
     * @param list<array<string, mixed>|object> $rows
     */
    public function persistDiagnosisRows(InpatientClinicalContext $ctx, array $rows): void
    {
        foreach ($rows as $row) {
            $code = trim((string) $this->rowValue($row, 'conceptId'));
            if ($code === '') {
                continue;
            }
            $condition = new Condition();
            $condition->encounter_id = $ctx->encounter->id;
            $condition->subject_persona_id = $ctx->encounter->subject_persona_id;
            $condition->code = $code;
            $condition->display = $this->resolveSnomedTerm($code);
            $clinical = $this->rowValue($row, 'condition_clinical_status');
            $verification = $this->rowValue($row, 'condition_verification_status');
            $condition->clinical_status = $clinical ?: ConditionClinicalStatus::ACTIVE;
            $condition->verification_status = $verification ?: ConditionVerificationStatus::CONFIRMED;
            $condition->recorded_date = date('Y-m-d H:i:s');
            $tipo = $this->rowValue($row, 'tipo_problema');
            $condition->note = 'inpatient:' . ($tipo ?? 'diagnostico');
            $condition->save(false);
        }
    }

    /**
     * @param array<string, mixed>|object $row
     */
    private function rowValue(array|object $row, string $key): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? null;
        }

        return $row->$key ?? null;
    }

    private function resolveSnomedTerm(string $conceptId): ?string
    {
        if ($conceptId === '' || !isset(\Yii::$app->snowstorm)) {
            return null;
        }
        try {
            $term = \Yii::$app->snowstorm->busquedaPorConceptId($conceptId);

            return is_string($term) && $term !== '' ? $term : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
