<?php

namespace common\components\Domain\Clinical\CareCohort\Service;

use common\components\Domain\Clinical\CareCohort\Enum\CarePackType;
use common\models\Clinical\CarePackJob;
use common\models\Clinical\Encounter;

final class CarePackJobEnqueuer
{
    private CarePackRepository $repository;

    public function __construct(?CarePackRepository $repository = null)
    {
        $this->repository = $repository ?? new CarePackRepository();
    }

    /**
     * @param array<string, mixed> $profile
     */
    public function enqueueIfNeeded(
        string $packType,
        string $cohortKey,
        array $profile,
        int $subjectPersonaId,
        ?int $encounterId = null
    ): ?CarePackJob {
        if (!in_array($packType, CarePackType::all(), true)) {
            throw new \InvalidArgumentException('pack_type inválido');
        }

        if ($this->repository->findValidPack($packType, $cohortKey) !== null) {
            return null;
        }

        if ($this->repository->hasPendingJob($packType, $cohortKey)) {
            return null;
        }

        $delay = CarePackConfig::generationDelayMinutes();
        $runAt = date('Y-m-d H:i:s', time() + $delay * 60);
        $mode = CarePackConfig::vertexBatchEnabled()
            ? CarePackJob::MODE_VERTEX_BATCH
            : CarePackJob::MODE_SYNC;

        $now = date('Y-m-d H:i:s');
        $job = new CarePackJob();
        $job->pack_type = $packType;
        $job->cohort_key = $cohortKey;
        $job->cohort_profile_json = json_encode($profile, JSON_UNESCAPED_UNICODE);
        $job->encounter_id = $encounterId;
        $job->subject_persona_id = $subjectPersonaId;
        $job->status = CarePackJob::STATUS_PENDING;
        $job->mode = $mode;
        $job->run_at = $runAt;
        $job->attempts = 0;
        $job->created_at = $now;
        $job->updated_at = $now;
        $job->save(false);

        return $job;
    }

    /**
     * @param list<string> $packTypes
     */
    public function enqueuePackSetForEncounter(Encounter $encounter, array $packTypes, string $cohortKey, array $profile): void
    {
        foreach ($packTypes as $packType) {
            $this->enqueueIfNeeded(
                $packType,
                $cohortKey,
                $profile,
                (int) $encounter->subject_persona_id,
                (int) $encounter->id
            );
        }
    }
}
