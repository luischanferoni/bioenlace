<?php

namespace console\controllers;

use common\components\Platform\Core\Auth\DemoSandboxSessionService;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Mantenimiento del sandbox demo institucional (purga TTL).
 *
 * Uso: php yii demo-sandbox/purge-expired
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
}
