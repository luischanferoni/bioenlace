<?php

namespace common\components\Platform\Core\Service\Push;

use common\components\Shared\Infrastructure\Http\HttpApiException;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use Yii;
use common\models\Platform\UserDevice;
use common\models\Platform\PersonaNotificacion;
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
     * @param array<string, mixed> $options idempotency_key, context_handler_id, context
     * @return PersonaNotificacion|null sobre de bandeja si se persistió
     */
    public function sendToPersona(
        $idPersona,
        array $data,
        $title,
        $body,
        $persistInbox = true,
        array $options = []
    ) {
        $idPersona = (int) $idPersona;
        if ($idPersona <= 0) {
            return null;
        }

        $tipo = isset($data['type']) ? (string) $data['type'] : 'GENERICO';
        $notification = null;

        if ($persistInbox) {
            try {
                $notification = PersonaNotificacionService::registrar(
                    $idPersona,
                    $tipo,
                    (string) $title,
                    (string) $body,
                    $data,
                    $options
                );
                if ($notification->public_ref) {
                    $data['notification_ref'] = (string) $notification->public_ref;
                }
                if (!empty($notification->context_handler_id)) {
                    $data['context_handler_id'] = (string) $notification->context_handler_id;
                }
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

        return $notification;
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
                (new JsonApiClient(
                    baseUrl: '',
                    connectTimeout: 5,
                    timeout: 20,
                    logCategory: FcmPushConfig::LOG_CATEGORY,
                    provider: 'push-http',
                    providerLabel: 'Endpoint de push'
                ))->post((string) $endpoint, [
                    'token' => $token,
                    'provider' => $provider,
                    'title' => $title,
                    'body' => $body,
                    'data' => $dataStr,
                ]);
            } catch (HttpApiException $e) {
                Yii::error('Push HTTP: ' . $e->getMessage(), FcmPushConfig::LOG_CATEGORY);
            }
        }
    }
}
