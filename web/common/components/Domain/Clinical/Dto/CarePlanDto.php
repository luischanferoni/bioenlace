<?php

namespace common\components\Domain\Clinical\Dto;

use common\models\Clinical\CarePlan;
use common\models\Clinical\CarePlanActivity;

final class CarePlanDto
{
    public string $resourceType = 'CarePlan';

    public int $id;

    public int $subjectPersonaId;

    public string $status;

    public string $intent;

    public string $category;

    public ?int $encounterId;

    /** @var list<array<string, mixed>> */
    public array $activities = [];

    public static function fromModel(CarePlan $plan, bool $withActivities = false): self
    {
        $dto = new self();
        $dto->id = (int) $plan->id;
        $dto->subjectPersonaId = (int) $plan->subject_persona_id;
        $dto->status = (string) $plan->status;
        $dto->intent = (string) $plan->intent;
        $dto->category = (string) $plan->category;
        $dto->encounterId = $plan->encounter_id !== null ? (int) $plan->encounter_id : null;

        if ($withActivities) {
            $activities = $plan->activities ?? CarePlanActivity::find()
                ->where(['care_plan_id' => $plan->id])
                ->orderBy(['sort_order' => SORT_ASC])
                ->all();
            foreach ($activities as $activity) {
                $dto->activities[] = CarePlanActivityDto::fromModel($activity)->toArray();
            }
        }

        return $dto;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = [
            'resourceType' => $this->resourceType,
            'id' => $this->id,
            'subjectPersonaId' => $this->subjectPersonaId,
            'status' => $this->status,
            'intent' => $this->intent,
            'category' => $this->category,
            'encounterId' => $this->encounterId,
        ];
        if ($this->activities !== []) {
            $out['activities'] = $this->activities;
        }

        return $out;
    }
}
