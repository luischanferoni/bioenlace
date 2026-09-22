<?php

namespace console\controllers;

use common\components\Domain\Clinical\LegalExport\Application\Service\LegalExportProcessorService;
use common\models\Clinical\LegalExportRequest;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Cola de expediente legal staff (cron cada minuto).
 *
 * php yii legal-record-export/run
 * php yii legal-record-export/process 42  (una solicitud por id)
 */
class LegalExportController extends Controller
{
    public function actionRun(int $limit = 10): int
    {
        $n = (new LegalExportProcessorService())->processDueQueue($limit);
        $this->stdout("Expedientes generados: {$n}\n");

        return ExitCode::OK;
    }

    public function actionProcess(int $requestId = 0): int
    {
        if ($requestId <= 0) {
            $this->stderr("Uso: php yii legal-record-export/process <request_id>\n");

            return ExitCode::USAGE;
        }

        $row = LegalExportRequest::findOne($requestId);
        if ($row === null) {
            $this->stderr("Solicitud {$requestId} no encontrada.\n");

            return ExitCode::DATAERR;
        }

        if ($row->estado !== LegalExportRequest::ESTADO_PENDIENTE) {
            $this->stderr("Estado actual: {$row->estado}\n");

            return ExitCode::DATAERR;
        }

        try {
            (new LegalExportProcessorService())->processOne($row);
            $this->stdout("OK solicitud {$requestId}\n");

            return ExitCode::OK;
        } catch (\Throwable $e) {
            (new LegalExportProcessorService())->markFailed($row, $e->getMessage());
            $this->stderr($e->getMessage() . "\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
