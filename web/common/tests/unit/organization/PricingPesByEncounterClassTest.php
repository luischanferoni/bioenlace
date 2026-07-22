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

    public function testUnitPricesListTier(): void
    {
        // Lista (1–19): markup 233 % → ×3,33
        // AMB sin add-ons: 0,95 × 3,33 = 3,16
        $this->assertSame(3.16, PricingPesByEncounterClassMetadata::unitPrice(false, false, 'AMB'));
        // AMB + audio: 1,93 × 3,33 = 6,43
        $this->assertSame(6.43, PricingPesByEncounterClassMetadata::unitPrice(true, false, 'AMB'));
        // AMB + video (STT una sola vez): 5,43 × 3,33 = 18,08
        $this->assertSame(18.08, PricingPesByEncounterClassMetadata::unitPrice(false, true, 'AMB'));
        $this->assertSame(18.08, PricingPesByEncounterClassMetadata::unitPrice(true, true, 'AMB'));
        // EMER (audio incluido, vol 350/400): 1,93 × 0,875 × 3,33 = 5,62
        $this->assertSame(5.62, PricingPesByEncounterClassMetadata::pricePerPes('EMER'));
        // IMP (audio incluido, vol 300/400): 1,93 × 0,75 × 3,33 = 4,82
        $this->assertSame(4.82, PricingPesByEncounterClassMetadata::pricePerPes('IMP'));
        // Video no aplica a EMER aunque se pida
        $this->assertSame(5.62, PricingPesByEncounterClassMetadata::unitPrice(false, true, 'EMER'));
        $this->assertNull(PricingPesByEncounterClassMetadata::pricePerPes('HH'));
    }

    public function testVolumeDiscountTiers(): void
    {
        $this->assertSame('lista', PricingPesByEncounterClassMetadata::tierForTotalPes(1)['id']);
        $this->assertSame('lista', PricingPesByEncounterClassMetadata::tierForTotalPes(19)['id']);
        $this->assertSame('mediano', PricingPesByEncounterClassMetadata::tierForTotalPes(20)['id']);
        $this->assertSame('mediano', PricingPesByEncounterClassMetadata::tierForTotalPes(49)['id']);
        $this->assertSame('grande', PricingPesByEncounterClassMetadata::tierForTotalPes(50)['id']);
        $this->assertSame('grande', PricingPesByEncounterClassMetadata::tierForTotalPes(149)['id']);
        $this->assertSame('enterprise', PricingPesByEncounterClassMetadata::tierForTotalPes(150)['id']);
        $this->assertSame(233.0, PricingPesByEncounterClassMetadata::marginOnCostPercentForTotalPes(10));
        $this->assertSame(163.0, PricingPesByEncounterClassMetadata::marginOnCostPercentForTotalPes(25));
        $this->assertSame(134.0, PricingPesByEncounterClassMetadata::marginOnCostPercentForTotalPes(80));
        $this->assertSame(117.0, PricingPesByEncounterClassMetadata::marginOnCostPercentForTotalPes(200));
    }

    public function testEstimateMonthlyTotalAppliesVolumeTier(): void
    {
        // 10 AMB + 4 EMER = 14 PES → lista
        $total = PricingPesByEncounterClassMetadata::estimateMonthlyTotal([
            'AMB' => 10,
            'EMER' => 4,
        ]);
        // 10 × 3,16 + 4 × 5,62 = 31,6 + 22,48 = 54,08
        $this->assertSame(54.08, $total);

        // 20 AMB = tramo mediano (markup 163 % → ×2,63): 0,95 × 2,63 = 2,50 × 20 = 50,00
        $mediano = PricingPesByEncounterClassMetadata::estimateMonthlyTotal(['AMB' => 20]);
        $this->assertSame(50.0, $mediano);

        $withVideo = PricingPesByEncounterClassMetadata::estimateMonthlyTotal(
            ['AMB' => 10, 'IMP' => 2],
            false,
            true
        );
        // 12 PES → lista; AMB+video 18,08 × 10 + IMP 4,82 × 2 = 180,8 + 9,64 = 190,44
        $this->assertSame(190.44, $withVideo);
    }

    public function testDefaultWhenEmptyAllowAll(): void
    {
        $this->assertTrue(PricingPesByEncounterClassMetadata::defaultWhenEmptyAllowAll());
    }
}
