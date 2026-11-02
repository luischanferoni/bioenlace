<?php

namespace common\tests\unit\organization;

use common\components\Domain\Organization\Efector\Application\UseCase\SignUpInstitutionalEfector;
use common\components\Domain\Organization\Efector\Infrastructure\External\Billing\SimulatedPaymentGateway;
use Codeception\Test\Unit;

class InstitutionalSignupBillingTest extends Unit
{
    public function testEstimateMonthlyUsdAmbWithDictadoIncluded(): void
    {
        $usd = SignUpInstitutionalEfector::estimateMonthlyUsd([
            'classes' => [
                'AMB' => [
                    'attentions_per_month' => 5000,
                    'dictado_incluido' => false, // se ignora: dictado siempre incluido
                    'videollamada_permitida' => false,
                ],
            ],
        ]);
        // 5000 × 0.0093 × 2.63 (tramo mediano, redondeo unitario) = 122.50
        $this->assertSame(122.5, $usd);
    }

    public function testEstimatePlanMonthlyQuoteIncludesClassAndVolumeDiscountBreakdown(): void
    {
        $quote = SignUpInstitutionalEfector::estimatePlanMonthlyQuote([
            'classes' => [
                'AMB' => [
                    'attentions_per_month' => 20000,
                    'dictado_incluido' => true,
                    'videollamada_permitida' => false,
                ],
            ],
        ], 'CLINICA');

        $this->assertSame(436.0, $quote['amount_usd']);
        $this->assertSame(620.0, $quote['list_amount_usd']);
        $this->assertSame(184.0, $quote['discount_amount_usd']);
        $this->assertSame(29.7, $quote['discount_percent']);
        $this->assertSame(20000, $quote['total_attentions']);
        $this->assertSame('AMB', $quote['items'][0]['code']);
        $this->assertSame(20000, $quote['items'][0]['attentions_per_month']);
        $this->assertSame(0.0218, $quote['items'][0]['unit_amount_usd']);
        $this->assertSame(436.0, $quote['items'][0]['amount_usd']);
    }

    public function testSimFailPanConstant(): void
    {
        $this->assertSame('4000000000000002', SimulatedPaymentGateway::SIM_FAIL_PAN);
    }

    public function testPlanesCatalogHasSellableClasses(): void
    {
        $catalog = SignUpInstitutionalEfector::planesCatalog();
        $this->assertArrayHasKey('sellable_classes', $catalog);
        $this->assertArrayHasKey('AMB', $catalog['sellable_classes']);
        $this->assertArrayHasKey('cogs_usd_per_encounter', $catalog);
    }
}
