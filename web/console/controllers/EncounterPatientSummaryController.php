<?php

namespace console\controllers;

use common\components\Domain\Clinical\PatientSummary\PatientEncounterSummaryPublishService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Cola de publicación de resúmenes de atención para pacientes (cron cada minuto).
 *
 * php yii encounter-patient-summary/run
 * php yii encounter-patient-summary/publish 123  (inmediato, sin esperar cola)
 */
class EncounterPatientSummaryController extends Controller
{
    /**
     * Procesa filas PENDIENTE con run_at <= now.
     */
    public function actionRun(int $limit = 50): int
    {
        $n = (new PatientEncounterSummaryPublishService())->processDueQueue($limit);
        $this->stdout("Publicados: {$n}\n");

        return ExitCode::OK;
    }

    /**
     * Publica un encounter de inmediato (pruebas / recuperación).
     */
    public function actionPublish(int $encounterId = 0): int
    {
        if ($encounterId <= 0) {
            $this->stderr("Uso: php yii encounter-patient-summary/publish <encounter_id>\n");

            return ExitCode::USAGE;
        }
        $ok = (new PatientEncounterSummaryPublishService())->publishEncounter($encounterId, true);
        if (!$ok) {
            $this->stderr("No se pudo publicar encounter {$encounterId}\n");

            return ExitCode::DATAERR;
        }
        $this->stdout("OK encounter {$encounterId}\n");

        return ExitCode::OK;
    }
}
