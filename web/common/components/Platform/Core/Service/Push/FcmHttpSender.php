<?php

namespace common\components\Platform\Core\Service\Push;

use common\components\Shared\Infrastructure\Http\HttpApiException;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use Yii;

/**
 * Envío FCM (HTTP v1 o legacy). Config: params fcmPush.
 */
final class FcmHttpSender
{
    /**
     * @param array<string, string> $data
     */
    public static function send(string $token, string $title, string $body, array $data = []): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        if (self::sendViaHttpV1($token, $title, $body, $data)) {
            return true;
        }

        return self::sendViaLegacy($token, $title, $body, $data);
    }

    /**
     * @param array<string, string> $data
     */
    private static function sendViaHttpV1(string $deviceToken, string $title, string $body, array $data): bool
    {
        $cfg = FcmPushConfig::get();
        $projectId = isset($cfg['projectId']) ? trim((string) $cfg['projectId']) : '';
        if ($projectId === '' && !empty($cfg['credentialsPath']) && is_file($cfg['credentialsPath'])) {
            $json = json_decode((string) file_get_contents($cfg['credentialsPath']), true);
            if (is_array($json) && !empty($json['project_id'])) {
                $projectId = (string) $json['project_id'];
            }
        }
        if ($projectId === '') {
            return false;
        }

        $accessToken = FcmGoogleAuth::getAccessToken();
        if ($accessToken === '') {
            return false;
        }

        $dataPayload = [];
        foreach ($data as $k => $v) {
            $dataPayload[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
        }

        $message = [
            'token' => $deviceToken,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => $dataPayload,
            'android' => ['priority' => 'HIGH'],
        ];

        $path = '/v1/projects/' . rawurlencode($projectId) . '/messages:send';

        try {
            self::fcmClient(['Authorization' => 'Bearer ' . $accessToken])
                ->post($path, ['message' => $message]);

            return true;
        } catch (HttpApiException $e) {
            Yii::error('FCM v1: ' . $e->getMessage(), FcmPushConfig::LOG_CATEGORY);
        }

        return false;
    }

    /**
     * @param array<string, string> $data
     */
    private static function sendViaLegacy(string $token, string $title, string $body, array $data): bool
    {
        $cfg = FcmPushConfig::get();
        $serverKey = isset($cfg['fcmServerKey']) ? trim((string) $cfg['fcmServerKey']) : '';
        if ($serverKey === '') {
            return false;
        }

        $payload = [
            'to' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => array_map('strval', $data),
            'priority' => 'high',
        ];

        try {
            self::legacyClient(['Authorization' => 'key=' . $serverKey])->post('/fcm/send', $payload);

            return true;
        } catch (HttpApiException $e) {
            Yii::error('FCM legacy: ' . $e->getMessage(), FcmPushConfig::LOG_CATEGORY);

            return false;
        }
    }

    /** @param array<string, string> $headers */
    private static function fcmClient(array $headers): JsonApiClient
    {
        return new JsonApiClient(
            baseUrl: 'https://fcm.googleapis.com',
            defaultHeaders: $headers,
            connectTimeout: 5,
            timeout: 20,
            logCategory: FcmPushConfig::LOG_CATEGORY,
            provider: 'fcm',
            providerLabel: 'FCM'
        );
    }

    /** @param array<string, string> $headers */
    private static function legacyClient(array $headers): JsonApiClient
    {
        return new JsonApiClient(
            baseUrl: 'https://fcm.googleapis.com',
            defaultHeaders: $headers,
            connectTimeout: 5,
            timeout: 20,
            logCategory: FcmPushConfig::LOG_CATEGORY,
            provider: 'fcm-legacy',
            providerLabel: 'FCM legacy'
        );
    }
}
