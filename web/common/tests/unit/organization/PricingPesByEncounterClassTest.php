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
        // AMB sin add-ons: 0.96 × 3.33 = 3.20
        $this->assertSame(3.2, PricingPesByEncounterClassMetadata::unitPrice(false, false, 'AMB'));
        // AMB + audio: 1.24 × 3.33 = 4.13
        $this->assertSame(4.13, PricingPesByEncounterClassMetadata::unitPrice(true, false, 'AMB'));
        // EMER (audio incluido, vol 350/400): 1.24 × 0.875 × 3.33 = 3.61
        $this->assertSame(3.61, PricingPesByEncounterClassMetadata::pricePerPes('EMER'));
        // IMP (audio incluido, vol 300/400): 1.24 × 0.75 × 3.33 = 3.10
        $this->assertSame(3.1, PricingPesByEncounterClassMetadata::pricePerPes('IMP'));
        // Video no aplica a EMER aunque se pida
        $this->assertSame(3.61, PricingPesByEncounterClassMetadata::unitPrice(false, true, 'EMER'));
        $this->assertNull(PricingPesByEncounterClassMetadata::pricePerPes('HH'));
    }

    public function testEstimateMonthlyTotal(): void
    {
        $total = PricingPesByEncounterClassMetadata::estimateMonthlyTotal([
            'AMB' => 10,
            'EMER' => 4,
        ]);
        // 10 × 3.20 + 4 × 3.61 = 32 + 14.44 = 46.44
        $this->assertSame(46.44, $total);

        $withVideo = PricingPesByEncounterClassMetadata::estimateMonthlyTotal(
            ['AMB' => 10, 'IMP' => 2],
            false,
            true
        );
        // AMB+video: 12.48 × 3.33 = 41.56 × 10; IMP: 3.10 × 2
        $this->assertSame(421.8, $withVideo);
    }

    public function testDefaultWhenEmptyAllowAll(): void
    {
        $this->assertTrue(PricingPesByEncounterClassMetadata::defaultWhenEmptyAllowAll());
    }
}
