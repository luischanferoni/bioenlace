<?php

namespace common\components\Clinical\Service;

use common\components\Clinical\Enum\CarePlanActivityKind;
use common\components\Clinical\Enum\CarePlanCategory;
use common\components\Clinical\Enum\CarePlanIntent;
use common\components\Clinical\Enum\CarePlanStatus;
use common\components\Clinical\Enum\RequestStatus;
use common\models\Clinical\CarePlan;
use common\models\Clinical\CarePlanActivity;
use common\models\Clinical\MedicationRequest;

final class CarePlanService
{
    public function createDraft(
        int $subjectPersonaId,
        string $category,
        ?int $encounterId = null,
        ?int $episodeOfCareId = null
    ): CarePlan {
        if (!CarePlanCategory::isValid($category)) {
            throw new \InvalidArgumentException("Categoría care_plan no válida: {$category}");
        }
        $plan = new CarePlan();
        $plan->subject_persona_id = $subjectPersonaId;
        $plan->status = CarePlanStatus::DRAFT;
        $plan->intent = CarePlanIntent::PLAN;
        $plan->category = $category;
        $plan->encounter_id = $encounterId;
        $plan->episode_of_care_id = $episodeOfCareId;
        $plan->period_start = date('Y-m-d H:i:s');
        if (!$plan->save()) {
            throw new \RuntimeException('No se pudo crear care_plan: ' . json_encode($plan->getErrors()));
        }

        return $plan;
    }

    public function assertMutable(CarePlan $plan): void
    {
        if (in_array($plan->status, [CarePlanStatus::COMPLETED, CarePlanStatus::REVOKED, CarePlanStatus::ENTERED_IN_ERROR], true)) {
            throw new \InvalidArgumentException(
                "El care plan #{$plan->id} está en estado «{$plan->status}» y no admite cambios."
            );
        }
    }

    public function hold(CarePlan $plan): CarePlan
    {
        $this->assertTransition($plan->status, CarePlanStatus::ON_HOLD);
        $plan->status = CarePlanStatus::ON_HOLD;
        $plan->save(false, ['status', 'updated_at', 'updated_by']);

        return $plan;
    }

    public function activate(CarePlan $plan): CarePlan
    {
        $this->assertMutable($plan);
        $this->assertTransition($plan->status, CarePlanStatus::ACTIVE);
        $plan->status = CarePlanStatus::ACTIVE;
        if ($plan->period_start === null) {
            $plan->period_start = date('Y-m-d H:i:s');
        }
        if (!$plan->save(false, ['status', 'period_start', 'updated_at', 'updated_by'])) {
            throw new \RuntimeException('No se pudo activar care_plan.');
        }

        return $plan;
    }

    public function complete(CarePlan $plan): CarePlan
    {
        $this->assertMutable($plan);
        $this->assertTransition($plan->status, CarePlanStatus::COMPLETED);
        $plan->status = CarePlanStatus::COMPLETED;
        $plan->period_end = date('Y-m-d H:i:s');
        $plan->save(false, ['status', 'period_end', 'updated_at', 'updated_by']);

        return $plan;
    }

    public function revoke(CarePlan $plan): CarePlan
    {
        $this->assertMutable($plan);
        $this->assertTransition($plan->status, CarePlanStatus::REVOKED);
        $plan->status = CarePlanStatus::REVOKED;
        $plan->period_end = date('Y-m-d H:i:s');
        $plan->save(false, ['status', 'period_end', 'updated_at', 'updated_by']);

        return $plan;
    }

    public function addMedicationActivity(CarePlan $plan, MedicationRequest $medication): CarePlanActivity
    {
        $sort = (int) CarePlanActivity::find()->where(['care_plan_id' => $plan->id])->max('sort_order');

        $activity = new CarePlanActivity();
        $activity->care_plan_id = $plan->id;
        $activity->kind = CarePlanActivityKind::MEDICATION_REQUEST;
        $activity->resource_type = 'MedicationRequest';
        $activity->resource_id = $medication->id;
        $activity->sort_order = $sort + 1;
        $activity->status = $medication->status ?? RequestStatus::ACTIVE;
        if (!$activity->save()) {
            throw new \RuntimeException('No se pudo registrar care_plan_activity: ' . json_encode($activity->getErrors()));
        }

        return $activity;
    }

    public function createAcutePlanForEncounter(int $subjectPersonaId, int $encounterId): CarePlan
    {
        $plan = $this->createDraft($subjectPersonaId, CarePlanCategory::ACUTE_AMBULATORY, $encounterId);

        return $this->activate($plan);
    }

    private function assertTransition(string $from, string $to): void
    {
        if (!CarePlanStatus::canTransition($from, $to)) {
            throw new \InvalidArgumentException("Transición care_plan no permitida: {$from} → {$to}");
        }
    }
}
