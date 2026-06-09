<?php

namespace common\components\Clinical\CareCohort\Service;

use common\components\Clinical\CareCohort\Enum\CarePackType;
use common\models\Clinical\CareCohortPack;
use common\models\Clinical\CareEncounterPack;
use common\models\Clinical\CarePackJob;

final class CarePackRepository
{
    public function findValidPack(string $packType, string $cohortKey): ?CareCohortPack
    {
        $row = CareCohortPack::find()
            ->where([
                'pack_type' => $packType,
                'cohort_key' => $cohortKey,
            ])
            ->andWhere(['>', 'expires_at', date('Y-m-d H:i:s')])
            ->orderBy(['generated_at' => SORT_DESC])
            ->one();

        return $row instanceof CareCohortPack ? $row : null;
    }

    /**
     * @param array<string, mixed> $profile
     */
    public function savePack(
        string $packType,
        string $cohortKey,
        array $profile,
        array $content,
        string $iaContext,
        string $source
    ): CareCohortPack {
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + CarePackConfig::packTtlDays() * 86400);

        $pack = CareCohortPack::findOne(['pack_type' => $packType, 'cohort_key' => $cohortKey])
            ?? new CareCohortPack();

        $pack->pack_type = $packType;
        $pack->cohort_key = $cohortKey;
        $pack->cohort_profile_json = json_encode($profile, JSON_UNESCAPED_UNICODE);
        $pack->content_json = json_encode($content, JSON_UNESCAPED_UNICODE);
        $pack->ia_context = $iaContext;
        $pack->source = $source;
        $pack->generated_at = $now;
        $pack->expires_at = $expires;
        $pack->updated_at = $now;
        if ($pack->isNewRecord) {
            $pack->created_at = $now;
        }
        $pack->save(false);

        return $pack;
    }

    public function findEncounterBinding(int $encounterId): ?CareEncounterPack
    {
        $row = CareEncounterPack::findOne(['encounter_id' => $encounterId]);

        return $row instanceof CareEncounterPack ? $row : null;
    }

    /**
     * @param array<string, mixed> $profile
     */
    public function upsertEncounterBinding(
        int $encounterId,
        int $subjectPersonaId,
        string $cohortKey,
        array $profile,
        ?int $assistancePackId = null,
        ?int $followupPackId = null,
        ?int $educationPackId = null
    ): CareEncounterPack {
        $now = date('Y-m-d H:i:s');
        $binding = $this->findEncounterBinding($encounterId) ?? new CareEncounterPack();
        $binding->encounter_id = $encounterId;
        $binding->subject_persona_id = $subjectPersonaId;
        $binding->cohort_key = $cohortKey;
        $binding->cohort_profile_json = json_encode($profile, JSON_UNESCAPED_UNICODE);
        if ($assistancePackId !== null) {
            $binding->assistance_pack_id = $assistancePackId;
        }
        if ($followupPackId !== null) {
            $binding->followup_pack_id = $followupPackId;
        }
        if ($educationPackId !== null) {
            $binding->education_pack_id = $educationPackId;
        }
        $binding->updated_at = $now;
        if ($binding->isNewRecord) {
            $binding->created_at = $now;
        }
        $binding->save(false);

        return $binding;
    }

    public function hasPendingJob(string $packType, string $cohortKey): bool
    {
        return CarePackJob::find()
            ->where([
                'pack_type' => $packType,
                'cohort_key' => $cohortKey,
            ])
            ->andWhere([
                'status' => [
                    CarePackJob::STATUS_PENDING,
                    CarePackJob::STATUS_RUNNING,
                    CarePackJob::STATUS_VERTEX_SUBMITTED,
                ],
            ])
            ->exists();
    }

    public function attachPackToEncounter(int $encounterId, string $packType, int $packId): void
    {
        if ($encounterId <= 0 || $packId <= 0) {
            return;
        }
        $binding = $this->findEncounterBinding($encounterId);
        if ($binding === null) {
            return;
        }
        switch ($packType) {
            case CarePackType::ASSISTANCE_QUESTIONS:
                $binding->assistance_pack_id = $packId;
                break;
            case CarePackType::FOLLOWUP_PROGRAM:
                $binding->followup_pack_id = $packId;
                break;
            case CarePackType::EDUCATION_BUNDLE:
                $binding->education_pack_id = $packId;
                break;
            default:
                return;
        }
        $binding->updated_at = date('Y-m-d H:i:s');
        $binding->save(false);
    }
}
