<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\services\EntityIntake\EntityIntakeService;

/**
 * API para estructurar texto/audio dentro de un contexto de entidad (intake).
 */
class EntityIntakeController extends BaseController
{
    public $enableCsrfValidation = false;

    /**
     * Analiza texto y devuelve prefill para un formulario.
     *
     * Body:
     * - entity: string (ej: internacion_ingreso, internacion_medicacion, ...)
     * - intent: string|null (opcional, ayuda a elegir schema)
     * - text: string
     */
    public function actionAnalyze()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $body = Yii::$app->request->getBodyParams();
        $entity = $body['entity'] ?? null;
        $intent = $body['intent'] ?? null;
        $text = $body['text'] ?? null;

        if (!$entity || !$text) {
            Yii::$app->response->statusCode = 400;
            return [
                'success' => false,
                'message' => 'Faltan datos obligatorios: entity y text.',
                'data' => null,
            ];
        }

        $result = EntityIntakeService::analyze((string)$text, (string)$entity, $intent ? (string)$intent : null);

        if (!$result['success']) {
            Yii::$app->response->statusCode = 400;
            return [
                'success' => false,
                'message' => 'No se pudo analizar el texto para la entidad solicitada.',
                'data' => $result,
            ];
        }

        return [
            'success' => true,
            'message' => 'Análisis completado.',
            'data' => $result,
        ];
    }
}

