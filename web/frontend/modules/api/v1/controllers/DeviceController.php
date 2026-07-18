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
     * POST body: push_token, push_provider (fcm|expo), platform
     * `device_id` es opcional (legado/biometría); la identidad push es `push_token`.
     */
    public function actionPushToken()
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            throw new BadRequestHttpException('Sesión sin persona asociada.');
        }
        $req = Yii::$app->request;
        $pushToken = $req->post('push_token');
        $provider = $req->post('push_provider', 'fcm');
        $platform = $req->post('platform');
        $deviceId = $req->post('device_id');
        if ($pushToken === null || $pushToken === '') {
            throw new BadRequestHttpException('push_token es obligatorio');
        }
        $ok = UserDevice::upsertPushToken(
            (int) $idPersona,
            (string) $pushToken,
            (string) $provider,
            $platform ? (string) $platform : null,
            $deviceId !== null && $deviceId !== '' ? (string) $deviceId : null
        );
        if (!$ok) {
            throw new BadRequestHttpException('No se pudo registrar el token push.');
        }
        return ['success' => true, 'message' => 'Token registrado'];
    }
}
