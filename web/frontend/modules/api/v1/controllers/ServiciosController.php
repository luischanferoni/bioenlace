<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\components\UiScreenService;

/**
 * API Servicios: views JSON embebibles (selección/autocomplete) para flujos conversacionales.
 */
class ServiciosController extends BaseController
{
    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update']);
        return $actions;
    }

    /**
     * View embebible: elegir servicio.
     *
     * GET|POST /api/v1/views/servicios/elegir
     *
     * @action_name Elegir servicio
     * @entity Servicios
     * @tags views, ui, servicio
     * @keywords elegir servicio, especialidad, seleccionar especialidad
     */
    public function actionElegir(): array
    {
        $req = Yii::$app->request;
        return UiScreenService::handleScreen(
            'servicios',
            'elegir',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );
    }
}

