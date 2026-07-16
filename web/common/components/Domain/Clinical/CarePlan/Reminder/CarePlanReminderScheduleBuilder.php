<?php

namespace common\components\Domain\Clinical\CarePlan\Reminder;

use common\components\Domain\Clinical\Enum\CarePlanActivityKind;
use common\components\Domain\Clinical\Enum\RequestStatus;
use common\components\Domain\Clinical\Service\PatientActiveCarePlanQuery;
use common\models\Clinical\CarePlan;
use common\models\Clinical\CarePlanActivity;
use common\models\Clinical\MedicationRequest;
use common\models\Clinical\ServiceRequest;

final class CarePlanReminderScheduleBuilder
{
    private PatientActiveCarePlanQuery $activeQuery;
    private MedicationDosageTimingParser $medicationTimingParser;
    private ActivityReminderTimingParser $activityTimingParser;
    private MedicationReminderFrequencyResolver $frequencyResolver;

    public function __construct(
        ?PatientActiveCarePlanQuery $activeQuery = null,
        ?MedicationDosageTimingParser $medicationTimingParser = null,
        ?ActivityReminderTimingParser $activityTimingParser = null,
        ?MedicationReminderFrequencyResolver $frequencyResolver = null
    ) {
        $this->activeQuery = $activeQuery ?? new PatientActiveCarePlanQuery();
        $this->medicationTimingParser = $medicationTimingParser ?? new MedicationDosageTimingParser();
        $this->activityTimingParser = $activityTimingParser ?? new ActivityReminderTimingParser();
        $this->frequencyResolver = $frequencyResolver ?? new MedicationReminderFrequencyResolver();
    }

    /**
     * @return array{generatedAt: string, version: int, items: list<array<string, mixed>>}
     */
    public function buildForPersona(int $subjectPersonaId, ?int $carePlanIdFilter = null): array
    {
        $items = [];
        foreach ($this->activeQuery->listActive($subjectPersonaId) as $plan) {
            if ($carePlanIdFilter !== null && (int) $plan->id !== $carePlanIdFilter) {
                continue;
            }
            foreach ($this->itemsForPlan($plan) as $dto) {
                $items[] = $dto->toArray();
            }
        }

        return [
            'generatedAt' => gmdate('c'),
            'version' => 2,
            'items' => $items,
        ];
    }

    /**
     * @return list<CarePlanReminderItemDto>
     */
    private function itemsForPlan(CarePlan $plan): array
    {
        $activities = CarePlanActivity::find()
            ->where(['care_plan_id' => (int) $plan->id, 'deleted_at' => null])
            ->orderBy(['sort_order' => SORT_ASC])
            ->all();

        $out = [];
        foreach ($activities as $activity) {
            $item = match ($activity->kind) {
                CarePlanActivityKind::MEDICATION_REQUEST => $this->itemFromMedicationActivity($plan, $activity),
                CarePlanActivityKind::SERVICE_REQUEST => $this->itemFromServiceActivity($plan, $activity),
                default => null,
            };
            if ($item !== null) {
                $out[] = $item;
            }
        }

        return $out;
    }

    private function itemFromMedicationActivity(CarePlan $plan, CarePlanActivity $activity): ?CarePlanReminderItemDto
    {
        $mr = MedicationRequest::findOne([
            'id' => (int) $activity->resource_id,
            'deleted_at' => null,
        ]);
        if ($mr === null || $mr->status !== RequestStatus::ACTIVE) {
            return null;
        }

        $dto = new CarePlanReminderItemDto();
        $dto->carePlanId = (int) $plan->id;
        $dto->activityId = (int) $activity->id;
        $dto->kind = CarePlanActivityKind::MEDICATION_REQUEST;
        $dto->resourceId = (int) $mr->id;
        $dto->notificationLabel = 'Medicación';
        $dto->title = trim((string) ($mr->medication_display ?? '')) !== ''
            ? (string) $mr->medication_display
            : 'Medicación';
        $dto->subtitle = trim((string) ($mr->dosage_text ?? ''));
        $dto->planStatus = (string) $plan->status;

        $parsed = $this->medicationTimingParser->parse($mr->dosage_json);
        if ($parsed === null) {
            $dto->requiresPatientSetup = true;
            $dto->schedule = $this->scheduleTemplateForPatientSetup(
                $this->frequencyResolver->resolveFromDosageText($mr->dosage_text),
                $plan
            );

            return $dto;
        }

        $dto->requiresPatientSetup = false;
        $dto->schedule = $this->scheduleFromParsed($parsed, $plan);

        return $dto;
    }

    private function itemFromServiceActivity(CarePlan $plan, CarePlanActivity $activity): ?CarePlanReminderItemDto
    {
        $sr = ServiceRequest::findOne([
            'id' => (int) $activity->resource_id,
            'deleted_at' => null,
        ]);
        if ($sr === null || $sr->status !== RequestStatus::ACTIVE) {
            return null;
        }

        $reminderJson = $sr->getAttribute('reminder_json');

        $dto = new CarePlanReminderItemDto();
        $dto->carePlanId = (int) $plan->id;
        $dto->activityId = (int) $activity->id;
        $dto->kind = CarePlanActivityKind::SERVICE_REQUEST;
        $dto->resourceId = (int) $sr->id;
        $dto->notificationLabel = 'Recordatorio de estudio';
        $dto->title = trim((string) ($sr->display ?? '')) !== ''
            ? (string) $sr->display
            : 'Estudio o práctica';
        $dto->subtitle = trim((string) ($sr->note ?? ''));
        $dto->planStatus = (string) $plan->status;

        $parsed = $this->activityTimingParser->parse($reminderJson);
        if ($parsed === null) {
            $dto->requiresPatientSetup = true;
            $dto->schedule = $this->scheduleTemplateForPatientSetup(
                ['dosesPerDay' => 1, 'intervalHours' => 24, 'period' => 1, 'periodUnit' => 'd'],
                $plan
            );

            return $dto;
        }

        $dto->requiresPatientSetup = false;
        $dto->schedule = $this->scheduleFromParsed($parsed, $plan);

        return $dto;
    }

    /**
     * @param array{dosesPerDay: int, intervalHours: int, period: int, periodUnit: string} $frequency
     * @return array<string, mixed>
     */
    private function scheduleTemplateForPatientSetup(array $frequency, CarePlan $plan): array
    {
        return [
            'timeOfDay' => [],
            'dosesPerDay' => (int) ($frequency['dosesPerDay'] ?? 1),
            'intervalHours' => (int) ($frequency['intervalHours'] ?? 24),
            'period' => (int) ($frequency['period'] ?? 1),
            'periodUnit' => (string) ($frequency['periodUnit'] ?? 'd'),
            'anchorTimeRequired' => true,
            'validFrom' => $plan->period_start,
            'validUntil' => $plan->period_end,
        ];
    }

    /**
     * @param array{timeOfDay: list<string>, period: int, periodUnit: string} $parsed
     * @return array<string, mixed>
     */
    private function scheduleFromParsed(array $parsed, CarePlan $plan): array
    {
        return [
            'timeOfDay' => $parsed['timeOfDay'],
            'period' => $parsed['period'],
            'periodUnit' => $parsed['periodUnit'],
            'validFrom' => $plan->period_start,
            'validUntil' => $plan->period_end,
        ];
    }
}
