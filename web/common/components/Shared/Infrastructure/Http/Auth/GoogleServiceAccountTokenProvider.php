<?php

namespace common\components\Shared\Infrastructure\Http\Auth;

use common\components\Shared\Infrastructure\Http\HttpApiException;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use Yii;

/**
 * Token OAuth2 de una cuenta de servicio de Google (JWT firmado RS256 → JWT-bearer).
 *
 * Es el mismo flujo para Vertex/Gemini, GCS, batch y FCM: sólo cambian el `scope`, el
 * archivo de credenciales y la clave de caché. El JWT se firma con `openssl`.
 */
final class GoogleServiceAccountTokenProvider implements AccessTokenProvider
{
    private const DEFAULT_TOKEN_URI = 'https://oauth2.googleapis.com/token';

    /** Margen de seguridad: el token dura 1 hora y se renueva 10 minutos antes. */
    private const EXPIRY_MARGIN_SECONDS = 600;

    private const MIN_TTL_SECONDS = 60;

    private ?string $token = null;

    /**
     * @param array<string, mixed>|null $credentials credenciales ya decodificadas; si es null
     *        se leen de `$credentialsPath` en cada `token()` (el archivo puede rotar)
     * @param string|null $cacheKey clave literal; si es null se deriva del path
     */
    public function __construct(
        private readonly string $credentialsPath,
        private readonly string $scope,
        private readonly ?JsonApiClient $client = null,
        private readonly ?array $credentials = null,
        private readonly string $cacheKeyPrefix = 'google_sa_token',
        private readonly ?string $cacheKey = null,
        private readonly string $logCategory = 'ia-manager'
    ) {
    }

    public function token(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $cache = Yii::$app->cache;
        $cacheKey = $this->cacheKey();
        if ($cache !== null) {
            $cached = $cache->get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $this->token = $cached;
            }
        }

        $credentials = $this->credentials ?? $this->readCredentials();
        $now = time();
        $jwt = $this->createJwt($credentials, $now);
        $tokenUri = trim((string) ($credentials['token_uri'] ?? '')) ?: self::DEFAULT_TOKEN_URI;

        try {
            $response = $this->client()->postForm($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);
        } catch (HttpApiException $e) {
            throw new OAuthTokenException(
                'Google service account: ' . $e->getMessage(),
                $e->statusCode(),
                $e
            );
        }

        $body = $response->body();
        $token = trim((string) ($body['access_token'] ?? ''));
        if ($token === '') {
            throw new OAuthTokenException('Google service account: access_token vacío.');
        }

        $ttl = (int) ($body['expires_in'] ?? 3600) - self::EXPIRY_MARGIN_SECONDS;
        if ($cache !== null) {
            $cache->set($cacheKey, $token, max(self::MIN_TTL_SECONDS, $ttl));
        }

        return $this->token = $token;
    }

    public function invalidate(): void
    {
        $this->token = null;

        $cache = Yii::$app->cache;
        if ($cache !== null) {
            $cache->delete($this->cacheKey());
        }
    }

    /** @return array<string, mixed> */
    private function readCredentials(): array
    {
        $path = trim($this->credentialsPath);
        if ($path === '' || !is_file($path)) {
            throw new OAuthTokenException('Google service account: credenciales no encontradas en ' . $path);
        }

        $json = @file_get_contents($path);
        $credentials = $json === false ? null : json_decode($json, true);
        if (!is_array($credentials) || !isset($credentials['private_key'], $credentials['client_email'])) {
            throw new OAuthTokenException('Google service account: JSON de credenciales inválido en ' . $path);
        }

        return $credentials;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function createJwt(array $credentials, int $now): string
    {
        $tokenUri = trim((string) ($credentials['token_uri'] ?? '')) ?: self::DEFAULT_TOKEN_URI;
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => (string) $credentials['client_email'],
            'scope' => $this->scope,
            'aud' => $tokenUri,
            'exp' => $now + 3600,
            'iat' => $now,
        ];

        $signatureInput = self::base64UrlEncode((string) json_encode($header))
            . '.' . self::base64UrlEncode((string) json_encode($claims));

        $privateKey = openssl_pkey_get_private((string) $credentials['private_key']);
        if ($privateKey === false) {
            throw new OAuthTokenException('Google service account: clave privada inválida.');
        }

        $signature = '';
        if (!openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new OAuthTokenException('Google service account: no se pudo firmar el JWT.');
        }

        return $signatureInput . '.' . self::base64UrlEncode($signature);
    }

    private function client(): JsonApiClient
    {
        return $this->client ?? new JsonApiClient(
            baseUrl: '',
            connectTimeout: 5,
            timeout: 30,
            logCategory: $this->logCategory,
            provider: 'google-oauth',
            providerLabel: 'Google OAuth'
        );
    }

    private function cacheKey(): string
    {
        return $this->cacheKey ?? $this->cacheKeyPrefix . '_' . md5($this->credentialsPath);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
