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
                    'max_pes' => 10,
                    'dictado_incluido' => false,
                    'videollamada_permitida' => false,
                ],
            ],
        ]);
        $this->assertGreaterThan(20.0, $usd);
        $this->assertLessThan(40.0, $usd);
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
    }
}
