<?php

namespace console\controllers;

use common\components\Domain\Integrations\Scheduling\Service\FhirScheduleLinkReconcileService;
use common\components\Domain\Integrations\Scheduling\Service\FhirSchedulingInboundPullService;
use yii\console\Controller;

/**
 * Agendamiento FHIR entrante (HAPI NIS → turnos).
 *
 * php yii fhir-scheduling-inbound/pull
 * php yii fhir-scheduling-inbound/pull 100
 * php yii fhir-scheduling-inbound/reconcile-schedule-links
 *
 * @see web/docs/plans/fhir-scheduling-inbound/
 */
class FhirSchedulingInboundController extends Controller
{
    public function actionPull(int $limit = 50): int
    {
        $stats = (new FhirSchedulingInboundPullService())->pull(null, $limit);
        $this->stdout(sprintf(
            "Pull: processed=%d created=%d updated=%d errors=%d\n",
            $stats['processed'],
            $stats['created'],
            $stats['updated'],
            $stats['errors']
        ));

        return ($stats['errors'] ?? 0) > 0 ? 1 : 0;
    }

    public function actionReconcileScheduleLinks(int $limit = 100): int
    {
        $n = (new FhirScheduleLinkReconcileService())->reconcile(null, $limit);
        $this->stdout("Schedule links marcados stale: {$n}\n");

        return 0;
    }
}
