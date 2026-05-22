<?php

namespace console\controllers;

use common\components\Clinical\Laboratory\Service\LaboratoryIngestService;
use common\models\Person\Persona;
use yii\console\Controller;

/**
 * Sincronización pull de resultados de laboratorio.
 *
 * php yii laboratory-sync/persona 920779
 * php yii laboratory-sync/persona 920779 sianlabs
 */
class LaboratorySyncController extends Controller
{
    /**
     * @param int $idPersona
     * @param string|null $connector
     */
    public function actionPersona($idPersona, $connector = null): int
    {
        $persona = Persona::findOne((int) $idPersona);
        if ($persona === null) {
            $this->stderr("Persona {$idPersona} no encontrada.\n");

            return 1;
        }

        $result = (new LaboratoryIngestService())->syncForPersona((int) $idPersona, $connector);
        $this->stdout(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        return empty($result['errors']) ? 0 : 1;
    }
}
