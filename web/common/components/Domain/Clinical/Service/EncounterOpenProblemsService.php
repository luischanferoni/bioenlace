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
     * Contrato API slim: ítems deduplicados + opciones compartidas (una sola vez).
     *
     * @return array{
     *   conditions: list<array<string, mixed>>,
     *   care_plans: list<array<string, mixed>>,
     *   condition_options?: list<array{value: string, label: string}>,
     *   care_plan_options?: list<array{value: string, label: string}>
     * }
     */
    public function forSubject(int $subjectPersonaId): array
    {
        if ($subjectPersonaId <= 0) {
            return ['conditions' => [], 'care_plans' => []];
        }

        $conditions = $this->buildConditions($subjectPersonaId);
        $carePlans = $this->buildCarePlans($subjectPersonaId);
        $out = [
            'conditions' => $conditions,
            'care_plans' => $carePlans,
        ];
        if ($conditions !== []) {
            $out['condition_options'] = ConditionClinicalStatus::closureOptions();
        }
        if ($carePlans !== []) {
            $out['care_plan_options'] = $this->defaultCarePlanClosureOptions();
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildConditions(int $subjectPersonaId): array
    {
        // Mismo dedupe/ranking que home HC (evita I10×N + SNOMED duplicados).
        $summaries = $this->conditionPresentation->listPatientSummaries($subjectPersonaId);
        $out = [];
        foreach ($summaries as $summary) {
            $id = (int) ($summary['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'kind' => 'condition',
                'label' => (string) ($summary['label'] ?? $summary['display'] ?? $summary['codigo'] ?? 'Condición'),
                'code' => (string) ($summary['codigo'] ?? ''),
                'clinical_status' => (string) ($summary['clinical_status'] ?? ''),
                'status_label' => (string) ($summary['statusLabel'] ?? ''),
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
        $seen = [];
        foreach ($plans as $plan) {
            if (!$plan instanceof CarePlan) {
                continue;
            }
            $presented = $this->carePlanPresentation->toPatientSummary($plan, true, 2);
            $title = trim((string) ($presented['title'] ?? ''));
            $categoryLabel = trim((string) ($presented['categoryLabel'] ?? $plan->category ?? ''));
            $label = $title !== '' ? $title : ($categoryLabel !== '' ? $categoryLabel : ('Plan #' . $plan->id));
            $dedupeKey = mb_strtolower($label . '|' . (string) ($plan->category ?? ''));
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;
            $out[] = [
                'id' => (int) $plan->id,
                'kind' => 'care_plan',
                'label' => $label,
                'category' => (string) ($plan->category ?? ''),
                'status' => (string) ($plan->status ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function defaultCarePlanClosureOptions(): array
    {
        return [
            ['value' => CarePlanStatus::ACTIVE, 'label' => 'Sigue activo'],
            ['value' => CarePlanStatus::COMPLETED, 'label' => 'Completado'],
            ['value' => CarePlanStatus::ON_HOLD, 'label' => 'En pausa'],
            ['value' => CarePlanStatus::REVOKED, 'label' => 'Revocado'],
        ];
    }
}
