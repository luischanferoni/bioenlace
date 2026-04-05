<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use common\models\UserDevice;

/**
 * Registro de dispositivos / push token para la app.
 */
class DeviceController extends BaseController
{
    /**
     * POST body: device_id, push_token, push_provider (fcm|expo), platform
     */
    public function actionPushToken()
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        $req = Yii::$app->request;
        $deviceId = $req->post('device_id');
        $pushToken = $req->post('push_token');
        $provider = $req->post('push_provider', 'fcm');
        $platform = $req->post('platform');
        if ($deviceId === null || $deviceId === '' || $pushToken === null || $pushToken === '') {
            throw new BadRequestHttpException('device_id y push_token son obligatorios');
        }
        UserDevice::upsertPushToken((int) $idPersona, (string) $deviceId, (string) $pushToken, (string) $provider, $platform ? (string) $platform : null);
        return ['success' => true, 'message' => 'Token registrado'];
    }
}
