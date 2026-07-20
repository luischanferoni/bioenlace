<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Clinical\Dto\CarePlanActivityDto;
use common\components\Domain\Clinical\Dto\CarePlanDto;
use common\components\Domain\Clinical\Enum\CarePlanActivityKind;
use common\components\Domain\Clinical\Enum\CarePlanCategory;
use common\components\Domain\Clinical\Enum\CarePlanStatus;
use common\components\Domain\Scheduling\Service\ConsultasSeguimientoIntakeCatalogService;
use common\models\Clinical\CarePlan;
use common\models\Clinical\CarePlanActivity;
use common\models\Clinical\MedicationRequest;
use common\models\Clinical\ServiceRequest;

/**
 * Resúmenes de CarePlan para listados móvil / asistente.
 */
final class CarePlanPresentationService
{
    private static array $categoryLabels = [
        CarePlanCategory::ACUTE_AMBULATORY => 'Tratamiento ambulatorio',
        CarePlanCategory::CHRONIC => 'Tratamiento crónico',
        CarePlanCategory::PROGRAM => 'Programa de salud',
        CarePlanCategory::INPATIENT => 'Internación',
        CarePlanCategory::POSTOPERATIVE => 'Postoperatorio',
        CarePlanCategory::PREVENTIVE => 'Prevención',
        CarePlanCategory::PALLIATIVE => 'Cuidados paliativos',
        CarePlanCategory::ODONTOLOGY => 'Odontología',
        CarePlanCategory::OPHTHALMOLOGY => 'Oftalmología',
        CarePlanCategory::MENTAL_HEALTH => 'Salud mental',
        CarePlanCategory::REHABILITATION => 'Rehabilitación',
        CarePlanCategory::NUTRITION => 'Nutrición',
        CarePlanCategory::OTHER => 'Tratamiento',
    ];

    private static array $statusLabels = [
        CarePlanStatus::DRAFT => 'Borrador',
        CarePlanStatus::ACTIVE => 'Activo',
        CarePlanStatus::ON_HOLD => 'En pausa',
        CarePlanStatus::REVOKED => 'Revocado',
        CarePlanStatus::COMPLETED => 'Completado',
        CarePlanStatus::ENTERED_IN_ERROR => 'Con error',
        CarePlanStatus::UNKNOWN => 'Desconocido',
    ];

    /**
     * Ítem de lista UI JSON para elegir entre varios CarePlan activos.
     *
     * @return array{id: string, name: string, label: string, subtitle: string, meta: array<string, mixed>}
     */
    public function toPatientListPickItem(CarePlan $plan): array
    {
        $summary = $this->toPatientSummary($plan, true, 3);
        $categoryLabel = trim((string) ($summary['categoryLabel'] ?? $summary['category'] ?? 'Tratamiento'));
        $title = trim((string) ($summary['title'] ?? ''));
        $name = $title !== '' ? $title : ($categoryLabel !== '' ? $categoryLabel : 'Tratamiento');

        $subtitleParts = [];
        if ($title !== '' && $categoryLabel !== '' && strcasecmp($title, $categoryLabel) !== 0) {
            $subtitleParts[] = $categoryLabel;
        }
        $periodStart = trim((string) ($summary['periodStart'] ?? $plan->period_start ?? ''));
        if ($periodStart !== '') {
            $ts = strtotime($periodStart);
            if ($ts !== false) {
                $subtitleParts[] = 'Desde ' . date('d/m/Y', $ts);
            }
        }
        $lines = $summary['activitySummaries'] ?? [];
        if (is_array($lines)) {
            foreach (array_slice($lines, 0, 2) as $line) {
                $line = trim((string) $line);
                if ($line !== '') {
                    $subtitleParts[] = $line;
                }
            }
        }

        return [
            'id' => (string) $plan->id,
            'name' => $name,
            'label' => $name,
            'subtitle' => implode(' · ', $subtitleParts),
            'meta' => [
                'status' => $plan->status,
                'category' => $plan->category,
                'title' => $title !== '' ? $title : null,
                'period_start' => $periodStart !== '' ? $periodStart : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPatientSummary(CarePlan $plan, bool $withActivities = true, ?int $activityLimit = 5): array
    {
        $base = CarePlanDto::fromModel($plan, $withActivities)->toArray();
        $base['categoryLabel'] = self::$categoryLabels[$plan->category] ?? $plan->category;
        $base['statusLabel'] = self::$statusLabels[$plan->status] ?? $plan->status;
        $base['title'] = $plan->title !== null && $plan->title !== '' ? (string) $plan->title : null;
        $base['description'] = $plan->description !== null && $plan->description !== '' ? (string) $plan->description : null;
        $base['periodStart'] = $plan->period_start;
        if ($withActivities) {
            $base['activitySummaries'] = $this->summarizeActivities($plan, $activityLimit);
        }
        $base['seguimientoAcciones'] = (new ConsultasSeguimientoIntakeCatalogService())->accionesSeguimientoCarePlan();

        return $base;
    }

    /**
     * @return list<string>
     */
    private function summarizeActivities(CarePlan $plan, ?int $limit = 5): array
    {
        $query = CarePlanActivity::find()
            ->where(['care_plan_id' => $plan->id])
            ->orderBy(['sort_order' => SORT_ASC]);
        if ($limit !== null) {
            $query->limit($limit);
        }
        $activities = $query->all();

        $lines = [];
        foreach ($activities as $activity) {
            $line = $this->activityLine($activity);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function activityLine(CarePlanActivity $activity): string
    {
        if ($activity->kind === CarePlanActivityKind::MEDICATION_REQUEST) {
            $mr = MedicationRequest::findOne((int) $activity->resource_id);
            if ($mr === null) {
                return '';
            }
            $name = $mr->medication_display ?: 'Medicación';
            $dosage = $mr->dosage_text ? " — {$mr->dosage_text}" : '';

            return $name . $dosage;
        }
        if ($activity->kind === CarePlanActivityKind::SERVICE_REQUEST) {
            $sr = ServiceRequest::findOne((int) $activity->resource_id);
            if ($sr === null) {
                return '';
            }

            return $sr->display ?: ($sr->category === 'referral' ? 'Derivación' : 'Práctica');
        }

        return CarePlanActivityDto::fromModel($activity)->kind;
    }
}
