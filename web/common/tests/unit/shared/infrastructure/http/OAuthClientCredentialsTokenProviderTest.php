<?php

namespace common\tests\unit\shared\infrastructure\http;

use Codeception\Test\Unit;
use common\components\Shared\Infrastructure\Http\Auth\OAuthClientCredentialsTokenProvider;
use common\components\Shared\Infrastructure\Http\Auth\OAuthTokenException;
use common\components\Shared\Infrastructure\Http\Auth\StaticTokenProvider;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use common\components\Shared\Infrastructure\Http\RetryPolicy;
use common\tests\unit\shared\infrastructure\http\Support\FakeTransport;
use Yii;
use yii\httpclient\Client;

/**
 * Un solo flujo `client_credentials`: cache con `expires_in - 60`, `invalidate()` y un
 * único mensaje de error.
 *
 * @see OAuthClientCredentialsTokenProvider
 */
class OAuthClientCredentialsTokenProviderTest extends Unit
{
    protected function _before(): void
    {
        Yii::$app->set('cache', ['class' => \yii\caching\ArrayCache::class]);
    }

    protected function _after(): void
    {
        Yii::$app->set('cache', ['class' => \yii\caching\ArrayCache::class]);
    }

    public function testCacheMissPideTokenFormEncoded(): void
    {
        $transport = (new FakeTransport())->respond(200, '{"access_token":"tok-1","expires_in":3600}');

        $this->assertSame('tok-1', $this->provider($transport)->token());

        $sent = $transport->sent()[0];
        $this->assertSame('POST', $sent['method']);
        $this->assertSame('https://auth.test/oauth/token', $sent['url']);
        $this->assertStringContainsString('x-www-form-urlencoded', (string) $sent['headers']['content-type']);
        $this->assertStringContainsString('grant_type=client_credentials', (string) $sent['content']);
        $this->assertStringContainsString('client_id=cid', (string) $sent['content']);
    }

    public function testCacheHitNoVuelveAPedirElToken(): void
    {
        $transport = (new FakeTransport())->respond(200, '{"access_token":"tok-1","expires_in":3600}');
        $provider = $this->provider($transport);

        $provider->token();

        // Otro provider con las mismas credenciales lee la cache de la app.
        $segundo = $this->provider($transport);
        $this->assertSame('tok-1', $segundo->token());
        $this->assertCount(1, $transport->sent(), 'El segundo provider debe leer de cache.');
    }

    public function testExpiresInAusenteUsaElTtlPorDefecto(): void
    {
        $transport = (new FakeTransport())->respond(200, '{"access_token":"tok-sin-ttl"}');

        $this->assertSame('tok-sin-ttl', $this->provider($transport)->token());

        $cache = Yii::$app->cache;
        $this->assertSame('tok-sin-ttl', $cache->get($this->cacheKey()));
    }

    public function testExpiresInCortoRespetaElMinimoDeSesentaSegundos(): void
    {
        $transport = (new FakeTransport())->respond(200, '{"access_token":"tok-corto","expires_in":30}');

        $this->provider($transport)->token();

        $this->assertSame('tok-corto', Yii::$app->cache->get($this->cacheKey()));
    }

    public function testInvalidateBorraLaCacheYPideUnoNuevo(): void
    {
        $transport = (new FakeTransport())
            ->respond(200, '{"access_token":"tok-1","expires_in":3600}')
            ->respond(200, '{"access_token":"tok-2","expires_in":3600}');
        $provider = $this->provider($transport);

        $this->assertSame('tok-1', $provider->token());
        $provider->invalidate();
        $this->assertSame('tok-2', $provider->token());
        $this->assertCount(2, $transport->sent());
    }

    public function testAccessTokenVacioFallaConExcepcionDeToken(): void
    {
        $transport = (new FakeTransport())->respond(200, '{"token_type":"bearer"}');

        $this->expectException(OAuthTokenException::class);
        $this->expectExceptionMessage('OAuth client_credentials: access_token vacío.');

        $this->provider($transport)->token();
    }

    public function testHttp401SeTraduceAExcepcionDeTokenConElMensajeDelProveedor(): void
    {        $transport = (new FakeTransport())->respond(401, '{"error":"invalid_client"}');

        try {
            $this->provider($transport)->token();
            $this->fail('Se esperaba OAuthTokenException.');
        } catch (OAuthTokenException $e) {
            $this->assertStringContainsString('OAuth client_credentials:', $e->getMessage());
            $this->assertStringContainsString('HTTP 401', $e->getMessage());
            $this->assertSame(401, $e->getCode());
        }
    }

    public function testCacheKeyLiteralSeRespeta(): void
    {
        $transport = (new FakeTransport())->respond(200, '{"access_token":"tok-legacy","expires_in":3600}');
        $legado = 'clinical_history_exchange_token_' . md5('nacional-fhircid');

        $provider = new OAuthClientCredentialsTokenProvider(
            client: $this->client($transport),
            tokenPath: '/oauth/token',
            clientId: 'cid',
            clientSecret: 'secret',
            cacheKey: $legado
        );

        $provider->token();

        $this->assertSame('tok-legacy', Yii::$app->cache->get($legado));
        $this->assertFalse(
            Yii::$app->cache->get($this->cacheKey()),
            'Con clave literal no se escribe la clave derivada.'
        );
    }

    public function testSinCacheConfiguradaSigueFuncionando(): void
    {
        $transport = (new FakeTransport())->respond(200, '{"access_token":"tok-x","expires_in":3600}');
        $provider = $this->provider($transport);

        Yii::$app->set('cache', null);

        try {
            $this->assertSame('tok-x', $provider->token());
        } finally {
            Yii::$app->set('cache', ['class' => \yii\caching\ArrayCache::class]);
        }
    }

    public function testStaticTokenProviderNoHaceRed(): void
    {
        $provider = new StaticTokenProvider('api-key');

        $this->assertSame('api-key', $provider->token());
        $provider->invalidate();
        $this->assertSame('api-key', $provider->token());
    }

    private function provider(FakeTransport $transport): OAuthClientCredentialsTokenProvider
    {
        return new OAuthClientCredentialsTokenProvider(
            client: $this->client($transport),
            tokenPath: '/oauth/token',
            clientId: 'cid',
            clientSecret: 'secret',
            cacheKeyPrefix: 'oauth_token_sianlabs'
        );
    }

    private function client(FakeTransport $transport): JsonApiClient
    {
        return new JsonApiClient(
            baseUrl: 'https://auth.test',
            connectTimeout: 1,
            timeout: 1,
            logCategory: 'oauth-test',
            retry: new RetryPolicy(0, 0, 0),
            provider: 'oauth-test',
            httpClient: new Client(['transport' => $transport]),
        );
    }

    private function cacheKey(): string
    {
        return 'oauth_token_sianlabs_' . md5('cid|/oauth/token');
    }
}
