<?php

namespace console\controllers;

use common\components\Clinical\Laboratory\Service\LaboratoryIngestService;
use common\components\Clinical\Laboratory\Service\LaboratorySyncBatchService;
use common\models\Person\Persona;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Sincronización pull de resultados de laboratorio (LIS → Bioenlace).
 *
 * Una persona:
 *   php yii laboratory-sync/persona 920779
 *   php yii laboratory-sync/persona 920779 sianlabs
 *
 * Lote (cron): personas con documento y usuario de app, paginado
 *   php yii laboratory-sync/lote
 *   php yii laboratory-sync/lote 100 0
 *   php yii laboratory-sync/lote 50 200 sianlabs 0
 *   (limit, offset, connector, soloConUsuario: 1|0)
 */
class LaboratorySyncController extends Controller
{
    /**
     * @param int|string $idPersona
     * @param string|null $connector
     */
    public function actionPersona($idPersona, $connector = null): int
    {
        $persona = Persona::findOne((int) $idPersona);
        if ($persona === null) {
            $this->stderr("Persona {$idPersona} no encontrada.\n");

            return ExitCode::DATAERR;
        }

        $result = (new LaboratoryIngestService())->syncForPersona((int) $idPersona, $connector);
        $this->stdout(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        return empty($result['errors']) ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Sincroniza un lote de personas (para cron / job nocturno).
     *
     * @param int|string $limit Máx. personas por ejecución (1–500, default 50)
     * @param int|string $offset Desplazamiento para paginar
     * @param string|null $connector Clave en laboratoryConnectors; vacío = default
     * @param int|string $soloConUsuario 1 = solo personas con id_user (default)
     */
    public function actionLote($limit = 50, $offset = 0, $connector = null, $soloConUsuario = 1): int
    {
        $connectorKey = is_string($connector) && trim($connector) !== '' ? trim($connector) : null;
        $onlyWithUser = (int) $soloConUsuario !== 0;

        $summary = (new LaboratorySyncBatchService())->syncBatch(
            (int) $limit,
            (int) $offset,
            $connectorKey,
            $onlyWithUser
        );

        $this->stdout(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        if ($summary['processed'] === 0) {
            $this->stdout("Sin personas en este rango (offset={$offset}, limit={$limit}).\n");
        }

        return $summary['errors'] === [] ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }
}
