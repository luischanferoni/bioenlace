<?php

namespace common\components\Ai\Providers\Google;

use Yii;
use yii\httpclient\Client;

final class GoogleAuth
{
    /**
     * Obtener token de acceso de Google Cloud (OAuth2).
     * Si hay API key configurada, no se requiere token.
     *
     * @return string
     */
    public static function getAccessToken()
    {
        if (!empty(Yii::$app->params['google_cloud_api_key'] ?? '')) {
            return '';
        }

        $credentialsPath = Yii::$app->params['google_cloud_credentials_path'] ?? '';
        if (empty($credentialsPath) || !file_exists($credentialsPath)) {
            Yii::warning(
                'Google Cloud credentials no encontradas. Configure google_cloud_credentials_path o google_cloud_api_key en frontend/config/params-local.php',
                'ia-manager'
            );
            return '';
        }

        $credentialsJson = file_get_contents($credentialsPath);
        $credentials = json_decode($credentialsJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($credentials['private_key'])) {
            Yii::error('Error leyendo credenciales de Google Cloud: ' . json_last_error_msg(), 'ia-manager');
            return '';
        }

        $cacheKey = 'google_oauth_token_' . md5($credentialsPath);
        $cachedToken = Yii::$app->cache->get($cacheKey);
        if ($cachedToken !== false) {
            return $cachedToken;
        }

        $now = time();
        $jwt = self::createJwt($credentials, $now);
        if (empty($jwt)) {
            Yii::error('Error creando JWT para Google Cloud', 'ia-manager');
            return '';
        }

        $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        $client = new Client();

        try {
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($tokenUri)
                ->setContent(http_build_query([
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]))
                ->addHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                ->send();

            if ($response->isOk) {
                $tokenData = json_decode($response->content, true);
                $accessToken = $tokenData['access_token'] ?? '';
                if (!empty($accessToken)) {
                    $expiresIn = ($tokenData['expires_in'] ?? 3600) - 600;
                    Yii::$app->cache->set($cacheKey, $accessToken, $expiresIn);
                    return $accessToken;
                }
            } else {
                Yii::error(
                    'Error obteniendo token de Google Cloud: ' . ($response->statusCode ?? 'unknown') . ' - ' . ($response->content ?? ''),
                    'ia-manager'
                );
            }
        } catch (\Exception $e) {
            Yii::error('Excepción obteniendo token de Google Cloud: ' . $e->getMessage(), 'ia-manager');
        }

        return '';
    }

    /**
     * @param array $credentials
     * @param int $now
     * @return string
     */
    private static function createJwt($credentials, $now)
    {
        if (!isset($credentials['private_key'], $credentials['client_email'])) {
            return '';
        }

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ];

        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $claimsEncoded = self::base64UrlEncode(json_encode($claims));
        $signatureInput = $headerEncoded . '.' . $claimsEncoded;

        $privateKey = openssl_pkey_get_private($credentials['private_key']);
        if ($privateKey === false) {
            Yii::error('Error obteniendo clave privada de Google Cloud: ' . openssl_error_string(), 'ia-manager');
            return '';
        }

        $signature = '';
        if (!openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            Yii::error('Error firmando JWT: ' . openssl_error_string(), 'ia-manager');
            openssl_free_key($privateKey);
            return '';
        }

        openssl_free_key($privateKey);
        $signatureEncoded = self::base64UrlEncode($signature);
        return $signatureInput . '.' . $signatureEncoded;
    }

    /**
     * @param string $data
     * @return string
     */
    private static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

