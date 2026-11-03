<?php

namespace console\controllers;

use common\components\Domain\Organization\Efector\Application\Service\CommercialSignupDraftService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Mantenimiento de los borradores del alta de espacios de atención.
 *
 * Cron sugerido: diario — `php yii commercial-signup/purge-drafts`.
 */
class CommercialSignupController extends Controller
{
    public function actionPurgeDrafts(): int
    {
        $purged = (new CommercialSignupDraftService())->purgeExpired();
        $this->stdout(
            'Borradores vencidos eliminados (' . CommercialSignupDraftService::RETENTION_DAYS . ' días de retención): '
            . $purged . "\n"
        );

        return ExitCode::OK;
    }
}
