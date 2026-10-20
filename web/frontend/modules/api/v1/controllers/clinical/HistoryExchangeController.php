<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Domain\Clinical\HistoryExchange\Application\Service\ClinicalHistoryOutboundQueryService;
use common\models\Clinical\ClinicalHistoryOutboundJob;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;

/**
 * Estado de export FHIR historia clínica (staff).
 *
 * GET /api/v1/clinical/history-exchange/listar-por-encounter?encounter_id=
 * GET /api/v1/clinical/history-exchange/ver-estado?job_id=
 */
class HistoryExchangeController extends BaseController
{
    use ClinicalAccessTrait;

    private ClinicalHistoryOutboundQueryService $query;

    public function init()
    {
        parent::init();
        $this->query = new ClinicalHistoryOutboundQueryService();
    }

    public function actionListarPorEncounter(): array
    {
        $encounterId = (int) (Yii::$app->request->get('encounter_id') ?? 0);
        if ($encounterId <= 0) {
            return $this->fail('encounter_id_required');
        }

        $this->assertEncounterAccess($encounterId);

        return $this->ok($this->query->listForEncounter($encounterId));
    }

    public function actionVerEstado(): array
    {
        $jobId = (int) (Yii::$app->request->get('job_id') ?? 0);
        if ($jobId <= 0) {
            return $this->fail('history_job_required');
        }

        $job = $this->query->findJob($jobId);
        if ($job === null) {
            return $this->fail('history_job_not_found');
        }

        $this->assertEncounterAccess((int) $job->encounter_id);

        return $this->ok(array_merge(
            $this->query->serializeJob($job),
            ['audit' => $this->query->auditTrail($jobId)]
        ));
    }
}
