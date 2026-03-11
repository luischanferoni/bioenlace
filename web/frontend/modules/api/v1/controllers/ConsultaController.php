<?php

namespace frontend\modules\api\v1\controllers;

/**
 * API Consulta: sólo mapea a `frontend\controllers\ConsultaController`.
 * Toda la lógica está en el controlador frontend.
 */
class ConsultaController extends BaseController
{
    public $modelClass = 'common\models\Consulta';

    public static $frontendControllerClass = \frontend\controllers\ConsultaController::class;

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);
        return $actions;
    }

    /**
     * POST /api/v1/consulta/analizar -> frontend consulta/analizar
     */
    public function actionAnalizar()
    {
        return $this->runFrontendAction('analizar');
    }

    /**
     * POST /api/v1/consulta/guardar -> frontend consulta/guardar
     */
    public function actionGuardar()
    {
        return $this->runFrontendAction('guardar');
    }
}

