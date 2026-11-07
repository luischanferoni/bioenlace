<?php

namespace common\components\Domain\Organization\Billing\Application\Service;

use common\components\Domain\Organization\Billing\Infrastructure\External\MercadoPago\MercadoPagoApiClient;
use Yii;
use yii\base\InvalidConfigException;

final class MercadoPagoCheckoutService
{
    /** @var array<string, mixed> */
    private array $config;
    private bool $useExchangeRateOverride;
    private ?MercadoPagoApiClient $apiClient;

    /**
     * @param array<string, mixed>|null $config
     */
    public function __construct(?array $config = null, ?MercadoPagoApiClient $apiClient = null)
    {
        $this->config = $config ?? (Yii::$app->params['mercadoPago'] ?? []);
        $this->apiClient = $apiClient;
        $this->useExchangeRateOverride = $config !== null
            && array_key_exists('usdArsRate', $config)
            && array_key_exists('usdArsRateEffectiveDate', $config);
    }

    /**
     * Calcula el snapshot del precio comercial. La cotización se configura y
     * actualiza trimestralmente; no se consulta una cotización externa durante el checkout.
     *
     * @return array{amount_usd: float, exchange_rate: float, exchange_rate_effective_date: string, net_ars: float, vat_rate: float, vat_ars: float, total_ars: float}
     */
    public function calculateArsAmount(float $amountUsd): array
    {
        $vatRate = (float) ($this->config['vatRate'] ?? 0.21);
        if ($this->useExchangeRateOverride) {
            $exchangeRate = (float) ($this->config['usdArsRate'] ?? 0);
            $effectiveDate = trim((string) ($this->config['usdArsRateEffectiveDate'] ?? ''));
        } else {
            $exchangeRateSnapshot = (new BillingExchangeRateService())->current();
            $exchangeRate = $exchangeRateSnapshot['rate'];
            $effectiveDate = $exchangeRateSnapshot['effective_date'];
        }
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
     * } $data `payer_email` vacío omite el pagador y el checkout pide login.
     * @return array{id: string, init_point: string, status: string|null}
     */
    public function createSubscription(array $data): array
    {
        if ((float) $data['amount_ars'] <= 0 || !is_finite((float) $data['amount_ars'])) {
            throw new \InvalidArgumentException('El importe de la suscripción debe ser mayor que cero.');
        }
        $payerEmail = trim((string) ($data['payer_email'] ?? ''));
        if ($payerEmail !== '' && !filter_var($payerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Se requiere un e-mail válido para crear la suscripción.');
        }
        foreach (['back_url', 'notification_url'] as $urlKey) {
            if (!filter_var($data[$urlKey], FILTER_VALIDATE_URL)
                || parse_url($data[$urlKey], PHP_URL_SCHEME) !== 'https') {
                throw new \InvalidArgumentException('Las URLs de retorno y notificación deben ser HTTPS.');
            }
        }

        $payload = [
            'reason' => $data['reason'],
            'external_reference' => $data['external_reference'],
            'back_url' => $data['back_url'],
            'notification_url' => $data['notification_url'],
            'auto_recurring' => [
                'frequency' => 1,
                'frequency_type' => 'months',
                'transaction_amount' => round((float) $data['amount_ars'], 2),
                'currency_id' => 'ARS',
            ],
            'status' => 'pending',
        ];
        // Sin payer_email, Mercado Pago pide el login del pagador en el checkout
        // (útil en sandbox cuando solo se tiene el usuario de prueba).
        if ($payerEmail !== '') {
            $payload['payer_email'] = $payerEmail;
        }

        $response = $this->apiClient()->createPreapproval($payload, $data['idempotency_key']);

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
        return $this->apiClient()->getPreapproval($subscriptionId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAuthorizedPayment(string $authorizedPaymentId): array
    {
        return $this->apiClient()->getAuthorizedPayment($authorizedPaymentId);
    }

    /**
     * @return array<string, mixed>
     */
    public function searchAuthorizedPayments(string $preapprovalId): array
    {
        return $this->apiClient()->searchAuthorizedPayments($preapprovalId);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->apiClient()->cancelPreapproval($subscriptionId);
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
     * @throws InvalidConfigException si falta el access token
     */
    private function apiClient(): MercadoPagoApiClient
    {
        if ($this->apiClient === null) {
            $accessToken = trim((string) ($this->config['accessToken'] ?? ''));
            if ($accessToken === '') {
                throw new InvalidConfigException('Mercado Pago access token no configurado.');
            }
            $this->apiClient = new MercadoPagoApiClient($accessToken);
        }

        return $this->apiClient;
    }
}
