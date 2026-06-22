<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Domain\Clinical\HistoryExchange\ClinicalHistoryOutboundQueryService;
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
            return $this->clinicalError('Se requiere encounter_id.', null, 400);
        }

        [$encounter, $error] = $this->requireEncounterAccess($encounterId);
        if ($error !== null) {
            return $error;
        }

        return [
            'success' => true,
            'data' => $this->query->listForEncounter($encounterId),
        ];
    }

    public function actionVerEstado(): array
    {
        $jobId = (int) (Yii::$app->request->get('job_id') ?? 0);
        if ($jobId <= 0) {
            return $this->clinicalError('Se requiere job_id.', null, 400);
        }

        $job = $this->query->findJob($jobId);
        if ($job === null) {
            Yii::$app->response->statusCode = 404;

            return $this->clinicalError('Job no encontrado.', null, 404);
        }

        [$encounter, $error] = $this->requireEncounterAccess((int) $job->encounter_id);
        if ($error !== null) {
            return $error;
        }

        return [
            'success' => true,
            'data' => array_merge(
                $this->query->serializeJob($job),
                ['audit' => $this->query->auditTrail($jobId)]
            ),
        ];
    }
}
