<?php

namespace frontend\modules\api\v1\controllers;

/**
 * API Rrhh: solo mapea a frontend\controllers\RrhhController.
 * Toda la lógica está en el controlador frontend (actionRrhhAutocomplete).
 */
class RrhhController extends BaseController
{
    public $modelClass = 'common\models\RrhhEfector';

    public static $frontendControllerClass = \frontend\controllers\RrhhController::class;

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update']);
        return $actions;
    }

    /**
     * GET /api/v1/rrhh/rrhh-autocomplete -> frontend rrhh/rrhh-autocomplete
     */
    public function actionRrhhAutocomplete()
    {
        return $this->runFrontendAction('rrhh-autocomplete');
    }
}
