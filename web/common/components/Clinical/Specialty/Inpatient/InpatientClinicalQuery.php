<?php

namespace common\components\Clinical\Specialty\Inpatient;

use common\components\Clinical\Dto\CarePlanDto;
use common\components\Clinical\Dto\MedicationRequestDto;
use common\components\Clinical\Dto\ServiceRequestDto;
use common\components\Clinical\Service\EpisodeOfCareService;
use common\models\Clinical\CarePlan;
use common\models\Clinical\Condition;
use common\models\Clinical\EpisodeOfCare;
use common\models\Clinical\MedicationRequest;
use common\models\Clinical\ServiceRequest;
use common\models\SegNivelInternacion;

/**
 * Lectura del bundle clínico de internación (staff / API).
 */
final class InpatientClinicalQuery
{
    /**
     * @return array<string, mixed>|null null si no hay episodio
     */
    public function bundleForInternacion(int $internacionId): ?array
    {
        $internacion = SegNivelInternacion::findOne($internacionId);
        if ($internacion === null) {
            return null;
        }

        $episode = (new EpisodeOfCareService())->findActiveForInternacion($internacionId);
        if ($episode === null) {
            $episode = EpisodeOfCare::find()
                ->andWhere(['internacion_id' => $internacionId, 'deleted_at' => null])
                ->orderBy(['id' => SORT_DESC])
                ->one();
        }
        if ($episode === null) {
            return null;
        }

        return $this->bundleForEpisode($episode, $internacion);
    }

    /**
     * @return array<string, mixed>
     */
    public function bundleForEpisode(EpisodeOfCare $episode, ?SegNivelInternacion $internacion = null): array
    {
        $encounter = InpatientClinicalContext::findOpenInpatientEncounter((int) $episode->internacion_id)
            ?? null;

        $encounterIds = [$encounter?->id];
        if ($encounterIds[0] === null) {
            $encounterIds = [];
        }

        $carePlans = CarePlan::find()
            ->andWhere(['episode_of_care_id' => $episode->id, 'deleted_at' => null])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $carePlanIds = array_map(static fn (CarePlan $p) => (int) $p->id, $carePlans);

        $medications = $this->listMedications($encounterIds, $carePlanIds);
        $practices = $this->listServiceRequests($encounterIds, $carePlanIds);
        $conditions = $this->listConditions($encounterIds);

        $planDtos = [];
        foreach ($carePlans as $plan) {
            $planDtos[] = CarePlanDto::fromModel($plan, true)->toArray();
        }

        return [
            'internacionId' => $internacion ? (int) $internacion->id : (int) $episode->internacion_id,
            'episode' => $this->episodeToArray($episode),
            'encounterId' => $encounter ? (int) $encounter->id : null,
            'carePlans' => $planDtos,
            'medicationRequests' => $medications,
            'serviceRequests' => $practices,
            'conditions' => $conditions,
            'isActive' => $episode->status === 'active',
        ];
    }

    /**
     * @param list<int> $encounterIds
     * @param list<int> $carePlanIds
     * @return list<array<string, mixed>>
     */
    private function listMedications(array $encounterIds, array $carePlanIds): array
    {
        $q = MedicationRequest::find()->andWhere(['deleted_at' => null]);
        if ($encounterIds !== [] || $carePlanIds !== []) {
            $or = ['or'];
            if ($encounterIds !== []) {
                $or[] = ['encounter_id' => $encounterIds];
            }
            if ($carePlanIds !== []) {
                $or[] = ['care_plan_id' => $carePlanIds];
            }
            $q->andWhere($or);
        } else {
            return [];
        }
        $out = [];
        foreach ($q->orderBy(['id' => SORT_ASC])->all() as $mr) {
            $out[] = MedicationRequestDto::fromModel($mr)->toArray();
        }

        return $out;
    }

    /**
     * @param list<int> $encounterIds
     * @param list<int> $carePlanIds
     * @return list<array<string, mixed>>
     */
    private function listServiceRequests(array $encounterIds, array $carePlanIds): array
    {
        $q = ServiceRequest::find()->andWhere(['deleted_at' => null]);
        if ($encounterIds !== [] || $carePlanIds !== []) {
            $or = ['or'];
            if ($encounterIds !== []) {
                $or[] = ['encounter_id' => $encounterIds];
            }
            if ($carePlanIds !== []) {
                $or[] = ['care_plan_id' => $carePlanIds];
            }
            $q->andWhere($or);
        } else {
            return [];
        }
        $out = [];
        foreach ($q->orderBy(['id' => SORT_ASC])->all() as $sr) {
            $out[] = ServiceRequestDto::fromModel($sr)->toArray();
        }

        return $out;
    }

    /**
     * @param list<int> $encounterIds
     * @return list<array<string, mixed>>
     */
    private function listConditions(array $encounterIds): array
    {
        if ($encounterIds === []) {
            return [];
        }
        $out = [];
        $rows = Condition::find()
            ->andWhere(['encounter_id' => $encounterIds, 'deleted_at' => null])
            ->orderBy(['id' => SORT_ASC])
            ->all();
        foreach ($rows as $c) {
            $out[] = [
                'resourceType' => 'Condition',
                'id' => (int) $c->id,
                'code' => $c->code,
                'display' => $c->display,
                'clinicalStatus' => $c->clinical_status,
                'verificationStatus' => $c->verification_status,
                'note' => $c->note,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function episodeToArray(EpisodeOfCare $episode): array
    {
        return [
            'resourceType' => 'EpisodeOfCare',
            'id' => (int) $episode->id,
            'subjectPersonaId' => (int) $episode->subject_persona_id,
            'status' => $episode->status,
            'typeCode' => $episode->type_code,
            'internacionId' => $episode->internacion_id !== null ? (int) $episode->internacion_id : null,
            'periodStart' => $episode->period_start,
            'periodEnd' => $episode->period_end,
            'title' => $episode->title,
        ];
    }
}
