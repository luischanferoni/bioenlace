<?php

namespace frontend\modules\api\v1\controllers;

use common\components\Domain\Organization\Service\GeografiaDepdropService;
use common\components\Domain\Organization\Service\InfraestructuraDepdropService;
use common\components\Domain\Organization\Service\ProfesionalDepdropService;
use common\models\Clinical\EncounterDefinition;
use Yii;

/**
 * API Catálogos: listas estáticas y DepDrop geográfico para clientes (web/móvil).
 */
class CatalogosController extends BaseController
{
    public static $authenticatorExcept = [
        'encounter-classes',
        'departamentos-depdrop',
        'localidades-depdrop',
        'barrios-depdrop',
        'especialidades-depdrop',
        'salas-por-piso-depdrop',
    ];

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update']);
        return $actions;
    }

    /**
     * GET /api/v1/catalogos/encounter-classes
     * No requiere autenticación (lista estática).
     */
    public function actionEncounterClasses()
    {
        $encounterClasses = EncounterDefinition::ENCOUNTER_CLASS;
        $formatted = [];
        foreach ($encounterClasses as $code => $label) {
            $formatted[] = [
                'code' => (string) $code,
                'label' => (string) $label,
            ];
        }
        return $this->success(['encounter_classes' => $formatted]);
    }

    /**
     * POST /api/v1/catalogos/departamentos-depdrop
     * Contrato Kartik DepDrop: {@code { output, selected }} (sin envoltorio success).
     */
    public function actionDepartamentosDepdrop(): array
    {
        return GeografiaDepdropService::departamentosResponse(Yii::$app->request->post());
    }

    /**
     * POST /api/v1/catalogos/localidades-depdrop
     */
    public function actionLocalidadesDepdrop(): array
    {
        return GeografiaDepdropService::localidadesResponse(Yii::$app->request->post());
    }

    /**
     * POST /api/v1/catalogos/barrios-depdrop
     */
    public function actionBarriosDepdrop(): array
    {
        return GeografiaDepdropService::barriosResponse(Yii::$app->request->post());
    }

    /**
     * POST /api/v1/catalogos/especialidades-depdrop
     */
    public function actionEspecialidadesDepdrop(): array
    {
        return ProfesionalDepdropService::especialidadesResponse(Yii::$app->request->post());
    }

    /**
     * POST /api/v1/catalogos/salas-por-piso-depdrop
     */
    public function actionSalasPorPisoDepdrop(): array
    {
        return InfraestructuraDepdropService::salasPorPisoResponse(Yii::$app->request->post());
    }
}

