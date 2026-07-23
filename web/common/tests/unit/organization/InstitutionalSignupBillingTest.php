<?php

namespace common\tests\unit\organization;

use common\components\Domain\Organization\Service\Billing\InstitutionalEfectorSignupService;
use common\components\Domain\Organization\Service\Billing\SimulatedPaymentGateway;
use Codeception\Test\Unit;

class InstitutionalSignupBillingTest extends Unit
{
    public function testEstimateMonthlyUsdAmbBasePositive(): void
    {
        $usd = InstitutionalEfectorSignupService::estimateMonthlyUsd([
            'classes' => [
                'AMB' => [
                    'attentions_per_month' => 5000,
                    'dictado_incluido' => false,
                    'videollamada_permitida' => false,
                ],
            ],
        ]);
        // 5000 × 0.0059 × 2.63 (tramo mediano) ≈ 77.59
        $this->assertGreaterThan(70.0, $usd);
        $this->assertLessThan(90.0, $usd);
    }

    public function testSimFailPanConstant(): void
    {
        $this->assertSame('4000000000000002', SimulatedPaymentGateway::SIM_FAIL_PAN);
    }

    public function testPlanesCatalogHasSellableClasses(): void
    {
        $catalog = InstitutionalEfectorSignupService::planesCatalog();
        $this->assertArrayHasKey('sellable_classes', $catalog);
        $this->assertArrayHasKey('AMB', $catalog['sellable_classes']);
        $this->assertArrayHasKey('cogs_usd_per_encounter', $catalog);
    }
}
