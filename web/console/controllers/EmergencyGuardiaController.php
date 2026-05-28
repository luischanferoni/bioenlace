<?php

namespace console\controllers;

use common\components\Clinical\Emergency\Service\GuardiaMetricsMaterializeService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Jobs de guardia / urgencias.
 */
class EmergencyGuardiaController extends Controller
{
    /**
     * Materializa KPIs diarios por efector (tabla guardia_metrics_daily).
     *
     * @param string|null $fecha Y-m-d (default: hoy)
     */
    public function actionMaterializeMetrics($fecha = null): int
    {
        $fecha = $fecha ?: date('Y-m-d');
        $n = (new GuardiaMetricsMaterializeService())->materializeAllEfectores($fecha);
        $this->stdout("Materializado métricas de guardia para {$n} efector(es), fecha {$fecha}.\n");

        return ExitCode::OK;
    }
}
