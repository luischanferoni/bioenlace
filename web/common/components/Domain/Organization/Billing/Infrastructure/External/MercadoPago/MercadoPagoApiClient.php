<?php

namespace common\components\Domain\Organization\Billing\Infrastructure\External\MercadoPago;

use common\components\Shared\Infrastructure\Http\HttpApiException;
use common\components\Shared\Infrastructure\Http\JsonApiClient;

/**
 * ACL de Mercado Pago: sólo endpoints de suscripción y pagos autorizados.
 *
 * Timeouts, reintentos, parseo y log vienen de {@see JsonApiClient}; acá viven el
 * `Bearer`, la clave de idempotencia y la compatibilidad de mensajes del proveedor.
 */
final class MercadoPagoApiClient
{
    public const PROVIDER = 'mercadopago';

    public const PROVIDER_LABEL = 'Mercado Pago';

    public const API_URL = 'https://api.mercadopago.com';

    private readonly JsonApiClient $client;

    public function __construct(string $accessToken, ?JsonApiClient $client = null)
    {
        $this->client = $client ?? new JsonApiClient(
            baseUrl: self::API_URL,
            defaultHeaders: ['Authorization' => 'Bearer ' . trim($accessToken)],
            connectTimeout: 5,
            timeout: 20,
            logCategory: self::PROVIDER,
            provider: self::PROVIDER,
            providerLabel: self::PROVIDER_LABEL,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createPreapproval(array $payload, string $idempotencyKey): array
    {
        return $this->send('POST', '/preapproval', $payload, [
            'X-Idempotency-Key' => $idempotencyKey,
        ]);
    }

    /** @return array<string, mixed> */
    public function getPreapproval(string $preapprovalId): array
    {
        return $this->send('GET', '/preapproval/' . rawurlencode($preapprovalId));
    }

    /** @return array<string, mixed> */
    public function getMe(): array
    {
        return $this->send('GET', '/users/me');
    }

    /** @return array<string, mixed> */
    public function getUser(string $userId): array
    {
        return $this->send('GET', '/users/' . rawurlencode($userId));
    }

    /** @return array<string, mixed> */
    public function getAuthorizedPayment(string $authorizedPaymentId): array
    {
        return $this->send('GET', '/authorized_payments/' . rawurlencode($authorizedPaymentId));
    }

    /**
     * @return array<string, mixed> `{ results: list<array<string,mixed>>, paging: array<string,mixed> }`
     */
    public function searchAuthorizedPayments(string $preapprovalId): array
    {
        return $this->send('GET', '/authorized_payments/search', null, [], [
            'preapproval_id' => $preapprovalId,
        ]);
    }

    /** @return array<string, mixed> */
    public function cancelPreapproval(string $preapprovalId): array
    {
        return $this->send('PUT', '/preapproval/' . rawurlencode($preapprovalId), ['status' => 'cancelled']);
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string> $headers
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>
     */
    private function send(
        string $method,
        string $path,
        ?array $body = null,
        array $headers = [],
        array $query = []
    ): array {
        try {
            $response = $this->client->request($method, $path, $body, $headers, $query);
        } catch (HttpApiException $e) {
            if ($e->statusCode() === 0) {
                throw new \RuntimeException('No se pudo conectar con Mercado Pago.', 0, $e);
            }

            throw $e;
        }

        return $response->body();
    }
}
