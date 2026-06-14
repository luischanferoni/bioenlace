<?php

namespace common\components\Domain\Clinical\Specialty\Inpatient;

use common\components\Domain\Clinical\Service\CarePlanService;
use common\components\Domain\Clinical\Service\MedicationRequestService;
use common\components\Domain\Clinical\Service\ServiceRequestService;
use common\models\Clinical\Condition;
use common\models\DiagnosticoConsulta;
use common\models\SegNivelInternacionDiagnostico;
use common\models\SegNivelInternacionMedicamento;
use common\models\SegNivelInternacionPractica;

/**
 * Órdenes clínicas de internación → recursos FHIR (sin tablas seg_nivel_internacion_* hijas).
 */
final class InpatientOrderService
{
    private MedicationRequestService $medications;
    private ServiceRequestService $serviceRequests;
    private CarePlanService $carePlans;

    public function __construct(
        ?MedicationRequestService $medications = null,
        ?ServiceRequestService $serviceRequests = null,
        ?CarePlanService $carePlans = null
    ) {
        $this->carePlans = $carePlans ?? new CarePlanService();
        $this->medications = $medications ?? new MedicationRequestService($this->carePlans);
        $this->serviceRequests = $serviceRequests ?? new ServiceRequestService($this->carePlans);
    }

    /**
     * @param SegNivelInternacionMedicamento[] $rows
     */
    public function persistMedicationRows(InpatientClinicalContext $ctx, array $rows): void
    {
        foreach ($rows as $row) {
            if (!$row instanceof SegNivelInternacionMedicamento) {
                continue;
            }
            $code = trim((string) $row->conceptId);
            if ($code === '') {
                continue;
            }
            $parts = array_filter([
                $row->cantidad !== null ? 'cant: ' . $row->cantidad : null,
                $row->dosis_diaria ? 'dosis diaria: ' . $row->dosis_diaria : null,
                $row->indicacion ? (string) $row->indicacion : null,
            ]);
            $this->medications->createFromApi($ctx->encounter, $ctx->carePlan, [
                'medication_code' => $code,
                'medication_display' => $this->resolveSnomedTerm($code),
                'dosage_text' => $parts ? implode('; ', $parts) : null,
            ]);
        }
    }

    /**
     * @param SegNivelInternacionPractica[] $rows
     */
    public function persistPracticeRows(InpatientClinicalContext $ctx, array $rows): void
    {
        foreach ($rows as $row) {
            if (!$row instanceof SegNivelInternacionPractica) {
                continue;
            }
            $code = trim((string) $row->conceptId);
            if ($code === '') {
                continue;
            }
            $note = array_filter([
                $row->resultado ? 'resultado: ' . $row->resultado : null,
                $row->informe ? 'informe: ' . $row->informe : null,
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
     * @param SegNivelInternacionDiagnostico[] $rows
     */
    public function persistDiagnosisRows(InpatientClinicalContext $ctx, array $rows): void
    {
        foreach ($rows as $row) {
            if (!$row instanceof SegNivelInternacionDiagnostico) {
                continue;
            }
            $code = trim((string) $row->conceptId);
            if ($code === '') {
                continue;
            }
            $condition = new Condition();
            $condition->encounter_id = $ctx->encounter->id;
            $condition->subject_persona_id = $ctx->encounter->subject_persona_id;
            $condition->code = $code;
            $condition->display = $this->resolveSnomedTerm($code);
            $condition->clinical_status = $row->condition_clinical_status
                ?: DiagnosticoConsulta::CLINICAL_STATUS_ACTIVE;
            $condition->verification_status = $row->condition_verification_status
                ?: DiagnosticoConsulta::VERIFICATION_STATUS_CONFIRMED;
            $condition->recorded_date = date('Y-m-d H:i:s');
            $condition->note = 'inpatient:' . ($row->tipo_problema ?? 'diagnostico');
            $condition->save(false);
        }
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
