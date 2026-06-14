<?php

namespace common\components\Platform\Core\Service\Push;

use Yii;
use common\models\UserDevice;
use common\components\Platform\Core\Service\Notificaciones\PersonaNotificacionService;

/**
 * Push FCM a una persona + bandeja in-app opcional.
 * Dominio agnóstico: el campo `type` en $data identifica el caso (turnos, mensajes, etc.).
 */
class PushNotificationSender
{
    /**
     * @param int $idPersona
     * @param array<string, mixed> $data payload (type, ids de dominio, etc.)
     * @param string $title
     * @param string $body
     * @param bool $persistInbox registrar en persona_notificacion
     */
    public function sendToPersona($idPersona, array $data, $title, $body, $persistInbox = true)
    {
        $idPersona = (int) $idPersona;
        if ($idPersona <= 0) {
            return;
        }

        $tipo = isset($data['type']) ? (string) $data['type'] : 'GENERICO';

        if ($persistInbox) {
            try {
                PersonaNotificacionService::registrar($idPersona, $tipo, (string) $title, (string) $body, $data);
            } catch (\Throwable $e) {
                Yii::warning('Inbox notif: ' . $e->getMessage(), FcmPushConfig::LOG_CATEGORY);
            }
        }

        $devices = UserDevice::find()
            ->where(['id_persona' => $idPersona, 'is_active' => true])
            ->andWhere(['not', ['push_token' => null]])
            ->andWhere(['<>', 'push_token', ''])
            ->all();

        foreach ($devices as $d) {
            $this->sendToToken($d->push_token, $d->push_provider, $title, $body, $data);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function sendToToken($token, $provider, $title, $body, array $data)
    {
        $dataStr = [];
        foreach ($data as $k => $v) {
            $dataStr[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
        }

        Yii::info([
            'fcm_push' => true,
            'provider' => $provider,
            'type' => $dataStr['type'] ?? '',
            'title' => $title,
            'body' => $body,
            'data' => $dataStr,
            'token_prefix' => substr((string) $token, 0, 12),
        ], FcmPushConfig::LOG_CATEGORY);

        $sent = false;
        if ((string) $provider === 'fcm' || $provider === null || $provider === '') {
            $sent = FcmHttpSender::send((string) $token, (string) $title, (string) $body, $dataStr);
        }

        $endpoint = FcmPushConfig::get()['httpEndpoint'] ?? null;
        if (!$sent && !empty($endpoint)) {
            try {
                $client = new \yii\httpclient\Client();
                $client->createRequest()
                    ->setMethod('POST')
                    ->setUrl((string) $endpoint)
                    ->setFormat(\yii\httpclient\Client::FORMAT_JSON)
                    ->setData([
                        'token' => $token,
                        'provider' => $provider,
                        'title' => $title,
                        'body' => $body,
                        'data' => $dataStr,
                    ])
                    ->send();
            } catch (\Throwable $e) {
                Yii::error('Push HTTP: ' . $e->getMessage(), FcmPushConfig::LOG_CATEGORY);
            }
        }
    }
}
