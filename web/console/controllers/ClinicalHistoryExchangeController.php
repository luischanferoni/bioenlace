<?php

namespace console\controllers;

use common\components\Domain\Clinical\HistoryExchange\ClinicalHistoryOutboundProcessorService;
use common\components\Domain\Clinical\HistoryExchange\ClinicalHistoryOutboundReconcileService;
use common\models\Clinical\ClinicalHistoryOutboundJob;
use yii\console\Controller;

/**
 * Export FHIR historia clínica → servidor nacional (cola saliente).
 *
 * php yii clinical-history-exchange/process-outbound
 * php yii clinical-history-exchange/process-outbound 50
 * php yii clinical-history-exchange/process-one 123
 * php yii clinical-history-exchange/reconcile
 * php yii clinical-history-exchange/reconcile 100
 *
 * @see web/docs/plans/interoperabilidad-historia-clinica/phases/01-estructura-y-cola.md
 */
class ClinicalHistoryExchangeController extends Controller
{
    public function actionProcessOutbound(int $limit = 20): int
    {
        $n = (new ClinicalHistoryOutboundProcessorService())->processDueQueue($limit);
        $this->stdout("Jobs procesados: {$n}\n");

        return 0;
    }

    public function actionProcessOne(int $jobId): int
    {
        $row = ClinicalHistoryOutboundJob::findOne($jobId);
        if ($row === null) {
            $this->stderr("Job {$jobId} no encontrado.\n");

            return 1;
        }

        $ok = (new ClinicalHistoryOutboundProcessorService())->processOne($row);
        $this->stdout($ok ? "Job {$jobId} procesado.\n" : "Job {$jobId} falló.\n");

        return $ok ? 0 : 1;
    }

    /**
     * Reencola un job MUERTO o FALLIDO para reintento manual.
     */
    public function actionRequeue(int $jobId): int
    {
        $row = ClinicalHistoryOutboundJob::findOne($jobId);
        if ($row === null) {
            $this->stderr("Job {$jobId} no encontrado.\n");

            return 1;
        }

        if (!in_array($row->estado, [
            ClinicalHistoryOutboundJob::ESTADO_MUERTO,
            ClinicalHistoryOutboundJob::ESTADO_FALLIDO,
            ClinicalHistoryOutboundJob::ESTADO_OMITIDO,
        ], true)) {
            $this->stderr("Job {$jobId} en estado {$row->estado}; no se reencola.\n");

            return 1;
        }

        $row->estado = ClinicalHistoryOutboundJob::ESTADO_PENDIENTE;
        $row->run_at = date('Y-m-d H:i:s');
        $row->ultimo_error = null;
        $row->updated_at = date('Y-m-d H:i:s');
        $row->save(false);

        $this->stdout("Job {$jobId} reencolado.\n");

        return 0;
    }

    /**
     * Concilia jobs ENVIADO sin acuse definitivo (requiere statusPath en conector nacional).
     */
    public function actionReconcile(int $limit = 50): int
    {
        $n = (new ClinicalHistoryOutboundReconcileService())->reconcileDue($limit);
        $this->stdout("Jobs reconciliados: {$n}\n");

        return 0;
    }
}
