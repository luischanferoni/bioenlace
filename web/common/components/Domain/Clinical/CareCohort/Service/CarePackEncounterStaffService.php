<?php

namespace common\components\Domain\Clinical\CareCohort\Service;

use common\components\Domain\Clinical\CareCohort\Enum\CarePackType;
use common\models\Clinical\CareAssistanceResponse;
use common\models\Clinical\CareCohortPack;
use common\models\Clinical\CareEncounterPack;

/**
 * Contexto read-only de cohorte y packs para staff (historia clínica / encounter).
 */
final class CarePackEncounterStaffService
{
    private CarePackRepository $repository;

    public function __construct(?CarePackRepository $repository = null)
    {
        $this->repository = $repository ?? new CarePackRepository();
    }

    /**
     * @return array<string, mixed>|null null si la feature está apagada o no hay datos de cohorte
     */
    public function buildForEncounter(int $encounterId): ?array
    {
        if (!CarePackConfig::isEnabled() || $encounterId <= 0) {
            return null;
        }

        $binding = $this->repository->findEncounterBinding($encounterId);
        $response = CareAssistanceResponse::findOne(['encounter_id' => $encounterId]);

        if ($binding === null && $response === null) {
            return null;
        }

        $profile = $this->decodeProfile($binding);
        $assistancePack = $this->resolvePack(
            $binding !== null ? (int) ($binding->assistance_pack_id ?? 0) : 0,
            $response !== null ? (int) $response->pack_id : 0
        );

        $content = $assistancePack !== null ? ($assistancePack->getContentArray() ?? []) : [];
        $answers = $response !== null ? ($response->getAnswersArray() ?? []) : [];

        return [
            'encounter_id' => $encounterId,
            'cohort_key' => $binding !== null ? (string) $binding->cohort_key : null,
            'cohort_key_short' => $binding !== null
                ? substr((string) $binding->cohort_key, 0, 12)
                : null,
            'cohort_profile' => $profile,
            'packs' => [
                'assistance' => $this->packSummary(
                    CarePackType::ASSISTANCE_QUESTIONS,
                    $binding !== null ? (int) ($binding->assistance_pack_id ?? 0) : 0,
                    $assistancePack
                ),
                'followup' => $this->packSummary(
                    CarePackType::FOLLOWUP_PROGRAM,
                    $binding !== null ? (int) ($binding->followup_pack_id ?? 0) : 0,
                    $this->resolvePack($binding !== null ? (int) ($binding->followup_pack_id ?? 0) : 0)
                ),
                'education' => $this->packSummary(
                    CarePackType::EDUCATION_BUNDLE,
                    $binding !== null ? (int) ($binding->education_pack_id ?? 0) : 0,
                    $this->resolvePack($binding !== null ? (int) ($binding->education_pack_id ?? 0) : 0)
                ),
            ],
            'assistance' => [
                'status' => $this->assistanceStatus($binding, $response, $assistancePack),
                'notes_for_staff' => trim((string) ($content['notes_for_staff'] ?? '')),
                'submitted_at' => $response !== null ? (string) $response->submitted_at : null,
                'delta_requested' => $response !== null ? (bool) $response->delta_requested : false,
                'answers' => $this->formatAnswersForStaff($content, $answers),
            ],
            'followup_scheduled_at' => $binding !== null && $binding->followup_scheduled_at !== null
                ? (string) $binding->followup_scheduled_at
                : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeProfile(?CareEncounterPack $binding): ?array
    {
        if ($binding === null || $binding->cohort_profile_json === null || $binding->cohort_profile_json === '') {
            return null;
        }
        $decoded = json_decode((string) $binding->cohort_profile_json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function resolvePack(int $bindingPackId, int $fallbackPackId = 0): ?CareCohortPack
    {
        $packId = $bindingPackId > 0 ? $bindingPackId : $fallbackPackId;
        if ($packId <= 0) {
            return null;
        }
        $pack = CareCohortPack::findOne(['id' => $packId]);

        return $pack instanceof CareCohortPack ? $pack : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function packSummary(string $packType, int $packId, ?CareCohortPack $pack): ?array
    {
        if ($packId <= 0 && $pack === null) {
            return null;
        }
        $resolved = $pack ?? CareCohortPack::findOne(['id' => $packId]);
        if (!$resolved instanceof CareCohortPack) {
            return [
                'pack_type' => $packType,
                'pack_id' => $packId > 0 ? $packId : null,
                'status' => 'missing',
            ];
        }

        return [
            'pack_type' => (string) $resolved->pack_type,
            'pack_id' => (int) $resolved->id,
            'source' => (string) $resolved->source,
            'generated_at' => (string) $resolved->generated_at,
            'expires_at' => (string) $resolved->expires_at,
            'expired' => $resolved->isExpired(),
        ];
    }

    private function assistanceStatus(
        ?CareEncounterPack $binding,
        ?CareAssistanceResponse $response,
        ?CareCohortPack $pack
    ): string {
        if ($response !== null) {
            return 'submitted';
        }
        if ($pack !== null) {
            return 'ready';
        }
        if ($binding !== null) {
            $cohortKey = (string) $binding->cohort_key;
            if ($cohortKey !== '' && $this->repository->hasPendingJob(CarePackType::ASSISTANCE_QUESTIONS, $cohortKey)) {
                return 'generating';
            }
        }

        return 'pending';
    }

    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $answers
     * @return list<array<string, mixed>>
     */
    private function formatAnswersForStaff(array $content, array $answers): array
    {
        if ($answers === []) {
            return [];
        }

        $questions = $content['questions'] ?? [];
        if (!is_array($questions)) {
            $questions = [];
        }

        $labels = [];
        foreach ($questions as $q) {
            if (!is_array($q)) {
                continue;
            }
            $id = trim((string) ($q['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $labels[$id] = trim((string) ($q['text'] ?? $id));
        }

        $out = [];
        foreach ($answers as $key => $value) {
            $id = trim((string) $key);
            if ($id === '') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'question' => $labels[$id] ?? $id,
                'answer' => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            ];
        }

        return $out;
    }
}
