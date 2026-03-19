<?php

namespace common\components\Services\Turnos;

use Yii;
use common\models\UserDevice;

/**
 * Envío de push (FCM/Expo). Implementación mínima: log + extensible.
 */
class PushNotificationSender
{
    /**
     * @param int $idPersona
     * @param array $data payload data (type, id_turno, etc.)
     * @param string $title
     * @param string $body
     */
    public function sendToPersona($idPersona, array $data, $title, $body)
    {
        $devices = UserDevice::find()
            ->where(['id_persona' => (int) $idPersona, 'is_active' => true])
            ->andWhere(['not', ['push_token' => null]])
            ->andWhere(['<>', 'push_token', ''])
            ->all();

        foreach ($devices as $d) {
            $this->sendToToken($d->push_token, $d->push_provider, $title, $body, $data);
        }
    }

    protected function sendToToken($token, $provider, $title, $body, array $data)
    {
        Yii::info([
            'turnos_push' => true,
            'provider' => $provider,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'token_prefix' => substr((string) $token, 0, 12),
        ], 'turnos-push');

        if (!empty(Yii::$app->params['turnosPush']['httpEndpoint'])) {
            try {
                $client = new \yii\httpclient\Client();
                $client->createRequest()
                    ->setMethod('POST')
                    ->setUrl(Yii::$app->params['turnosPush']['httpEndpoint'])
                    ->setFormat(\yii\httpclient\Client::FORMAT_JSON)
                    ->setData([
                        'token' => $token,
                        'provider' => $provider,
                        'title' => $title,
                        'body' => $body,
                        'data' => $data,
                    ])
                    ->send();
            } catch (\Throwable $e) {
                Yii::error('Push HTTP: ' . $e->getMessage(), 'turnos-push');
            }
        }
    }
}
