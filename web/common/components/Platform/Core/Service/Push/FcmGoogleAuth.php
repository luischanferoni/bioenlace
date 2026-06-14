<?php

namespace common\components\Platform\Core\Service\Push;

use Yii;
use yii\httpclient\Client;

/**
 * OAuth2 para FCM HTTP v1 (independiente de Vertex / google_cloud_*).
 */
final class FcmGoogleAuth
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public static function getAccessToken(): string
    {
        $cfg = FcmPushConfig::get();
        $credentialsPath = isset($cfg['credentialsPath']) ? trim((string) $cfg['credentialsPath']) : '';
        if ($credentialsPath === '' || !is_file($credentialsPath)) {
            return '';
        }

        $credentialsJson = file_get_contents($credentialsPath);
        $credentials = json_decode($credentialsJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($credentials['private_key'], $credentials['client_email'])) {
            Yii::error('FCM: JSON de credenciales inválido en ' . $credentialsPath, FcmPushConfig::LOG_CATEGORY);

            return '';
        }

        $cacheKey = 'fcm_oauth_token_' . md5($credentialsPath);
        $cached = Yii::$app->cache->get($cacheKey);
        if ($cached !== false && is_string($cached) && $cached !== '') {
            return $cached;
        }

        $now = time();
        $jwt = self::createJwt($credentials, $now);
        if ($jwt === '') {
            return '';
        }

        $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        try {
            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($tokenUri)
                ->setContent(http_build_query([
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]))
                ->addHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                ->send();

            if (!$response->isOk) {
                Yii::error(
                    'FCM OAuth: ' . ($response->statusCode ?? '?') . ' ' . ($response->content ?? ''),
                    FcmPushConfig::LOG_CATEGORY
                );

                return '';
            }
            $tokenData = json_decode($response->content, true);
            $accessToken = is_array($tokenData) ? (string) ($tokenData['access_token'] ?? '') : '';
            if ($accessToken === '') {
                return '';
            }
            $expiresIn = (int) ($tokenData['expires_in'] ?? 3600) - 600;
            Yii::$app->cache->set($cacheKey, $accessToken, max(60, $expiresIn));

            return $accessToken;
        } catch (\Throwable $e) {
            Yii::error('FCM OAuth: ' . $e->getMessage(), FcmPushConfig::LOG_CATEGORY);

            return '';
        }
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private static function createJwt(array $credentials, int $now): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ];

        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $claimsEncoded = self::base64UrlEncode(json_encode($claims));
        $signatureInput = $headerEncoded . '.' . $claimsEncoded;

        $privateKey = openssl_pkey_get_private($credentials['private_key']);
        if ($privateKey === false) {
            Yii::error('FCM JWT: clave privada inválida', FcmPushConfig::LOG_CATEGORY);

            return '';
        }

        $signature = '';
        if (!openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            openssl_free_key($privateKey);

            return '';
        }
        openssl_free_key($privateKey);

        return $signatureInput . '.' . self::base64UrlEncode($signature);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
