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

    public function testSellableClassesAndFlags(): void
    {
        $codes = PricingPesByEncounterClassMetadata::sellableClassCodes();
        $this->assertContains('AMB', $codes);
        $this->assertContains('EMER', $codes);
        $this->assertContains('IMP', $codes);
        $this->assertFalse(PricingPesByEncounterClassMetadata::classIncludesAudio('AMB'));
        $this->assertTrue(PricingPesByEncounterClassMetadata::classIncludesAudio('EMER'));
        $this->assertTrue(PricingPesByEncounterClassMetadata::classIncludesAudio('IMP'));
        $this->assertTrue(PricingPesByEncounterClassMetadata::classAllowsVideollamada('AMB'));
        $this->assertFalse(PricingPesByEncounterClassMetadata::classAllowsVideollamada('EMER'));
        $this->assertFalse(PricingPesByEncounterClassMetadata::classAllowsVideollamada('IMP'));
    }

    public function testVolumes(): void
    {
        $this->assertSame(400.0, PricingPesByEncounterClassMetadata::encountersPerMonth('AMB'));
        $this->assertSame(350.0, PricingPesByEncounterClassMetadata::encountersPerMonth('EMER'));
        $this->assertSame(300.0, PricingPesByEncounterClassMetadata::encountersPerMonth('IMP'));
    }

    public function testUnitPrices(): void
    {
        // AMB sin add-ons: 0.83 × 3.33 = 2.76 (COGS con context caching)
        $this->assertSame(2.76, PricingPesByEncounterClassMetadata::unitPrice(false, false, 'AMB'));
        // AMB + audio: 1.11 × 3.33 = 3.70
        $this->assertSame(3.7, PricingPesByEncounterClassMetadata::unitPrice(true, false, 'AMB'));
        // EMER (audio incluido, vol 350/400): 1.11 × 0.875 × 3.33 = 3.23
        $this->assertSame(3.23, PricingPesByEncounterClassMetadata::pricePerPes('EMER'));
        // IMP (audio incluido, vol 300/400): 1.11 × 0.75 × 3.33 = 2.77
        $this->assertSame(2.77, PricingPesByEncounterClassMetadata::pricePerPes('IMP'));
        // Video no aplica a EMER aunque se pida
        $this->assertSame(3.23, PricingPesByEncounterClassMetadata::unitPrice(false, true, 'EMER'));
        $this->assertNull(PricingPesByEncounterClassMetadata::pricePerPes('HH'));
    }

    public function testEstimateMonthlyTotal(): void
    {
        $total = PricingPesByEncounterClassMetadata::estimateMonthlyTotal([
            'AMB' => 10,
            'EMER' => 4,
        ]);
        // 10 × 2.76 + 4 × 3.23 = 27.6 + 12.92 = 40.52
        $this->assertSame(40.52, $total);

        $withVideo = PricingPesByEncounterClassMetadata::estimateMonthlyTotal(
            ['AMB' => 10, 'IMP' => 2],
            false,
            true
        );
        // AMB+video: 12.35 × 3.33 = 41.13 × 10; IMP: 2.77 × 2
        $this->assertSame(416.84, $withVideo);
    }

    public function testDefaultWhenEmptyAllowAll(): void
    {
        $this->assertTrue(PricingPesByEncounterClassMetadata::defaultWhenEmptyAllowAll());
    }
}
