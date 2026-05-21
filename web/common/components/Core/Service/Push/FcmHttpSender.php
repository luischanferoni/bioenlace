<?php

namespace common\components\Core\Service\Push;

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

        $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/messages:send';

        try {
            $client = new \yii\httpclient\Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($url)
                ->addHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ])
                ->setFormat(\yii\httpclient\Client::FORMAT_JSON)
                ->setData(['message' => $message])
                ->send();

            if ($response->isOk) {
                return true;
            }
            Yii::error(
                'FCM v1: ' . ($response->statusCode ?? '?') . ' ' . ($response->content ?? ''),
                FcmPushConfig::LOG_CATEGORY
            );
        } catch (\Throwable $e) {
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
            $client = new \yii\httpclient\Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl('https://fcm.googleapis.com/fcm/send')
                ->addHeaders(['Authorization' => 'key=' . $serverKey])
                ->setFormat(\yii\httpclient\Client::FORMAT_JSON)
                ->setData($payload)
                ->send();

            return $response->isOk;
        } catch (\Throwable $e) {
            Yii::error('FCM legacy: ' . $e->getMessage(), FcmPushConfig::LOG_CATEGORY);

            return false;
        }
    }
}
