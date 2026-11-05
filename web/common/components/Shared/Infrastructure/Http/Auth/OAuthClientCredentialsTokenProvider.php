<?php

namespace common\components\Shared\Infrastructure\Http\Auth;

use common\components\Shared\Infrastructure\Http\JsonApiClient;
use Yii;

/**
 * OAuth `client_credentials` con cache entre requests.
 *
 * Es la única implementación del flujo: cada conector aporta URL del token, credenciales
 * y un {@see JsonApiClient} sin autenticación.
 */
final class OAuthClientCredentialsTokenProvider implements AccessTokenProvider
{
    private const DEFAULT_TTL_SECONDS = 3000;

    private const MIN_TTL_SECONDS = 60;

    private ?string $token = null;

    public function __construct(
        private readonly JsonApiClient $client,
        private readonly string $tokenPath,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $cacheKeyPrefix = 'http_oauth_token'
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

        $response = $this->client->postForm($this->tokenPath, [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        $token = (string) ($response->body()['access_token'] ?? '');
        if ($token === '') {
            throw new \RuntimeException('OAuth client_credentials: access_token vacío.');
        }

        $ttl = (int) ($response->body()['expires_in'] ?? self::DEFAULT_TTL_SECONDS);
        if ($cache !== null) {
            $cache->set($cacheKey, $token, max(self::MIN_TTL_SECONDS, $ttl - self::MIN_TTL_SECONDS));
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

    private function cacheKey(): string
    {
        return $this->cacheKeyPrefix . '_' . md5($this->clientId . '|' . $this->tokenPath);
    }
}
