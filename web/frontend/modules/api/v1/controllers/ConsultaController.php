<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\Response;
use common\components\Services\Consulta\ConsultaProcesamientoService;

/**
 * API Consulta: delega análisis y guardado en {@see ConsultaProcesamientoService}.
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
        Yii::$app->response->format = Response::FORMAT_JSON;
        $out = (new ConsultaProcesamientoService())->analizar(Yii::$app->request->getBodyParams());

        return $this->applyConsultaHttpStatus($out);
    }

    public function actionGuardar()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $body = $this->mergeGuardarRequestBody();
        if (YII_DEBUG) {
            Yii::info('Datos recibidos en actionGuardar (api): ' . json_encode([
                'bodyParams' => Yii::$app->request->getBodyParams(),
                'post' => Yii::$app->request->post(),
                'rawBody' => substr(Yii::$app->request->getRawBody(), 0, 500),
                'mergedBody' => $body,
            ]), 'consulta-guardar');
        }
        $out = (new ConsultaProcesamientoService())->guardar($body);

        return $this->applyConsultaHttpStatus($out);
    }

    /**
     * @param array $out puede incluir __statusCode para el cliente HTTP
     * @return array payload sin __statusCode
     */
    private function applyConsultaHttpStatus(array $out): array
    {
        if (!empty($out['__statusCode'])) {
            Yii::$app->response->statusCode = (int) $out['__statusCode'];
            unset($out['__statusCode']);
        }

        return $out;
    }

    private function mergeGuardarRequestBody(): array
    {
        $body = Yii::$app->request->getBodyParams();
        $post = Yii::$app->request->post();

        if (empty($body)) {
            $body = $post;
        }

        if (empty($body)) {
            $rawBody = Yii::$app->request->getRawBody();
            if (!empty($rawBody)) {
                $decoded = json_decode($rawBody, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $body = $decoded;
                }
            }
        }

        return is_array($body) ? $body : [];
    }
}
