<?php

namespace frontend\modules\api\v1\controllers;

/**
 * API Efectores: solo mapea a frontend\controllers\EfectoresController.
 * Toda la lógica está en el controlador frontend (actionSearch).
 */
class EfectoresController extends BaseController
{
    public $modelClass = 'common\models\Efector';

    public static $frontendControllerClass = \frontend\controllers\EfectoresController::class;

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update']);
        return $actions;
    }

    /**
     * GET /api/v1/efectores/search -> frontend efectores/search
     */
    public function actionSearch()
    {
        return $this->runFrontendAction('search');
    }
}
