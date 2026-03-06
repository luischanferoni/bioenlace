<?php

namespace frontend\modules\api\v1\controllers;

use Yii;

/**
 * API Turnos: solo mapea a frontend\controllers\TurnosController.
 * Toda la lógica está en el controlador frontend.
 */
class TurnosController extends BaseController
{
    public $modelClass = 'common\models\Turno';

    /** Este controlador API solo mapea; except y verbs se leen del controlador frontend. */
    public static $frontendControllerClass = \frontend\controllers\TurnosController::class;

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update']);
        return $actions;
    }

    public function actionEventos()
    {
        return $this->runFrontendAction('eventos');
    }

    public function actionProximoDisponible()
    {
        $err = $this->requerirAutenticacion();
        if ($err !== null) {
            return $err;
        }
        return $this->runFrontendAction('proximo-disponible-api');
    }

    public function actionMisTurnos()
    {
        return $this->runFrontendAction('mis-turnos', []);
    }

    public function actionIndex()
    {
        return $this->runFrontendAction('index-api');
    }

    public function actionView($id)
    {
        return $this->runFrontendAction('view-api', ['id' => $id]);
    }

    public function actionCreate()
    {
        return $this->runFrontendAction('create-api');
    }

    public function actionUpdate($id)
    {
        return $this->runFrontendAction('update-api', ['id' => $id]);
    }
}
