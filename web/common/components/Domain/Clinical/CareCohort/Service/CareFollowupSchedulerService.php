<?php

namespace common\components\Domain\Clinical\CareCohort\Service;

use common\components\Domain\Clinical\CareCohort\Enum\CarePackType;
use common\components\Domain\Clinical\Enum\EncounterStatus;
use common\models\Clinical\CareCohortPack;
use common\models\Clinical\CareEncounterPack;
use common\models\Clinical\CareFollowupTouchpointQueue;
use common\models\Clinical\Encounter;
use common\models\Clinical\EncounterPatientSummary;
use common\models\Clinical\ServiceRequest;
use Yii;

/**
 * Programa touchpoints de followup_program al publicar resumen o al completar el pack.
 * Garantiza mínimo de touchpoints (pregunta/recordatorio) vía params care_cohort.followup.
 */
final class CareFollowupSchedulerService
{
    private CarePackRepository $repository;

    public function __construct(?CarePackRepository $repository = null)
    {
        $this->repository = $repository ?? new CarePackRepository();
    }

    public function tryScheduleForEncounter(int $encounterId, ?string $anchorAt = null): bool
    {
        if (!CarePackConfig::isEnabled()) {
            return false;
        }

        $encounter = Encounter::findOne(['id' => $encounterId, 'deleted_at' => null]);
        if ($encounter === null
            || $encounter->status !== EncounterStatus::FINISHED
            || $encounter->encounter_class !== Encounter::ENCOUNTER_CLASS_AMB) {
            return false;
        }

        $binding = $this->repository->findEncounterBinding($encounterId);
        if ($binding === null) {
            return false;
        }

        if ($binding->followup_scheduled_at !== null && $binding->followup_scheduled_at !== '') {
            return true;
        }

        $followupPack = $this->resolveFollowupPack($binding);
        if ($followupPack === null) {
            return false;
        }

        $content = $followupPack->getContentArray();
        if ($content === null) {
            $content = ['touchpoints' => []];
        }

        $touchpoints = $content['touchpoints'] ?? [];
        if (!is_array($touchpoints)) {
            $touchpoints = [];
        }

        $controlDelay = $this->resolveControlDelayDaysFromEncounter($encounterId);
        $touchpoints = $this->ensureMinTouchpoints($touchpoints, $controlDelay);

        if ($touchpoints === []) {
            $this->markScheduled($binding);

            return true;
        }

        $educationPack = $this->resolveEducationPack($binding);
        $anchorTs = $this->resolveAnchorTimestamp($encounterId, $anchorAt);
        $now = date('Y-m-d H:i:s');

        foreach ($touchpoints as $index => $tp) {
            if (!is_array($tp)) {
                continue;
            }
            $key = 'tp-' . (int) $index;
            if (CareFollowupTouchpointQueue::find()->where([
                'encounter_id' => $encounterId,
                'touchpoint_key' => $key,
            ])->exists()) {
                continue;
            }

            $delayDays = max(0, (int) ($tp['delay_days'] ?? 0));
            $runAt = date('Y-m-d H:i:s', $anchorTs + $delayDays * 86400);

            $refs = $tp['education_refs'] ?? [];
            if (!is_array($refs)) {
                $refs = [];
            }

            $row = new CareFollowupTouchpointQueue();
            $row->encounter_id = $encounterId;
            $row->subject_persona_id = (int) $encounter->subject_persona_id;
            $row->touchpoint_key = $key;
            $row->run_at = $runAt;
            $row->estado = CareFollowupTouchpointQueue::ESTADO_PENDIENTE;
            $row->title = trim((string) ($tp['title'] ?? 'Seguimiento')) ?: 'Seguimiento';
            $row->purpose = trim((string) ($tp['purpose'] ?? 'evolution')) ?: 'evolution';
            $row->form_kind = trim((string) ($tp['form_kind'] ?? 'evolution_short')) ?: 'evolution_short';
            $row->education_refs = json_encode(array_values(array_map('strval', $refs)), JSON_UNESCAPED_UNICODE);
            $row->followup_pack_id = (int) $followupPack->id;
            $row->education_pack_id = $educationPack !== null ? (int) $educationPack->id : null;
            $row->intentos = 0;
            $row->created_at = $now;
            $row->updated_at = $now;
            if (!$row->save(false)) {
                Yii::warning("No se pudo crear touchpoint {$key} encounter={$encounterId}", 'care-cohort');
            }
        }

        $this->markScheduled($binding);

        return true;
    }

    /**
     * Completa hasta min_touchpoints con defaults; aplica delay de control clínico si existe.
     *
     * @param list<mixed> $touchpoints
     * @return list<array<string, mixed>>
     */
    public function ensureMinTouchpoints(array $touchpoints, ?int $controlDelayDays = null): array
    {
        $normalized = [];
        foreach ($touchpoints as $tp) {
            if (is_array($tp)) {
                $normalized[] = $tp;
            }
        }

        $defaults = CarePackConfig::followupDefaultTouchpoints();
        $min = CarePackConfig::followupMinTouchpoints();
        $i = 0;
        while (count($normalized) < $min && $i < count($defaults)) {
            $normalized[] = $defaults[$i];
            $i++;
        }

        if ($controlDelayDays !== null && $controlDelayDays > 0 && $normalized !== []) {
            $idx = count($normalized) - 1;
            $normalized[$idx]['delay_days'] = $controlDelayDays;
            if (empty($normalized[$idx]['title'])) {
                $normalized[$idx]['title'] = 'Control de evolución';
            }
            if (empty($normalized[$idx]['form_kind'])) {
                $normalized[$idx]['form_kind'] = 'symptoms';
            }
        }

        return $normalized;
    }

    public function resolveControlDelayDaysFromEncounter(int $encounterId): ?int
    {
        $rows = ServiceRequest::find()
            ->where(['encounter_id' => $encounterId, 'deleted_at' => null])
            ->andWhere(['not', ['reminder_json' => null]])
            ->andWhere(['<>', 'reminder_json', ''])
            ->all();

        $max = null;
        foreach ($rows as $sr) {
            $json = json_decode((string) $sr->reminder_json, true);
            if (!is_array($json)) {
                continue;
            }
            $d = (int) ($json['delay_days'] ?? 0);
            if ($d > 0 && ($max === null || $d > $max)) {
                $max = $d;
            }
        }

        return $max;
    }

    public function trySchedulePendingForPack(int $followupPackId): int
    {
        if ($followupPackId <= 0) {
            return 0;
        }

        $bindings = CareEncounterPack::find()
            ->where(['followup_pack_id' => $followupPackId])
            ->andWhere(['or', ['followup_scheduled_at' => null], ['followup_scheduled_at' => '']])
            ->limit(100)
            ->all();

        $n = 0;
        foreach ($bindings as $binding) {
            if ($this->tryScheduleForEncounter((int) $binding->encounter_id)) {
                $n++;
            }
        }

        return $n;
    }

    private function resolveFollowupPack(CareEncounterPack $binding): ?CareCohortPack
    {
        if ((int) $binding->followup_pack_id > 0) {
            $pack = CareCohortPack::findOne(['id' => (int) $binding->followup_pack_id]);
            if ($pack instanceof CareCohortPack && !$pack->isExpired()) {
                return $pack;
            }
        }

        $pack = $this->repository->findValidPack(CarePackType::FOLLOWUP_PROGRAM, $binding->cohort_key);
        if ($pack !== null) {
            $this->repository->attachPackToEncounter(
                (int) $binding->encounter_id,
                CarePackType::FOLLOWUP_PROGRAM,
                (int) $pack->id
            );
        }

        return $pack;
    }

    private function resolveEducationPack(CareEncounterPack $binding): ?CareCohortPack
    {
        if ((int) $binding->education_pack_id > 0) {
            $pack = CareCohortPack::findOne(['id' => (int) $binding->education_pack_id]);
            if ($pack instanceof CareCohortPack && !$pack->isExpired()) {
                return $pack;
            }
        }

        $pack = $this->repository->findValidPack(CarePackType::EDUCATION_BUNDLE, $binding->cohort_key);
        if ($pack !== null) {
            $this->repository->attachPackToEncounter(
                (int) $binding->encounter_id,
                CarePackType::EDUCATION_BUNDLE,
                (int) $pack->id
            );
        }

        return $pack;
    }

    private function resolveAnchorTimestamp(int $encounterId, ?string $anchorAt): int
    {
        if ($anchorAt !== null && $anchorAt !== '') {
            $ts = strtotime($anchorAt);

            return $ts !== false ? $ts : time();
        }

        $summary = EncounterPatientSummary::findOne(['encounter_id' => $encounterId]);
        if ($summary !== null && !empty($summary->published_at)) {
            $ts = strtotime((string) $summary->published_at);

            return $ts !== false ? $ts : time();
        }

        return time();
    }

    private function markScheduled(CareEncounterPack $binding): void
    {
        $binding->followup_scheduled_at = date('Y-m-d H:i:s');
        $binding->updated_at = date('Y-m-d H:i:s');
        $binding->save(false);
    }
}
