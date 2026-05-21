<?php

namespace common\components\Clinical\Service;

use common\components\Clinical\Dto\CarePlanActivityDto;
use common\components\Clinical\Dto\CarePlanDto;
use common\components\Clinical\Enum\CarePlanActivityKind;
use common\components\Clinical\Enum\CarePlanCategory;
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

    /**
     * @return array<string, mixed>
     */
    public function toPatientSummary(CarePlan $plan, bool $withActivities = true): array
    {
        $base = CarePlanDto::fromModel($plan, $withActivities)->toArray();
        $base['categoryLabel'] = self::$categoryLabels[$plan->category] ?? $plan->category;
        if ($withActivities) {
            $base['activitySummaries'] = $this->summarizeActivities($plan);
        }

        return $base;
    }

    /**
     * @return list<string>
     */
    private function summarizeActivities(CarePlan $plan): array
    {
        $activities = CarePlanActivity::find()
            ->where(['care_plan_id' => $plan->id])
            ->orderBy(['sort_order' => SORT_ASC])
            ->limit(5)
            ->all();

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
