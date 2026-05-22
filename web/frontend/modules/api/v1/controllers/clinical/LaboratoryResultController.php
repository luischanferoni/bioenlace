<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Clinical\Laboratory\Service\LaboratoryIngestService;
use common\components\Clinical\Laboratory\Service\LaboratoryResultQueryService;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;

/**
 * Resultados de laboratorio (ingesta pull + lectura).
 *
 * GET  /api/v1/clinical/laboratory-results/mis-resultados
 * POST /api/v1/clinical/laboratory-results/sincronizar
 * GET  /api/v1/clinical/encounter/<encounterId>/laboratory-results
 */
class LaboratoryResultController extends BaseController
{
    use ClinicalAccessTrait;

    private LaboratoryResultQueryService $query;
    private LaboratoryIngestService $ingest;

    public function init()
    {
        parent::init();
        $this->query = new LaboratoryResultQueryService();
        $this->ingest = new LaboratoryIngestService();
    }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);

        return $actions;
    }

    /**
     * Listado de informes del paciente autenticado.
     */
    public function actionMisResultados(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError('Solo pacientes autenticados.', null, 400);
        }

        return [
            'success' => true,
            'message' => 'Resultados de laboratorio',
            'data' => [
                'reports' => $this->query->listForPersona($idPersona),
            ],
        ];
    }

    /**
     * Pull desde LIS configurado (paciente autenticado = su persona).
     */
    public function actionSincronizar(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError('Solo pacientes autenticados.', null, 400);
        }

        $connectorKey = Yii::$app->request->post('connector')
            ?? Yii::$app->request->get('connector');

        try {
            $result = $this->ingest->syncForPersona($idPersona, is_string($connectorKey) ? $connectorKey : null);
        } catch (\Throwable $e) {
            Yii::error($e, 'laboratory-sync');

            return $this->clinicalError($e->getMessage(), null, 502);
        }

        return [
            'success' => true,
            'message' => 'Sincronización de laboratorio',
            'data' => $result,
        ];
    }

    /**
     * Informes vinculados a un encounter (staff o paciente con acceso).
     */
    public function actionPorEncounter($encounterId): array
    {
        [$encounter, $err] = $this->requireEncounterAccess((int) $encounterId);
        if ($err !== null) {
            return $err;
        }

        return [
            'success' => true,
            'message' => 'Laboratorio del encounter',
            'data' => [
                'encounterId' => (int) $encounter->id,
                'reports' => $this->query->listForEncounter((int) $encounter->id),
            ],
        ];
    }
}
