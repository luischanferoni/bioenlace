<?php

namespace frontend\modules\api\v1\controllers;

use common\components\Clinical\Service\SecureMediaService;
use Yii;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * GET /api/v1/media/{scope}/{encounter_id}/{filename} — archivo binario (Bearer + participantes del encounter).
 *
 * scope: motivos-consulta | consulta-chat
 */
class MediaController extends BaseController
{
    /**
     * No forzar JSON en la descarga binaria.
     */
    public function beforeAction($action)
    {
        if ($action->id === 'ver') {
            return Controller::beforeAction($action);
        }

        return parent::beforeAction($action);
    }

    /**
     * @param string $scope
     * @param int $encounterId
     * @param string $filename
     */
    public function actionVer($scope, $encounterId, $filename)
    {
        try {
            $resolved = SecureMediaService::resolveForDownload(
                (string) $scope,
                (int) $encounterId,
                (string) $filename
            );
        } catch (NotFoundHttpException $e) {
            Yii::$app->response->statusCode = 404;
            Yii::$app->response->format = Response::FORMAT_JSON;

            return ['success' => false, 'message' => $e->getMessage(), 'data' => null];
        } catch (ForbiddenHttpException $e) {
            Yii::$app->response->statusCode = 403;
            Yii::$app->response->format = Response::FORMAT_JSON;

            return ['success' => false, 'message' => $e->getMessage(), 'data' => null];
        }

        Yii::$app->response->format = Response::FORMAT_RAW;

        return Yii::$app->response->sendFile(
            $resolved['path'],
            $resolved['filename'],
            [
                'mimeType' => $resolved['mime'],
                'inline' => true,
            ]
        );
    }
}
