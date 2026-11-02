<?php

namespace common\components\Domain\Organization\Billing\Application\Service;

use Yii;
use yii\base\InvalidConfigException;
use yii\httpclient\Client;
use yii\httpclient\CurlTransport;

final class MercadoPagoCheckoutService
{
    private const API_URL = 'https://api.mercadopago.com';

    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed>|null $config
     */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (Yii::$app->params['mercadoPago'] ?? []);
        if (trim((string) ($this->config['accessToken'] ?? '')) === '') {
            throw new InvalidConfigException('Mercado Pago access token no configurado.');
        }
    }

    /**
     * Calcula el snapshot del precio comercial. La cotización se configura y
     * actualiza trimestralmente; no se consulta una cotización externa durante el checkout.
     *
     * @return array{amount_usd: float, exchange_rate: float, exchange_rate_effective_date: string, net_ars: float, vat_rate: float, vat_ars: float, total_ars: float}
     */
    public function calculateArsAmount(float $amountUsd): array
    {
        $exchangeRate = (float) ($this->config['usdArsRate'] ?? 0);
        $vatRate = (float) ($this->config['vatRate'] ?? 0.21);
        $effectiveDate = trim((string) ($this->config['usdArsRateEffectiveDate'] ?? ''));
        if ($amountUsd <= 0 || !is_finite($amountUsd)) {
            throw new \InvalidArgumentException('El importe mensual debe ser mayor que cero.');
        }
        if ($exchangeRate <= 0 || !is_finite($exchangeRate)) {
            throw new InvalidConfigException('Cotización MEP vendedora USD/ARS no configurada.');
        }
        $effectiveTimestamp = $effectiveDate !== '' ? strtotime($effectiveDate) : false;
        if ($effectiveTimestamp === false || date('Y-m-d', $effectiveTimestamp) !== $effectiveDate) {
            throw new InvalidConfigException('Fecha de vigencia de la cotización MEP no configurada o inválida.');
        }
        $ageDays = (int) floor((strtotime(date('Y-m-d')) - $effectiveTimestamp) / 86400);
        if ($ageDays < 0 || $ageDays > 100) {
            throw new InvalidConfigException('La cotización MEP está vencida; debe revisarse trimestralmente.');
        }
        if ($vatRate < 0 || $vatRate > 1 || !is_finite($vatRate)) {
            throw new InvalidConfigException('Alícuota de IVA de Mercado Pago inválida.');
        }

        $netArs = round($amountUsd * $exchangeRate, 2);
        $vatArs = round($netArs * $vatRate, 2);

        return [
            'amount_usd' => round($amountUsd, 2),
            'exchange_rate' => $exchangeRate,
            'exchange_rate_effective_date' => $effectiveDate,
            'net_ars' => $netArs,
            'vat_rate' => $vatRate,
            'vat_ars' => $vatArs,
            'total_ars' => round($netArs + $vatArs, 2),
        ];
    }

    /**
     * Crea una suscripción mensual con Checkout Pro.
     *
     * @param array{
     *   amount_ars: float,
     *   payer_email: string,
     *   reason: string,
     *   external_reference: string,
     *   back_url: string,
     *   notification_url: string,
     *   idempotency_key: string
     * } $data
     * @return array{id: string, init_point: string, status: string|null}
     */
    public function createSubscription(array $data): array
    {
        if ((float) $data['amount_ars'] <= 0 || !is_finite((float) $data['amount_ars'])) {
            throw new \InvalidArgumentException('El importe de la suscripción debe ser mayor que cero.');
        }
        if (!filter_var($data['payer_email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Se requiere un e-mail válido para crear la suscripción.');
        }
        foreach (['back_url', 'notification_url'] as $urlKey) {
            if (!filter_var($data[$urlKey], FILTER_VALIDATE_URL)
                || parse_url($data[$urlKey], PHP_URL_SCHEME) !== 'https') {
                throw new \InvalidArgumentException('Las URLs de retorno y notificación deben ser HTTPS.');
            }
        }

        $response = $this->request('POST', '/preapproval', [
            'reason' => $data['reason'],
            'external_reference' => $data['external_reference'],
            'payer_email' => $data['payer_email'],
            'back_url' => $data['back_url'],
            'notification_url' => $data['notification_url'],
            'auto_recurring' => [
                'frequency' => 1,
                'frequency_type' => 'months',
                'transaction_amount' => round((float) $data['amount_ars'], 2),
                'currency_id' => 'ARS',
            ],
            'status' => 'pending',
        ], $data['idempotency_key']);

        $id = trim((string) ($response['id'] ?? ''));
        $initPoint = trim((string) ($response['init_point'] ?? ''));
        if ($id === '' || !filter_var($initPoint, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('Mercado Pago no devolvió una suscripción y URL de checkout válidas.');
        }

        return [
            'id' => $id,
            'init_point' => $initPoint,
            'status' => isset($response['status']) ? (string) $response['status'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscription(string $subscriptionId): array
    {
        return $this->request('GET', '/preapproval/' . rawurlencode($subscriptionId));
    }

    /**
     * @return array<string, mixed>
     */
    public function getAuthorizedPayment(string $authorizedPaymentId): array
    {
        return $this->request('GET', '/authorized_payments/' . rawurlencode($authorizedPaymentId));
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->request('PUT', '/preapproval/' . rawurlencode($subscriptionId), [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Valida X-Signature según el manifest de Webhooks de Mercado Pago.
     *
     * @param array<string, mixed> $payload
     */
    public function validateWebhookSignature(
        array $payload,
        ?string $signatureHeader,
        ?string $requestId
    ): bool {
        $secret = trim((string) ($this->config['webhookSecret'] ?? ''));
        if ($secret === '' || $signatureHeader === null || $requestId === null || trim($requestId) === '') {
            return false;
        }

        $resourceId = strtolower(trim((string) ($payload['data']['id'] ?? '')));
        if ($resourceId === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }
        $timestamp = $parts['ts'] ?? '';
        $signature = $parts['v1'] ?? '';
        if (!ctype_digit($timestamp) || !preg_match('/^[a-f0-9]{64}$/i', $signature)) {
            return false;
        }

        $manifest = 'id:' . $resourceId . ';request-id:' . trim($requestId) . ';ts:' . $timestamp . ';';
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, strtolower($signature));
    }

    /**
     * @param array<string, mixed>|null $data
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $data = null, ?string $idempotencyKey = null): array
    {
        $client = new Client([
            'baseUrl' => self::API_URL,
            'transport' => CurlTransport::class,
        ]);
        $request = $client->createRequest()
            ->setMethod($method)
            ->setUrl($path)
            ->setHeaders([
                'Authorization' => 'Bearer ' . trim((string) $this->config['accessToken']),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->setOptions([
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 20,
            ]);
        if ($idempotencyKey !== null) {
            $request->addHeaders(['X-Idempotency-Key' => $idempotencyKey]);
        }
        if ($data !== null) {
            $request->setFormat(Client::FORMAT_JSON)->setData($data);
        }

        $response = $request->send();
        $body = json_decode($response->content, true);
        if (!$response->isOk() || !is_array($body)) {
            $statusCode = $response->getStatusCode();
            Yii::error([
                'event' => 'mercadopago.api_error',
                'method' => $method,
                'path' => $path,
                'status_code' => $statusCode,
            ], 'mercadopago');
            throw new \RuntimeException('Mercado Pago no pudo completar la operación (HTTP ' . $statusCode . ').');
        }

        return $body;
    }
}
