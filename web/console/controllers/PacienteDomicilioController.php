<?php

namespace console\controllers;

use yii\console\Controller;
use common\components\Domain\Person\Service\PacienteDomicilioVerificacionService;

/**
 * Reintentos de verificación de domicilio vía MPI (cron cada ~30 min).
 */
class PacienteDomicilioController extends Controller
{
    public function actionRun(int $limit = 50): int
    {
        $n = (new PacienteDomicilioVerificacionService())->procesarPendientes($limit);
        $this->stdout("Verificaciones domicilio exitosas: {$n}\n");

        return 0;
    }
}
