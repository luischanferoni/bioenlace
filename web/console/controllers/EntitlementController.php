<?php

namespace console\controllers;

use common\components\Domain\Organization\Service\Entitlement\EfectorEncounterEntitlementService;
use yii\console\Controller;

/**
 * Contrato comercial: aplica downgrades de max_pes diferidos al nuevo período.
 *
 * Cron sugerido: diario 00:15 — `php yii entitlement/apply-pending-downgrades`
 */
class EntitlementController extends Controller
{
    /**
     * @param string|null $date Y-m-d (default: hoy)
     */
    public function actionApplyPendingDowngrades($date = null): int
    {
        $onDate = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
        $n = EfectorEncounterEntitlementService::applyPendingDowngrades($onDate);
        $this->stdout('Downgrades de entitlement aplicados: ' . $n . "\n");

        return 0;
    }
}
