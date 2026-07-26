<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Clinical\Enum\CarePlanStatus;
use common\components\Domain\Clinical\Enum\ConditionClinicalStatus;
use common\models\Clinical\CarePlan;

/**
 * Problemas y planes abiertos del paciente para revisión al cerrar atención.
 *
 * Nada viene preseleccionado: el profesional confirma en el cliente.
 */
final class EncounterOpenProblemsService
{
    private PatientActiveConditionQuery $conditions;
    private PatientActiveCarePlanQuery $carePlans;
    private ConditionPresentationService $conditionPresentation;
    private CarePlanPresentationService $carePlanPresentation;

    public function __construct(
        ?PatientActiveConditionQuery $conditions = null,
        ?PatientActiveCarePlanQuery $carePlans = null,
        ?ConditionPresentationService $conditionPresentation = null,
        ?CarePlanPresentationService $carePlanPresentation = null
    ) {
        $this->conditions = $conditions ?? new PatientActiveConditionQuery();
        $this->carePlans = $carePlans ?? new PatientActiveCarePlanQuery();
        $this->conditionPresentation = $conditionPresentation ?? new ConditionPresentationService();
        $this->carePlanPresentation = $carePlanPresentation ?? new CarePlanPresentationService();
    }

    /**
     * @return array{
     *   conditions: list<array<string, mixed>>,
     *   care_plans: list<array<string, mixed>>
     * }
     */
    public function forSubject(int $subjectPersonaId): array
    {
        if ($subjectPersonaId <= 0) {
            return ['conditions' => [], 'care_plans' => []];
        }

        return [
            'conditions' => $this->buildConditions($subjectPersonaId),
            'care_plans' => $this->buildCarePlans($subjectPersonaId),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildConditions(int $subjectPersonaId): array
    {
        $rows = $this->conditions->listActive($subjectPersonaId, 40);
        $out = [];
        foreach ($rows as $cond) {
            $summary = $this->conditionPresentation->toPatientSummary($cond);
            $out[] = [
                'id' => (int) $cond->id,
                'kind' => 'condition',
                'label' => (string) ($summary['label'] ?? $cond->display ?? $cond->code ?? 'Condición'),
                'code' => (string) ($cond->code ?? ''),
                'clinical_status' => (string) ($cond->clinical_status ?? ''),
                'status_label' => (string) ($summary['statusLabel'] ?? ''),
                'options' => ConditionClinicalStatus::closureOptions(),
                'allow_custom' => false,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildCarePlans(int $subjectPersonaId): array
    {
        $plans = $this->carePlans->listActive($subjectPersonaId);
        $out = [];
        foreach ($plans as $plan) {
            if (!$plan instanceof CarePlan) {
                continue;
            }
            $presented = $this->carePlanPresentation->toPatientSummary($plan, true, 2);
            $title = trim((string) ($presented['title'] ?? ''));
            $categoryLabel = trim((string) ($presented['categoryLabel'] ?? $plan->category ?? ''));
            $label = $title !== '' ? $title : ($categoryLabel !== '' ? $categoryLabel : ('Plan #' . $plan->id));
            $out[] = [
                'id' => (int) $plan->id,
                'kind' => 'care_plan',
                'label' => $label,
                'category' => (string) ($plan->category ?? ''),
                'status' => (string) ($plan->status ?? ''),
                'options' => $this->carePlanClosureOptions((string) $plan->status),
                'allow_custom' => false,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function carePlanClosureOptions(string $currentStatus): array
    {
        $opts = [
            ['value' => CarePlanStatus::ACTIVE, 'label' => 'Sigue activo'],
            ['value' => CarePlanStatus::COMPLETED, 'label' => 'Completado'],
            ['value' => CarePlanStatus::ON_HOLD, 'label' => 'En pausa'],
            ['value' => CarePlanStatus::REVOKED, 'label' => 'Revocado'],
        ];
        if ($currentStatus === CarePlanStatus::ON_HOLD) {
            return $opts;
        }
        if ($currentStatus === CarePlanStatus::DRAFT) {
            return [
                ['value' => CarePlanStatus::ACTIVE, 'label' => 'Activar'],
                ['value' => CarePlanStatus::COMPLETED, 'label' => 'Completado'],
                ['value' => CarePlanStatus::REVOKED, 'label' => 'Revocado'],
            ];
        }

        return $opts;
    }
}
