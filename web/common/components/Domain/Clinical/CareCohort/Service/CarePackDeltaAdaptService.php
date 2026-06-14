<?php

namespace common\components\Domain\Clinical\CareCohort\Service;

use common\components\Domain\Clinical\CareCohort\Enum\CarePackType;
use common\models\Clinical\CareCohortPack;
use common\models\Clinical\CarePackJob;
use common\models\Clinical\Encounter;

/**
 * Encola adaptación delta del pack de asistencia para un encounter concreto.
 */
final class CarePackDeltaAdaptService
{
    private CarePackRepository $repository;

    public function __construct(?CarePackRepository $repository = null)
    {
        $this->repository = $repository ?? new CarePackRepository();
    }

    /**
     * @param array<string, mixed> $answers
     */
    public function requestAssistanceDelta(Encounter $encounter, CareCohortPack $basePack, array $answers): void
    {
        $profile = $basePack->getProfileArray() ?? [];
        $profile['delta_context'] = [
            'base_pack_id' => (int) $basePack->id,
            'encounter_id' => (int) $encounter->id,
            'answers' => $answers,
        ];

        $deltaKey = hash('sha256', $basePack->cohort_key . ':delta:' . (int) $encounter->id);

        if ($this->repository->findValidPack(CarePackType::ASSISTANCE_QUESTIONS, $deltaKey) !== null) {
            return;
        }

        if ($this->repository->hasPendingJob(CarePackType::ASSISTANCE_QUESTIONS, $deltaKey)) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $job = new CarePackJob();
        $job->pack_type = CarePackType::ASSISTANCE_QUESTIONS;
        $job->cohort_key = $deltaKey;
        $job->cohort_profile_json = json_encode($profile, JSON_UNESCAPED_UNICODE);
        $job->encounter_id = (int) $encounter->id;
        $job->subject_persona_id = (int) $encounter->subject_persona_id;
        $job->status = CarePackJob::STATUS_PENDING;
        $job->mode = CarePackJob::MODE_SYNC;
        $job->run_at = $now;
        $job->attempts = 0;
        $job->created_at = $now;
        $job->updated_at = $now;
        $job->save(false);
    }
}
