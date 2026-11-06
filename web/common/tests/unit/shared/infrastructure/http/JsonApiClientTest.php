<?php

namespace common\tests\unit\shared\infrastructure\http;

use Codeception\Test\Unit;
use common\components\Shared\Infrastructure\Http\HttpApiException;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use common\components\Shared\Infrastructure\Http\RetryPolicy;
use common\tests\unit\shared\infrastructure\http\Support\FakeTransport;
use Yii;
use yii\httpclient\Client;

/**
 * Contrato del transporte compartido: qué se reintenta, qué se propaga y qué se loguea.
 *
 * @see JsonApiClient
 */
class JsonApiClientTest extends Unit
{
    public function testRespuestaOkDevuelveBodyYHeaders(): void
    {
        $transport = (new FakeTransport())->respond(
            200,
            '{"ok":true}',
            ['Location' => 'https://fhir.test/Encounter/abc']
        );

        $response = $this->client($transport)->post('/fhir/Encounter', ['resourceType' => 'Encounter']);

        $this->assertTrue($response->isOk());
        $this->assertSame(['ok' => true], $response->body());
        $this->assertSame('https://fhir.test/Encounter/abc', $response->header('LOCATION'));
    }

    public function testBodyNoJsonDevuelveArrayVacioYConservaElCrudo(): void
    {
        $transport = (new FakeTransport())->respond(204, '');

        $response = $this->client($transport)->delete('/recurso/1');

        $this->assertSame([], $response->body());
        $this->assertSame('', $response->rawBody());
    }

    public function testErrorConCauseReproduceElMensajeDeMercadoPago(): void
    {
        $transport = (new FakeTransport())->respond(402, json_encode([
            'message' => 'invalid_payment',
            'cause' => [
                ['code' => '1', 'description' => 'saldo insuficiente'],
                ['code' => '2', 'description' => 'tarjeta vencida'],
            ],
        ]));

        try {
            $this->client($transport)->post('/preapproval', ['reason' => 'plan']);
            $this->fail('Se esperaba HttpApiException.');
        } catch (HttpApiException $e) {
            $this->assertSame(402, $e->statusCode());
            $this->assertSame('mercadopago', $e->provider());
            $this->assertSame(['1: saldo insuficiente', '2: tarjeta vencida'], $e->providerCauses());
            $this->assertSame(
                'Mercado Pago rechazó la operación (HTTP 402): invalid_payment'
                . ' [1: saldo insuficiente; 2: tarjeta vencida]',
                $e->getMessage()
            );
            $this->assertFalse($e->isRetryable());
        }
    }

    public function testTransporteCaidoLanzaStatusCeroYEsRetryable(): void
    {
        $transport = (new FakeTransport())->fail();

        try {
            $this->client($transport)->get('/ping');
            $this->fail('Se esperaba HttpApiException.');
        } catch (HttpApiException $e) {
            $this->assertSame(0, $e->statusCode());
            $this->assertSame('Mercado Pago: no se pudo conectar.', $e->getMessage());
            $this->assertTrue($e->isRetryable());
        }
    }

    public function testGetSeReintentaEn500HastaAgotarLosIntentos(): void
    {
        $transport = (new FakeTransport())
            ->respond(500, '{"message":"boom"}')
            ->respond(500, '{"message":"boom"}')
            ->respond(500, '{"message":"boom"}');

        try {
            $this->client($transport)->get('/recurso');
            $this->fail('Se esperaba HttpApiException.');
        } catch (HttpApiException $e) {
            $this->assertSame(500, $e->statusCode());
        }

        $this->assertCount(3, $transport->sent(), 'GET es idempotente: 1 intento + 2 reintentos.');
    }

    public function testPostSinClaveNoSeReintenta(): void
    {
        $transport = (new FakeTransport())
            ->respond(500, '{"message":"boom"}')
            ->respond(200, '{"ok":true}');

        try {
            $this->client($transport)->post('/preapproval', ['reason' => 'plan']);
            $this->fail('Se esperaba HttpApiException.');
        } catch (HttpApiException $e) {
            $this->assertSame(500, $e->statusCode());
        }

        $this->assertCount(1, $transport->sent(), 'Un POST sin clave nunca se repite.');
    }

    public function testPostConClaveSeReintentaYRepiteLaClave(): void
    {
        $transport = (new FakeTransport())
            ->respond(500, '{"message":"boom"}')
            ->respond(500, '{"message":"boom"}')
            ->respond(201, '{"id":"preapproval-1"}');

        $response = $this->client($transport)->post(
            '/preapproval',
            ['reason' => 'plan'],
            ['X-Idempotency-Key' => 'clave-123']
        );

        $this->assertSame(['id' => 'preapproval-1'], $response->body());

        $sent = $transport->sent();
        $this->assertCount(3, $sent);
        foreach ($sent as $request) {
            $this->assertSame('clave-123', $request['headers']['x-idempotency-key'] ?? null);
        }
    }

    public function testPost4xxNoSeReintenta(): void
    {
        $transport = (new FakeTransport())->respond(422, '{"message":"datos inválidos"}');

        try {
            $this->client($transport)->post('/preapproval', ['reason' => 'plan']);
            $this->fail('Se esperaba HttpApiException.');
        } catch (HttpApiException $e) {
            $this->assertFalse($e->isRetryable());
        }

        $this->assertCount(1, $transport->sent());
    }

    public function testFormEncodedSeEnviaComoFormulario(): void
    {
        $transport = (new FakeTransport())->respond(200, '{"access_token":"tok"}');

        $this->client($transport)->postForm('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => 'cid',
        ]);

        $sent = $transport->sent()[0];
        $this->assertStringContainsString('x-www-form-urlencoded', (string) $sent['headers']['content-type']);
        $this->assertStringContainsString('grant_type=client_credentials', (string) $sent['content']);
    }

    public function testJsonSeEnviaComoJson(): void
    {
        $transport = (new FakeTransport())->respond(200, '{}');

        $this->client($transport)->post('/preapproval', ['reason' => 'plan']);

        $sent = $transport->sent()[0];
        $this->assertStringContainsString('json', (string) $sent['headers']['content-type']);
        $this->assertSame(['reason' => 'plan'], json_decode((string) $sent['content'], true));
    }

    public function testQueryParamsVanEnLaUrlDelGet(): void
    {
        $transport = (new FakeTransport())->respond(200, '{}');

        $this->client($transport)->get('/turnos', ['estado' => 'pendiente', 'page' => 2]);

        $url = $transport->sent()[0]['url'];
        $this->assertStringContainsString('estado=pendiente', $url);
        $this->assertStringContainsString('page=2', $url);
    }

    public function testQuerySobreBaseSinPathNoAgregaBarraIntermedia(): void
    {
        $transport = (new FakeTransport())->respond(200, '{"status":"OK"}');

        $this->client($transport, [], 'https://maps.test/maps/api/geocode/json')
            ->get('', ['latlng' => '-34.6138769,-58.4267282']);

        $url = $transport->sent()[0]['url'];

        $this->assertStringStartsWith(
            'https://maps.test/maps/api/geocode/json?',
            $url,
            'La query se pega a la base: `/json/?…` no existe en la API de Google (404).'
        );
        $this->assertStringNotContainsString('/json/?', $url);
    }

    public function testQuerySobreBaseConBarraFinalNoDuplicaLaBarra(): void
    {
        $transport = (new FakeTransport())->respond(200, '{}');

        $this->client($transport, [], 'https://api.test/v1/')->get('', ['a' => 'b']);

        $this->assertStringStartsWith('https://api.test/v1?', $transport->sent()[0]['url']);
    }

    public function testLogDeErrorNoIncluyeElAuthorization(): void
    {
        $logger = Yii::getLogger();
        $this->assertTrue(
            method_exists($logger, 'getAndClearLog'),
            'El módulo Yii2 de Codeception reemplaza el logger: se lee con getAndClearLog().'
        );
        $logger->getAndClearLog();

        $transport = (new FakeTransport())->respond(402, '{"message":"denegado"}');

        try {
            $this->client($transport, ['Authorization' => 'Bearer secreto-super-privado'])
                ->post('/preapproval', ['reason' => 'plan']);
        } catch (HttpApiException $e) {
            $this->assertSame(402, $e->statusCode());
        }

        $log = $logger->getAndClearLog();

        $this->assertStringContainsString('http.api_error', $log);
        $this->assertStringContainsString('mercadopago', $log);
        $this->assertStringContainsString('denegado', $log, 'El cuerpo del proveedor (truncado) sí se loguea.');
        $this->assertStringNotContainsString('secreto-super-privado', $log, 'Nunca se loguea Authorization.');
    }

    /** @param array<string, string> $defaultHeaders */
    private function client(
        FakeTransport $transport,
        array $defaultHeaders = [],
        string $baseUrl = 'https://api.test'
    ): JsonApiClient {
        return new JsonApiClient(
            baseUrl: $baseUrl,
            defaultHeaders: $defaultHeaders,
            connectTimeout: 1,
            timeout: 1,
            logCategory: 'mercadopago',
            retry: new RetryPolicy(2, 0, 0),
            provider: 'mercadopago',
            providerLabel: 'Mercado Pago',
            httpClient: new Client(['transport' => $transport]),
        );
    }
}
