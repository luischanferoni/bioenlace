<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Platform\Core\Product\PricingPesByEncounterClassMetadata;

class PricingPesByEncounterClassTest extends Unit
{
    protected function _before(): void
    {
        PricingPesByEncounterClassMetadata::reset();
    }

    public function testSellableClassesAndPrices(): void
    {
        $codes = PricingPesByEncounterClassMetadata::sellableClassCodes();
        $this->assertContains('AMB', $codes);
        $this->assertContains('EMER', $codes);
        $this->assertContains('IMP', $codes);
        $this->assertSame(18.0, PricingPesByEncounterClassMetadata::pricePerPes('AMB'));
        $this->assertSame(55.0, PricingPesByEncounterClassMetadata::pricePerPes('EMER'));
        $this->assertSame(42.0, PricingPesByEncounterClassMetadata::pricePerPes('IMP'));
    }

    public function testEstimateMonthlyTotal(): void
    {
        $total = PricingPesByEncounterClassMetadata::estimateMonthlyTotal([
            'AMB' => 10,
            'EMER' => 4,
        ]);
        $this->assertSame(400.0, $total);
    }

    public function testDefaultWhenEmptyAllowAll(): void
    {
        $this->assertTrue(PricingPesByEncounterClassMetadata::defaultWhenEmptyAllowAll());
    }
}
