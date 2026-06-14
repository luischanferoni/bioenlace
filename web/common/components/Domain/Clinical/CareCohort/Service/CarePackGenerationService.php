<?php

namespace common\components\Domain\Clinical\CareCohort\Service;

use common\components\Platform\Ai\IAManager;
use common\components\Domain\Clinical\CareCohort\CarePackContentParser;
use common\components\Domain\Clinical\CareCohort\Enum\CarePackType;
use common\models\Clinical\CareCohortPack;
use common\models\Clinical\CarePackJob;
use common\models\Clinical\Encounter;
use Yii;

final class CarePackGenerationService
{
    private CarePackPromptBuilder $prompts;
    private CarePackContentParser $parser;
    private CarePackRepository $repository;

    public function __construct(
        ?CarePackPromptBuilder $prompts = null,
        ?CarePackContentParser $parser = null,
        ?CarePackRepository $repository = null
    ) {
        $this->prompts = $prompts ?? new CarePackPromptBuilder();
        $this->parser = $parser ?? new CarePackContentParser();
        $this->repository = $repository ?? new CarePackRepository();
    }

    public function processJob(CarePackJob $job, ?Encounter $encounter = null): bool
    {
        $profile = $job->cohort_profile_json
            ? json_decode($job->cohort_profile_json, true)
            : [];
        if (!is_array($profile)) {
            $profile = [];
        }

        $subjectPersonaId = (int) ($job->subject_persona_id ?? 0);
        if ($subjectPersonaId <= 0 && $encounter !== null) {
            $subjectPersonaId = (int) $encounter->subject_persona_id;
        }
        if ($subjectPersonaId <= 0) {
            $this->failJob($job, 'subject_persona_id requerido');

            return false;
        }

        if ($encounter === null && (int) $job->encounter_id > 0) {
            $encounter = Encounter::findOne(['id' => (int) $job->encounter_id, 'deleted_at' => null]);
        }

        $existing = $this->repository->findValidPack($job->pack_type, $job->cohort_key);
        if ($existing !== null) {
            $this->completeJob($job, $existing);

            return true;
        }

        $prompt = $this->prompts->build($job->pack_type, $profile, $subjectPersonaId, $encounter);
        $iaContext = CarePackType::iaContext($job->pack_type);

        $raw = IAManager::consultarIA($prompt, $iaContext, 'analysis');
        if ($raw === null || $raw === []) {
            $this->failJob($job, 'IA sin respuesta');

            return false;
        }

        $text = is_string($raw) ? $raw : json_encode($raw, JSON_UNESCAPED_UNICODE);
        $content = $this->parser->parse($text);
        if ($content === null) {
            $this->failJob($job, 'No se pudo parsear JSON del pack');

            return false;
        }

        $pack = $this->repository->savePack(
            $job->pack_type,
            $job->cohort_key,
            $profile,
            $content,
            $iaContext,
            CareCohortPack::SOURCE_SYNC
        );
        $this->completeJob($job, $pack);

        Yii::info("Care pack generado job={$job->id} pack={$pack->id} type={$job->pack_type}", 'care-cohort');

        return true;
    }

    private function completeJob(CarePackJob $job, CareCohortPack $pack): void
    {
        $job->status = CarePackJob::STATUS_COMPLETED;
        $job->pack_id = (int) $pack->id;
        $job->last_error = null;
        $job->updated_at = date('Y-m-d H:i:s');
        $job->save(false);
        if ((int) $job->encounter_id > 0) {
            $this->repository->attachPackToEncounter((int) $job->encounter_id, $job->pack_type, (int) $pack->id);
        }

        if ($job->pack_type === CarePackType::FOLLOWUP_PROGRAM) {
            $scheduler = new CareFollowupSchedulerService($this->repository);
            if ((int) $job->encounter_id > 0) {
                $scheduler->tryScheduleForEncounter((int) $job->encounter_id);
            } else {
                $scheduler->trySchedulePendingForPack((int) $pack->id);
            }
        }
    }

    private function failJob(CarePackJob $job, string $message): void
    {
        $job->attempts = (int) $job->attempts + 1;
        $job->last_error = $message;
        $job->updated_at = date('Y-m-d H:i:s');
        if ($job->attempts >= 5) {
            $job->status = CarePackJob::STATUS_FAILED;
        } else {
            $job->status = CarePackJob::STATUS_PENDING;
            $job->run_at = date('Y-m-d H:i:s', time() + min(60, 5 * $job->attempts) * 60);
        }
        $job->save(false);
        Yii::warning("Care pack job falló id={$job->id}: {$message}", 'care-cohort');
    }
}
