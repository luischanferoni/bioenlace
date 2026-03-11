<?php

namespace frontend\modules\api\v1\controllers;

/**
 * API Persona: sólo mapea a `frontend\controllers\PersonaController`.
 * Toda la lógica está en el controlador frontend.
 */
class PersonaController extends BaseController
{
    public $modelClass = 'common\models\Persona';

    /**
     * Controlador de frontend al que se mapean las acciones.
     */
    public static $frontendControllerClass = \frontend\controllers\PersonaController::class;

    /**
     * Deshabilitar acciones REST por defecto que no se usan porque mapeamos a acciones personalizadas.
     */
    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);
        return $actions;
    }

    /**
     * GET /api/v1/persona/index -> frontend persona/index
     */
    public function actionIndex()
    {
        return $this->runFrontendAction('index');
    }

    /**
     * GET /api/v1/persona/view?id=... -> frontend persona/view
     */
    public function actionView($id)
    {
        return $this->runFrontendAction('view', ['id' => $id]);
    }

    /**
     * GET /api/v1/persona/timeline?id=... -> frontend persona/timeline
     */
    public function actionTimeline($id)
    {
        return $this->runFrontendAction('timeline', ['id' => $id]);
    }

    /**
     * POST /api/v1/persona/create -> frontend persona/create
     */
    public function actionCreate()
    {
        return $this->runFrontendAction('create');
    }

    /**
     * PUT/PATCH /api/v1/persona/update?id=... -> frontend persona/update
     */
    public function actionUpdate($id)
    {
        return $this->runFrontendAction('update', ['id' => $id]);
    }

    /**
     * DELETE /api/v1/persona/delete?id=... -> frontend persona/delete
     */
    public function actionDelete($id)
    {
        return $this->runFrontendAction('delete', ['id' => $id]);
    }
}

