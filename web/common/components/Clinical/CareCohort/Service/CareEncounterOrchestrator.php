<?php

namespace common\components\Clinical\CareCohort\Service;

use common\components\Clinical\CareCohort\CohortKeyBuilder;
use common\components\Clinical\CareCohort\Enum\CarePackType;
use common\models\Clinical\Encounter;

/**
 * Programa packs de cohorte al crear encounter (asistencia) y al finalizar (seguimiento/educación).
 */
final class CareEncounterOrchestrator
{
    private CohortKeyBuilder $cohortBuilder;
    private CarePackRepository $repository;
    private CarePackJobEnqueuer $enqueuer;

    public function __construct(
        ?CohortKeyBuilder $cohortBuilder = null,
        ?CarePackRepository $repository = null,
        ?CarePackJobEnqueuer $enqueuer = null
    ) {
        $this->cohortBuilder = $cohortBuilder ?? new CohortKeyBuilder();
        $this->repository = $repository ?? new CarePackRepository();
        $this->enqueuer = $enqueuer ?? new CarePackJobEnqueuer($this->repository);
    }

    public function onEncounterEnsured(Encounter $encounter): void
    {
        if (!CarePackConfig::isEnabled()) {
            return;
        }
        if ($encounter->encounter_class !== Encounter::ENCOUNTER_CLASS_AMB) {
            return;
        }

        $built = $this->cohortBuilder->buildForPersona((int) $encounter->subject_persona_id, $encounter);
        $this->bindAndSchedule($encounter, $built['cohort_key'], $built['profile'], [
            CarePackType::ASSISTANCE_QUESTIONS,
        ]);
    }

    public function onEncounterFinalized(Encounter $encounter): void
    {
        if (!CarePackConfig::isEnabled()) {
            return;
        }
        if ($encounter->encounter_class !== Encounter::ENCOUNTER_CLASS_AMB) {
            return;
        }

        $built = $this->cohortBuilder->buildForPersona((int) $encounter->subject_persona_id, $encounter);
        $this->bindAndSchedule($encounter, $built['cohort_key'], $built['profile'], [
            CarePackType::FOLLOWUP_PROGRAM,
            CarePackType::EDUCATION_BUNDLE,
        ]);
    }

    /**
     * @param array<string, mixed> $profile
     * @param list<string> $packTypes
     */
    private function bindAndSchedule(Encounter $encounter, string $cohortKey, array $profile, array $packTypes): void
    {
        $assistanceId = null;
        $followupId = null;
        $educationId = null;

        foreach ($packTypes as $packType) {
            $pack = $this->repository->findValidPack($packType, $cohortKey);
            if ($packType === CarePackType::ASSISTANCE_QUESTIONS) {
                $assistanceId = $pack !== null ? (int) $pack->id : null;
            } elseif ($packType === CarePackType::FOLLOWUP_PROGRAM) {
                $followupId = $pack !== null ? (int) $pack->id : null;
            } elseif ($packType === CarePackType::EDUCATION_BUNDLE) {
                $educationId = $pack !== null ? (int) $pack->id : null;
            }
        }

        $this->repository->upsertEncounterBinding(
            (int) $encounter->id,
            (int) $encounter->subject_persona_id,
            $cohortKey,
            $profile,
            $assistanceId,
            $followupId,
            $educationId
        );

        $this->enqueuer->enqueuePackSetForEncounter($encounter, $packTypes, $cohortKey, $profile);
    }
}
