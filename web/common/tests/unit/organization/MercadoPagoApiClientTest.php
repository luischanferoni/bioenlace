<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Organization\Billing\Infrastructure\External\MercadoPago\MercadoPagoApiClient;
use common\components\Shared\Infrastructure\Http\HttpApiException;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use common\components\Shared\Infrastructure\Http\RetryPolicy;
use common\tests\unit\shared\infrastructure\http\Support\FakeTransport;
use yii\httpclient\Client;

/**
 * ACL de Mercado Pago: payloads, idempotencia y compatibilidad de errores.
 *
 * @see MercadoPagoApiClient
 */
class MercadoPagoApiClientTest extends Unit
{
    public function testCreatePreapprovalMandaIdempotenciaYDevuelveElBody(): void
    {
        $transport = (new FakeTransport())->respond(201, json_encode([
            'id' => 'preapproval-1',
            'init_point' => 'https://mp.test/checkout',
            'status' => 'pending',
        ]));

        $response = $this->acl($transport)->createPreapproval(
            ['reason' => 'Bioenlace - Centro', 'status' => 'pending'],
            'checkout-key-1'
        );

        $this->assertSame('preapproval-1', $response['id']);

        $sent = $transport->sent()[0];
        $this->assertSame('POST', $sent['method']);
        $this->assertSame('https://api.mercadopago.com/preapproval', $sent['url']);
        $this->assertSame('checkout-key-1', $sent['headers']['x-idempotency-key']);
        $this->assertSame('Bearer test-token', $sent['headers']['authorization']);
        $this->assertSame('Bioenlace - Centro', json_decode((string) $sent['content'], true)['reason']);
    }

    public function testError402ConservaElMensajeConCauses(): void
    {
        $transport = (new FakeTransport())->respond(402, json_encode([
            'message' => 'invalid_payment',
            'cause' => [['code' => '1', 'description' => 'saldo insuficiente']],
        ]));

        try {
            $this->acl($transport)->createPreapproval(['reason' => 'x'], 'key');
            $this->fail('Se esperaba RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertSame(
                'Mercado Pago rechazó la operación (HTTP 402): invalid_payment [1: saldo insuficiente]',
                $e->getMessage()
            );
        }
    }

    public function testTimeoutConservaElMensajeHistorico(): void
    {
        $transport = (new FakeTransport())->fail();

        try {
            $this->acl($transport)->getPreapproval('abc');
            $this->fail('Se esperaba RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertSame('No se pudo conectar con Mercado Pago.', $e->getMessage());
            $this->assertInstanceOf(HttpApiException::class, $e->getPrevious());
        }
    }

    public function testCancelMandaPutConEstadoCancelled(): void
    {
        $transport = (new FakeTransport())->respond(200, '{"status":"cancelled"}');

        $response = $this->acl($transport)->cancelPreapproval('preapproval-1');

        $this->assertSame(['status' => 'cancelled'], $response);

        $sent = $transport->sent()[0];
        $this->assertSame('PUT', $sent['method']);
        $this->assertSame('https://api.mercadopago.com/preapproval/preapproval-1', $sent['url']);
        $this->assertSame(['status' => 'cancelled'], json_decode((string) $sent['content'], true));
    }

    public function testGetAuthorizedPaymentArmaLaRutaConEscape(): void
    {
        $transport = (new FakeTransport())->respond(200, '{"id":"123"}');

        $this->acl($transport)->getAuthorizedPayment('123');

        $this->assertSame(
            'https://api.mercadopago.com/authorized_payments/123',
            $transport->sent()[0]['url']
        );
    }

    private function acl(FakeTransport $transport): MercadoPagoApiClient
    {
        return new MercadoPagoApiClient('test-token', new JsonApiClient(
            baseUrl: MercadoPagoApiClient::API_URL,
            defaultHeaders: ['Authorization' => 'Bearer test-token'],
            connectTimeout: 1,
            timeout: 1,
            logCategory: MercadoPagoApiClient::PROVIDER,
            retry: new RetryPolicy(2, 0, 0),
            provider: MercadoPagoApiClient::PROVIDER,
            providerLabel: MercadoPagoApiClient::PROVIDER_LABEL,
            httpClient: new Client(['transport' => $transport]),
        ));
    }
}
