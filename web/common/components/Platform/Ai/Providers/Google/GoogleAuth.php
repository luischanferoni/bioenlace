<?php

namespace common\components\Platform\Ai\Providers\Google;

use Yii;
use yii\httpclient\Client;

final class GoogleAuth
{
    /**
     * Obtener token de acceso de Google Cloud (OAuth2).
     * Si hay API key configurada, no se requiere token.
     */
    public static function getAccessToken(): string
    {
        if (GoogleCloudConfigResolver::apiKey() !== '') {
            return '';
        }

        $credentialsPath = GoogleCloudConfigResolver::credentialsPath();
        if ($credentialsPath === null) {
            Yii::warning(
                'Google Cloud credentials no encontradas. Configure google_cloud_credentials_path o google_cloud_api_key en '
                . GoogleCloudConfigResolver::PARAMS_HINT,
                'ia-manager'
            );
            return '';
        }

        $credentials = GoogleCloudConfigResolver::readCredentials();
        if ($credentials === null || !isset($credentials['private_key'])) {
            Yii::error(
                'Error leyendo credenciales de Google Cloud en: ' . $credentialsPath,
                'ia-manager'
            );
            return '';
        }

        $cacheKey = 'google_oauth_token_' . md5($credentialsPath);
        $cachedToken = Yii::$app->cache->get($cacheKey);
        if ($cachedToken !== false) {
            return (string) $cachedToken;
        }

        $now = time();
        $jwt = self::createJwt($credentials, $now);
        if ($jwt === '') {
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
                if ($accessToken !== '') {
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
     * @param array<string, mixed> $credentials
     */
    private static function createJwt(array $credentials, int $now): string
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

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
