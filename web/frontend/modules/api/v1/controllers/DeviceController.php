<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\components\Platform\Core\Http\Contract\ApiException;
use common\models\Platform\UserDevice;

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
            throw new ApiException('request_invalid', [
                'detail' => 'Sesión sin persona asociada.',
            ]);
        }
        $req = Yii::$app->request;
        $pushToken = $req->post('push_token');
        $provider = $req->post('push_provider', 'fcm');
        $platform = $req->post('platform');
        $deviceId = $req->post('device_id');
        if ($pushToken === null || $pushToken === '') {
            throw new ApiException('request_invalid', ['detail' => 'push_token es obligatorio']);
        }
        $ok = UserDevice::upsertPushToken(
            (int) $idPersona,
            (string) $pushToken,
            (string) $provider,
            $platform ? (string) $platform : null,
            $deviceId !== null && $deviceId !== '' ? (string) $deviceId : null
        );
        if (!$ok) {
            throw new ApiException('request_invalid', [
                'detail' => 'No se pudo registrar el token push.',
            ]);
        }

        return $this->ok(['registered' => true]);
    }
}
