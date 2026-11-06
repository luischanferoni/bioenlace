<?php

namespace common\tests\unit\shared\infrastructure\http;

use Codeception\Test\Unit;
use common\components\Shared\Infrastructure\Http\Auth\GoogleServiceAccountTokenProvider;
use common\components\Shared\Infrastructure\Http\Auth\OAuthTokenException;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use common\components\Shared\Infrastructure\Http\RetryPolicy;
use common\tests\unit\shared\infrastructure\http\Support\FakeTransport;
use Yii;
use yii\httpclient\Client;

/**
 * Cuenta de servicio de Google: JWT RS256 → JWT-bearer, cache e `invalidate()`.
 *
 * @see GoogleServiceAccountTokenProvider
 */
class GoogleServiceAccountTokenProviderTest extends Unit
{
    private const SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    protected function _before(): void
    {
        Yii::$app->set('cache', ['class' => \yii\caching\ArrayCache::class]);
    }

    protected function _after(): void
    {
        Yii::$app->set('cache', ['class' => \yii\caching\ArrayCache::class]);
    }

    public function testPideTokenConJwtFirmadoYLoCachea(): void
    {
        $transport = (new FakeTransport())->respond(200, '{"access_token":"ya29.tok","expires_in":3600}');
        $provider = $this->provider($transport);

        $this->assertSame('ya29.tok', $provider->token());
        $this->assertSame('ya29.tok', $provider->token(), 'Segunda llamada desde cache.');
        $this->assertCount(1, $transport->sent());

        $sent = $transport->sent()[0];
        $this->assertSame('POST', $sent['method']);
        $this->assertSame('https://oauth2.googleapis.com/token', $sent['url']);
        $this->assertStringContainsString('x-www-form-urlencoded', (string) $sent['headers']['content-type']);

        parse_str((string) $sent['content'], $form);
        $this->assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $form['grant_type']);

        $parts = explode('.', (string) $form['assertion']);
        $this->assertCount(3, $parts, 'El JWT lleva header.payload.firma.');

        $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);
        $this->assertSame(self::SCOPE, $claims['scope']);
        $this->assertSame('cuenta-servicio@test.iam.gserviceaccount.com', $claims['iss']);
        $this->assertSame('https://oauth2.googleapis.com/token', $claims['aud']);
    }

    public function testInvalidateBorraLaCache(): void
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

    public function testHttpDeErrorSeTraduceAExcepcionDeToken(): void
    {
        $transport = (new FakeTransport())->respond(400, '{"error":"invalid_grant"}');

        try {
            $this->provider($transport)->token();
            $this->fail('Se esperaba OAuthTokenException.');
        } catch (OAuthTokenException $e) {
            $this->assertStringContainsString('Google service account:', $e->getMessage());
            $this->assertSame(400, $e->getCode());
        }
    }

    public function testCredencialesInvalidasFallaSinRed(): void
    {
        $transport = new FakeTransport();
        $provider = new GoogleServiceAccountTokenProvider(
            credentialsPath: 'D:/no/existe/credenciales.json',
            scope: self::SCOPE,
            client: $this->client($transport)
        );

        $this->expectException(OAuthTokenException::class);
        $this->expectExceptionMessage('credenciales no encontradas');

        $provider->token();
    }

    private function provider(FakeTransport $transport): GoogleServiceAccountTokenProvider
    {
        return new GoogleServiceAccountTokenProvider(
            credentialsPath: 'memoria://cuenta-servicio',
            scope: self::SCOPE,
            client: $this->client($transport),
            credentials: [
                'client_email' => 'cuenta-servicio@test.iam.gserviceaccount.com',
                'private_key' => $this->privateKeyPem(),
            ]
        );
    }

    private function client(FakeTransport $transport): JsonApiClient
    {
        return new JsonApiClient(
            baseUrl: '',
            connectTimeout: 1,
            timeout: 1,
            logCategory: 'oauth-test',
            retry: new RetryPolicy(0, 0, 0),
            provider: 'google-oauth',
            providerLabel: 'Google OAuth',
            httpClient: new Client(['transport' => $transport])
        );
    }

    private function privateKeyPem(): string
    {
        // Clave RSA de prueba (desechable, generada para este test): firmar no requiere
        // `openssl.cnf`, pero generarla en runtime sí, y no todos los PHP lo tienen.
        return <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDWxh37gi4q5PPm
hDr0SMgR2kM9Ni2YXumDIC9w2pR7ypX6Qgi7dD/+7zx3o2smxOOkopQ9i/QPVeGa
NqVliWog4G2iN6td9cxKQtztpEkLNRs3/nqdCoFBoCdRy49N0fDM6pEUXt4hLFMA
L8n4vKZ9Sm1YY8EdJcvoaoU+nqQNDkAHpEyeZbHVvb74ELhc4dciYiJ13g72BzRe
KLOzutUwkwM7y5rNIh/vOcuLrgQgTUEsgWhwlh6komVMPYv6BvkBTdGZe3jJUk28
NhSscMfIdoxdbB5mix9DOlN9sHzTEnLfGv9++YqNIr4IqhX17Grtf5JFNCoM83tS
6vs6vSZlAgMBAAECggEAAW2SS/aO34ajr4PwD2k/eUzp9wl57qIs68wGPLobVCJh
8mbHf5WTilqlXIdH4ltbRyA0m58nWYXA6QFVgrIR9FOBrxsvJsIIYHMG3Iaj3LD2
9B2bS5mDCABsWoEfvAz6DKa/nbLOx8ovaBgpP9/jPICG7xhEs7CpZSt2JNcxsIuF
V7KXyewOAmEz2jqzBhIwTe1NBK6/jG/KSyW/g5fJB088zEK+pZAnDJkACCbXe+0w
PvIR6HrJk4U34sPqqZGudtSng62h/7/WZKzDR3sr4R+0afIdq0gAhq/kYujiiLZZ
pPouzprC6Hp5v9lO2xEcgKSVVKpn58V2oeBuUfSCuQKBgQDsMvc6GL06BsgTeu2o
qbcPu6iYyzI0TSMm7mKAc5TUq3fV9TEMgYid89g1loT+QaWqSj1J3oMlk6jLTSMS
r2PVrKd+D0u9He5xP1gBiX0co+24AW54gt1pFOQppewScTgQCrx2psv0yRjCkezN
1k3k+alb3dNOwXet/BfaT3JlbQKBgQDox1k3O0kKbSodNW6zAstibnJ7uTO5EGJg
BBd5G9eAGNnm7LE6iyqzCZlSToiOvq6Ju78jY2aCzKcdKfWmrwsazgtRupFyTVcl
efw18vQLUW2ixk899qvqW1PqzcRy2AHVDSnvC9ZVvC0ZFqaLBjrBbwNRbdlAPVBi
XM8xusbB2QKBgQCqBXnO2RYFGfKViyxd92Str5CKA+JsoYYHNRu0iP5+I7uMSQX4
kZmM0YU19dZfy1N8BBqf/Mr8lpCvXyQ7BANiQPkEFfCX/l6wKD+qQ5TKs/mfNXCW
0OSeCoq+rBAFqngHnCLTafdwif0HudDyziueAlT1qvS7QSpsFAX7gKrA9QKBgBBU
3CeJ20gF/Kxr8UZR7d8MDLAtgDl9ssk1wEfY8jekhrUy6gRHe0lwZPdnVyFcs3Yq
W/CpqqmGnjrnlLqLEWZiCl1Dj6zLTE/uzQkj8IdSwTRoYWrhgZJBshOJOD0udoJy
F/0TgYgmWaum8SxT1Y94sGzoHhPpuVgNVuQQTJ2RAoGBAL6FNLxb0hmGt/hqDkuh
eaqjp3nV3YoUhdJvttXeNVyz4hJQ+eWEWbZqBkdfzwzJQbJn3JVtkvf2kK1P4QXN
i9VEVYR+gby23ZpTqGgK+XyZlOM6bmKHSsncFkWZocXiOi1oSAAjVSe6Q2/IAT/b
X9eCDtCzlpoOehllctmCgd58
-----END PRIVATE KEY-----
PEM;
    }
}
