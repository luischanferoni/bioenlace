<?php

namespace frontend\modules\api\v1\controllers;

use Yii;

/**
 * API Consulta (retirada). Usar {@see clinical\EncounterController}.
 *
 * @deprecated POST /api/v1/clinical/encounter/analizar|guardar
 */
class ConsultaController extends BaseController
{
    public static $authenticatorExcept = [];

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);

        return $actions;
    }

    public function actionAnalizar()
    {
        return $this->goneResponse();
    }

    public function actionGuardar()
    {
        return $this->goneResponse();
    }

    /** @return array<string, mixed> */
    private function goneResponse(): array
    {
        Yii::$app->response->statusCode = 410;

        return [
            'success' => false,
            'message' => 'Endpoint retirado. Use POST /api/v1/clinical/encounter/analizar o /api/v1/clinical/encounter/guardar.',
            'data' => null,
        ];
    }
}
