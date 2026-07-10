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

    public function testSellableClasses(): void
    {
        $codes = PricingPesByEncounterClassMetadata::sellableClassCodes();
        $this->assertContains('AMB', $codes);
        $this->assertContains('EMER', $codes);
        $this->assertContains('IMP', $codes);
    }

    public function testUnitCogsAndPriceWithoutAddons(): void
    {
        $this->assertSame(1.24, PricingPesByEncounterClassMetadata::unitCogs(false, false));
        // 1.24 × (1 + 2.33) = 4.1292 → 4.13
        $this->assertSame(4.13, PricingPesByEncounterClassMetadata::unitPrice(false, false));
        $this->assertSame(4.13, PricingPesByEncounterClassMetadata::pricePerPes('AMB'));
        $this->assertSame(4.13, PricingPesByEncounterClassMetadata::pricePerPes('EMER'));
        $this->assertNull(PricingPesByEncounterClassMetadata::pricePerPes('HH'));
    }

    public function testUnitCogsWithAddons(): void
    {
        $this->assertSame(1.55, PricingPesByEncounterClassMetadata::unitCogs(true, false));
        $this->assertSame(12.76, PricingPesByEncounterClassMetadata::unitCogs(false, true));
        $this->assertSame(13.07, PricingPesByEncounterClassMetadata::unitCogs(true, true));
        // 13.07 × 3.33 = 43.5231 → 43.52
        $this->assertSame(43.52, PricingPesByEncounterClassMetadata::unitPrice(true, true));
    }

    public function testEstimateMonthlyTotal(): void
    {
        $total = PricingPesByEncounterClassMetadata::estimateMonthlyTotal([
            'AMB' => 10,
            'EMER' => 4,
        ]);
        // 14 × 4.13
        $this->assertSame(57.82, $total);

        $withVideo = PricingPesByEncounterClassMetadata::estimateMonthlyTotal(
            ['AMB' => 10],
            false,
            true
        );
        // 10 × (12.76 × 3.33) = 10 × 42.49
        $this->assertSame(424.9, $withVideo);
    }

    public function testDefaultWhenEmptyAllowAll(): void
    {
        $this->assertTrue(PricingPesByEncounterClassMetadata::defaultWhenEmptyAllowAll());
    }
}
