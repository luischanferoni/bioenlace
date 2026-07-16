<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Clinical\Dto\MedicationRequestDto;
use common\components\Domain\Clinical\Enum\CarePlanActivityKind;
use common\components\Domain\Clinical\Enum\CarePlanStatus;
use common\models\Clinical\CarePlan;
use common\models\Clinical\CarePlanActivity;
use common\models\Clinical\MedicationRequest;

/**
 * Medicación activa vinculada a un CarePlan del paciente (selección en flows).
 */
final class CarePlanMedicationListService
{
    public function findActivePlanForPersona(int $carePlanId, int $idPersona): ?CarePlan
    {
        if ($carePlanId <= 0 || $idPersona <= 0) {
            return null;
        }

        $plan = CarePlan::findOne([
            'id' => $carePlanId,
            'subject_persona_id' => $idPersona,
            'deleted_at' => null,
        ]);
        if ($plan === null) {
            return null;
        }
        if (!in_array((string) $plan->status, [CarePlanStatus::ACTIVE, CarePlanStatus::ON_HOLD], true)) {
            return null;
        }

        return $plan;
    }

    /**
     * @return list<array{id: string, name: string, label: string, subtitle: string}>
     */
    public function listItemsForPlan(CarePlan $plan): array
    {
        $activities = CarePlanActivity::find()
            ->where([
                'care_plan_id' => (int) $plan->id,
                'kind' => CarePlanActivityKind::MEDICATION_REQUEST,
            ])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $items = [];
        $seen = [];
        foreach ($activities as $activity) {
            $mrId = (int) ($activity->resource_id ?? 0);
            if ($mrId <= 0 || isset($seen[$mrId])) {
                continue;
            }
            $mr = MedicationRequest::findOne(['id' => $mrId]);
            if ($mr === null) {
                continue;
            }
            $status = strtolower(trim((string) ($mr->status ?? '')));
            if (in_array($status, ['cancelled', 'entered-in-error', 'stopped'], true)) {
                continue;
            }
            $seen[$mrId] = true;
            $dto = MedicationRequestDto::fromModel($mr)->toArray();
            $name = (string) ($dto['medicationDisplay'] ?? $dto['medicationCode'] ?? 'Medicación');
            $dose = trim((string) ($dto['dosageText'] ?? ''));
            $items[] = [
                'id' => (string) $mrId,
                'name' => $name,
                'label' => $name,
                'subtitle' => $dose,
            ];
        }

        return $items;
    }

    /**
     * @param list<int> $medicationRequestIds
     * @return list<int> IDs válidos pertenecientes al plan
     */
    public function filterOwnedIds(CarePlan $plan, array $medicationRequestIds): array
    {
        $allowed = [];
        foreach ($this->listItemsForPlan($plan) as $row) {
            $allowed[(int) $row['id']] = true;
        }
        $out = [];
        foreach ($medicationRequestIds as $id) {
            $id = (int) $id;
            if ($id > 0 && isset($allowed[$id])) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param list<int> $medicationRequestIds
     * @return list<string>
     */
    public function labelsForIds(array $medicationRequestIds): array
    {
        $labels = [];
        foreach ($medicationRequestIds as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $mr = MedicationRequest::findOne(['id' => $id]);
            if ($mr === null) {
                continue;
            }
            $dto = MedicationRequestDto::fromModel($mr)->toArray();
            $name = trim((string) ($dto['medicationDisplay'] ?? $dto['medicationCode'] ?? ''));
            $dose = trim((string) ($dto['dosageText'] ?? ''));
            if ($name === '') {
                $name = 'Medicación #' . $id;
            }
            $labels[] = $dose !== '' ? ($name . ' (' . $dose . ')') : $name;
        }

        return $labels;
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    public static function parseIds($raw): array
    {
        if (is_array($raw)) {
            return array_values(array_unique(array_filter(array_map('intval', $raw))));
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return [];
        }
        $parts = preg_split('/\s*,\s*/', $s) ?: [];

        return array_values(array_unique(array_filter(array_map('intval', $parts))));
    }
}
