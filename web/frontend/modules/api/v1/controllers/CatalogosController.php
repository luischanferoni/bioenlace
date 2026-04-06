<?php

namespace frontend\modules\api\v1\controllers;

use common\models\ConsultasConfiguracion;

/**
 * API Catálogos: listas estáticas para clientes (web/móvil).
 */
class CatalogosController extends BaseController
{
    public static $authenticatorExcept = ['encounter-classes'];

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
        $encounterClasses = ConsultasConfiguracion::ENCOUNTER_CLASS;
        $formatted = [];
        foreach ($encounterClasses as $code => $label) {
            $formatted[] = [
                'code' => (string) $code,
                'label' => (string) $label,
            ];
        }
        return $this->success(['encounter_classes' => $formatted]);
    }
}

