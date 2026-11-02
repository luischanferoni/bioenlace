<?php

namespace common\tests\unit\organization;

use common\components\Domain\Organization\Billing\Application\Service\MercadoPagoCheckoutService;
use common\components\Domain\Organization\Billing\Application\Service\BillingCheckoutService;
use Codeception\Test\Unit;

class MercadoPagoCheckoutServiceTest extends Unit
{
    public function testPaidThroughClampsToLastDayOfFollowingMonth(): void
    {
        $originalTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        try {
            $this->assertSame(
                '2024-02-29 12:30:00',
                BillingCheckoutService::paidThroughAt('2024-01-31T12:30:00+00:00')
            );
            $this->assertSame(
                '2024-09-30 12:30:00',
                BillingCheckoutService::paidThroughAt('2024-08-31T12:30:00+00:00')
            );
        } finally {
            date_default_timezone_set($originalTimezone);
        }
    }

    public function testCalculatesMonthlyArsSnapshotWithVat(): void
    {
        $service = new MercadoPagoCheckoutService([
            'accessToken' => 'test-token',
            'usdArsRate' => 1000,
            'usdArsRateEffectiveDate' => date('Y-m-d'),
            'vatRate' => 0.21,
        ]);

        $snapshot = $service->calculateArsAmount(10);

        $this->assertSame(10000.0, $snapshot['net_ars']);
        $this->assertSame(2100.0, $snapshot['vat_ars']);
        $this->assertSame(12100.0, $snapshot['total_ars']);
    }

    public function testRejectsStaleExchangeRate(): void
    {
        $service = new MercadoPagoCheckoutService([
            'accessToken' => 'test-token',
            'usdArsRate' => 1000,
            'usdArsRateEffectiveDate' => date('Y-m-d', strtotime('-101 days')),
            'vatRate' => 0.21,
        ]);

        $this->expectException(\yii\base\InvalidConfigException::class);
        $service->calculateArsAmount(10);
    }

    public function testValidatesMercadoPagoWebhookManifestSignature(): void
    {
        $secret = 'webhook-secret';
        $requestId = 'request-123';
        $timestamp = (string) time();
        $payload = ['data' => ['id' => 'A1B2C3']];
        $manifest = 'id:a1b2c3;request-id:' . $requestId . ';ts:' . $timestamp . ';';
        $signature = hash_hmac('sha256', $manifest, $secret);
        $service = new MercadoPagoCheckoutService([
            'accessToken' => 'test-token',
            'webhookSecret' => $secret,
        ]);

        $this->assertTrue(
            $service->validateWebhookSignature($payload, 'ts=' . $timestamp . ',v1=' . $signature, $requestId)
        );
        $this->assertFalse(
            $service->validateWebhookSignature($payload, 'ts=' . $timestamp . ',v1=' . str_repeat('0', 64), $requestId)
        );
    }
}
