<?php

namespace console\controllers;

use common\components\Platform\Core\Auth\DemoSandboxSessionService;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Mantenimiento del sandbox demo institucional (purga TTL).
 *
 * Uso:
 *   php yii demo-sandbox/purge-expired
 *   php yii demo-sandbox/hard-delete-purged
 */
class DemoSandboxController extends Controller
{
    /**
     * Purga sesiones demo vencidas (médico temporal + seed).
     */
    public function actionPurgeExpired(): int
    {
        $result = (new DemoSandboxSessionService())->purgeExpired();
        $this->stdout(
            sprintf(
                "Demo sandbox: escaneadas=%d purgadas=%d\n",
                $result['scanned'],
                $result['purged']
            ),
            Console::FG_GREEN
        );
        foreach ($result['errors'] as $err) {
            $this->stderr($err . "\n", Console::FG_YELLOW);
        }

        return ExitCode::OK;
    }

    /**
     * Elimina de BD residuos soft-deleted de demos (personas DemoPurged + filas con deleted_at).
     */
    public function actionHardDeletePurged(): int
    {
        $result = (new \common\components\Domain\Organization\Service\Seed\DemoSandboxPurgeService())
            ->hardDeletePurgedResidues();
        $this->stdout(
            sprintf(
                "Hard-delete DemoPurged: guardias=%d encounters=%d turnos=%d internaciones=%d pes=%d personas=%d users=%d\n",
                $result['guardias'],
                $result['encounters'],
                $result['turnos'] ?? 0,
                $result['internaciones'] ?? 0,
                $result['pes'],
                $result['personas'],
                $result['users']
            ),
            Console::FG_GREEN
        );
        foreach ($result['errors'] as $err) {
            $this->stderr($err . "\n", Console::FG_YELLOW);
        }

        return ExitCode::OK;
    }
}
