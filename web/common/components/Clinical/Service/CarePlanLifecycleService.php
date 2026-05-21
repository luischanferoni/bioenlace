<?php

namespace common\components\Clinical\Service;

use common\components\Clinical\Enum\CarePlanCategory;
use common\components\Clinical\Enum\CarePlanStatus;
use common\components\Clinical\Enum\EncounterStatus;
use common\components\Clinical\Support\CarePlanProgramMeta;
use common\models\Clinical\CarePlan;
use common\models\Clinical\Encounter;
use common\models\Clinical\EpisodeOfCare;
use common\models\SegNivelInternacion;

/**
 * Reglas de ciclo de vida CarePlan (internación, ambulatorio, crónico, programa).
 */
final class CarePlanLifecycleService
{
    private CarePlanService $carePlans;
    private EncounterLifecycleService $encounters;
    private EpisodeOfCareService $episodes;

    public function __construct(
        ?CarePlanService $carePlans = null,
        ?EncounterLifecycleService $encounters = null,
        ?EpisodeOfCareService $episodes = null
    ) {
        $this->carePlans = $carePlans ?? new CarePlanService();
        $this->encounters = $encounters ?? new EncounterLifecycleService();
        $this->episodes = $episodes ?? new EpisodeOfCareService();
    }

    public function onInternacionAdmission(SegNivelInternacion $internacion): EpisodeOfCare
    {
        $episode = $this->episodes->startInpatient($internacion);
        $existing = CarePlan::find()
            ->andWhere(['episode_of_care_id' => $episode->id, 'category' => CarePlanCategory::INPATIENT])
            ->andWhere(['status' => [CarePlanStatus::DRAFT, CarePlanStatus::ACTIVE, CarePlanStatus::ON_HOLD]])
            ->andWhere(['deleted_at' => null])
            ->one();
        if ($existing !== null) {
            $this->ensureInpatientEncounter($internacion, $episode);

            return $episode;
        }

        $plan = $this->carePlans->createDraft(
            (int) $internacion->id_persona,
            CarePlanCategory::INPATIENT,
            null,
            (int) $episode->id
        );
        $this->carePlans->activate($plan);
        $this->ensureInpatientEncounter($internacion, $episode);

        return $episode;
    }

    private function ensureInpatientEncounter(SegNivelInternacion $internacion, EpisodeOfCare $episode): Encounter
    {
        $parentType = Encounter::PARENT_CLASSES[Encounter::PARENT_INTERNACION];
        $existing = Encounter::find()
            ->andWhere([
                'parent_type' => $parentType,
                'parent_id' => (int) $internacion->id,
                'encounter_class' => Encounter::ENCOUNTER_CLASS_IMP,
                'status' => EncounterStatus::IN_PROGRESS,
            ])
            ->andWhere(['deleted_at' => null])
            ->one();

        if ($existing instanceof Encounter) {
            return $existing;
        }

        return $this->encounters->start([
            'subject_persona_id' => (int) $internacion->id_persona,
            'encounter_class' => Encounter::ENCOUNTER_CLASS_IMP,
            'parent_type' => $parentType,
            'parent_id' => (int) $internacion->id,
            'efector_id' => $episode->efector_id,
            'id_profesional_efector_servicio' => $internacion->id_profesional_efector_servicio,
            'reason_text' => 'Internación #' . $internacion->id,
        ]);
    }

    /**
     * Alta de internación: cierra episodio, planes inpatient y encounters IMP vinculados.
     *
     * @param bool $createAmbulatoryContinuity Si true, crea plan `chronic` (continuidad ambulatoria).
     */
    public function completeOnDischarge(
        SegNivelInternacion $internacion,
        ?string $dischargeAt = null,
        bool $createAmbulatoryContinuity = false
    ): void {
        $dischargeAt = $dischargeAt ?? $this->internacionDischargeDatetime($internacion);
        $episode = $this->episodes->findActiveForInternacion((int) $internacion->id);
        if ($episode !== null) {
            $this->episodes->finish($episode, $dischargeAt);
            $this->completeInpatientPlansForEpisode($episode, $dischargeAt);
        }

        $this->finalizeInternacionEncounters((int) $internacion->id, $dischargeAt);

        if ($createAmbulatoryContinuity) {
            $this->createChronicPlan((int) $internacion->id_persona, true);
        }
    }

    /**
     * Al cerrar encounter ambulatorio: completa planes agudos salvo categorías persistentes.
     *
     * @param array<string, mixed> $options
     *   - continue_treatment (bool): promover a plan chronic (revoca crónicos previos).
     *   - complete_acute (bool): default true.
     */
    public function onEncounterClose(Encounter $encounter, array $options = []): void
    {
        $continueTreatment = !empty($options['continue_treatment']);
        $completeAcute = ($options['complete_acute'] ?? true) !== false;

        if ($completeAcute) {
            $this->completeEncounterLinkedPlans($encounter);
        }

        if ($continueTreatment) {
            $this->createChronicPlan((int) $encounter->subject_persona_id, true);
        }
    }

    public function hold(CarePlan $plan): CarePlan
    {
        $this->carePlans->assertMutable($plan);

        return $this->carePlans->hold($plan);
    }

    public function activate(CarePlan $plan): CarePlan
    {
        $this->carePlans->assertMutable($plan);

        return $this->carePlans->activate($plan);
    }

    public function complete(CarePlan $plan): CarePlan
    {
        $this->carePlans->assertMutable($plan);

        return $this->carePlans->complete($plan);
    }

    public function revoke(CarePlan $plan): CarePlan
    {
        $this->carePlans->assertMutable($plan);

        return $this->carePlans->revoke($plan);
    }

    public function createChronicPlan(int $subjectPersonaId, bool $revokePrior = true): CarePlan
    {
        if ($revokePrior) {
            $this->revokeActiveByCategory($subjectPersonaId, CarePlanCategory::CHRONIC);
        }
        $plan = $this->carePlans->createDraft($subjectPersonaId, CarePlanCategory::CHRONIC);

        return $this->carePlans->activate($plan);
    }

    public function createProgramPlan(
        int $subjectPersonaId,
        int $occurrenceTotal,
        string $category = CarePlanCategory::PROGRAM
    ): CarePlan {
        if (!CarePlanCategory::isProgramLike($category) && $category !== CarePlanCategory::PROGRAM) {
            throw new \InvalidArgumentException("Categoría no válida para programa: {$category}");
        }
        $plan = $this->carePlans->createDraft($subjectPersonaId, $category);
        $plan->description = CarePlanProgramMeta::encode($occurrenceTotal, 0);

        return $this->carePlans->activate($plan);
    }

    /**
     * Registra una sesión de programa; auto-completa si se agotan ocurrencias o venció period_end.
     */
    public function recordProgramSession(CarePlan $plan): CarePlan
    {
        $this->carePlans->assertMutable($plan);
        if (!CarePlanCategory::isProgramLike($plan->category) && $plan->category !== CarePlanCategory::PROGRAM) {
            throw new \InvalidArgumentException('El care plan no es de tipo programa.');
        }

        $meta = CarePlanProgramMeta::parse($plan->description);
        $meta['occurrenceCount']++;
        $plan->description = CarePlanProgramMeta::encode($meta['occurrenceTotal'], $meta['occurrenceCount']);
        $plan->save(false, ['description', 'updated_at', 'updated_by']);

        if (CarePlanProgramMeta::isExhausted($plan->description)) {
            return $this->carePlans->complete($plan);
        }
        if ($plan->period_end !== null && strtotime($plan->period_end) <= time()) {
            return $this->carePlans->complete($plan);
        }

        return $plan;
    }

    private function completeInpatientPlansForEpisode(EpisodeOfCare $episode, string $periodEnd): void
    {
        $plans = CarePlan::find()
            ->andWhere(['episode_of_care_id' => $episode->id, 'category' => CarePlanCategory::INPATIENT])
            ->andWhere(['status' => [CarePlanStatus::ACTIVE, CarePlanStatus::ON_HOLD, CarePlanStatus::DRAFT]])
            ->andWhere(['deleted_at' => null])
            ->all();

        foreach ($plans as $plan) {
            if ($plan->status === CarePlanStatus::COMPLETED || $plan->status === CarePlanStatus::REVOKED) {
                continue;
            }
            $this->carePlans->complete($plan);
        }
    }

    private function completeEncounterLinkedPlans(Encounter $encounter): void
    {
        $plans = CarePlan::find()
            ->andWhere(['encounter_id' => $encounter->id])
            ->andWhere(['deleted_at' => null])
            ->andWhere(['status' => [CarePlanStatus::ACTIVE, CarePlanStatus::DRAFT, CarePlanStatus::ON_HOLD]])
            ->all();

        foreach ($plans as $plan) {
            if (!CarePlanCategory::completesOnEncounterClose($plan->category)) {
                continue;
            }
            if ($plan->status === CarePlanStatus::DRAFT) {
                $this->carePlans->activate($plan);
            }
            $this->carePlans->complete($plan);
        }
    }

    private function finalizeInternacionEncounters(int $internacionId, string $periodEnd): void
    {
        $encounters = Encounter::find()
            ->andWhere([
                'parent_type' => Encounter::PARENT_CLASSES[Encounter::PARENT_INTERNACION],
                'parent_id' => $internacionId,
            ])
            ->andWhere(['encounter_class' => Encounter::ENCOUNTER_CLASS_IMP])
            ->andWhere(['status' => EncounterStatus::IN_PROGRESS])
            ->andWhere(['deleted_at' => null])
            ->all();

        foreach ($encounters as $encounter) {
            $encounter->period_end = $periodEnd;
            $this->encounters->finalize($encounter);
        }
    }

    private function revokeActiveByCategory(int $subjectPersonaId, string $category): void
    {
        $plans = CarePlan::find()
            ->andWhere([
                'subject_persona_id' => $subjectPersonaId,
                'category' => $category,
                'status' => [CarePlanStatus::ACTIVE, CarePlanStatus::ON_HOLD, CarePlanStatus::DRAFT],
            ])
            ->andWhere(['deleted_at' => null])
            ->all();

        foreach ($plans as $plan) {
            $this->carePlans->revoke($plan);
        }
    }

    private function internacionDischargeDatetime(SegNivelInternacion $internacion): string
    {
        $fecha = (string) ($internacion->fecha_fin ?? '');
        $hora = (string) ($internacion->hora_fin ?? '00:00:00');
        if ($fecha === '') {
            return date('Y-m-d H:i:s');
        }
        if (strlen($hora) === 5) {
            $hora .= ':00';
        }

        return $fecha . ' ' . $hora;
    }
}
