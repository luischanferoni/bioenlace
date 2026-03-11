<?php

namespace frontend\modules\api\v1\controllers;

/**
 * API Listado: sólo mapea a `frontend\controllers\ListadoController`.
 * Toda la lógica está en el controlador frontend.
 */
class ListadoController extends BaseController
{
    public $modelClass = 'common\models\User';

    public static $frontendControllerClass = \frontend\controllers\ListadoController::class;

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);
        return $actions;
    }

    /**
     * GET /api/v1/listado/internacion -> frontend listado/internacion
     */
    public function actionInternacion()
    {
        return $this->runFrontendAction('internacion');
    }

    /**
     * GET /api/v1/listado/guardia -> frontend listado/guardia
     */
    public function actionGuardia()
    {
        return $this->runFrontendAction('guardia');
    }
}

