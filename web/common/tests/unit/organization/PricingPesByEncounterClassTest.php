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
        $this->assertTrue(PricingPesByEncounterClassMetadata::classIncludesPatientChat('AMB'));
        $this->assertFalse(PricingPesByEncounterClassMetadata::classIncludesPatientChat('EMER'));
        $this->assertTrue(PricingPesByEncounterClassMetadata::classIncludesAudio('AMB'));
        $this->assertTrue(PricingPesByEncounterClassMetadata::classIncludesAudio('EMER'));
        $this->assertTrue(PricingPesByEncounterClassMetadata::classAllowsVideollamada('AMB'));
        $this->assertFalse(PricingPesByEncounterClassMetadata::classAllowsVideollamada('EMER'));
    }

    public function testUnitCogsPerEncounter(): void
    {
        // AMB con dictado incluido: chat 0.0019 + motivos 0.0034 + captura 0.0006 + dictado 0.0025 = 0.0084
        $this->assertSame(0.0084, PricingPesByEncounterClassMetadata::unitCogsPerEncounter(false, false, 'AMB'));
        $this->assertSame(0.0084, PricingPesByEncounterClassMetadata::unitCogsPerEncounter(true, false, 'AMB'));
        // AMB + video (STT una vez): 0.0084 + 0.0088 = 0.0172
        $this->assertSame(0.0172, PricingPesByEncounterClassMetadata::unitCogsPerEncounter(false, true, 'AMB'));
        // EMER (sin chat, con dictado): 0.0034 + 0.0006 + 0.0025 = 0.0065
        $this->assertSame(0.0065, PricingPesByEncounterClassMetadata::unitCogsPerEncounter(false, false, 'EMER'));
    }

    public function testVolumeDiscountTiersByAttentions(): void
    {
        $this->assertSame('lista', PricingPesByEncounterClassMetadata::tierForTotalAttentions(200)['id']);
        $this->assertSame('lista', PricingPesByEncounterClassMetadata::tierForTotalAttentions(1000)['id']);
        $this->assertSame('mediano', PricingPesByEncounterClassMetadata::tierForTotalAttentions(5000)['id']);
        $this->assertSame('grande', PricingPesByEncounterClassMetadata::tierForTotalAttentions(20000)['id']);
        $this->assertSame('enterprise', PricingPesByEncounterClassMetadata::tierForTotalAttentions(40000)['id']);
        $this->assertSame(134.0, PricingPesByEncounterClassMetadata::marginOnCostPercentForTotalAttentions(20000));
    }

    public function testEstimateMonthlyTotalByAttentions(): void
    {
        // 20.000 AMB + dictado, tramo grande: unit ~0.0197 × 20000 = 394
        $total = PricingPesByEncounterClassMetadata::estimateMonthlyTotal(
            ['AMB' => 20000],
            true,
            false
        );
        $this->assertSame(394.0, $total);
    }

    public function testIndependentWorkerNearPreviousPesPrice(): void
    {
        // Consultorio 200 atenciones ≈ ~USD 5,60 (antes ~USD 6 por 1 PES)
        $total = PricingPesByEncounterClassMetadata::estimateMonthlyTotal(
            ['AMB' => 200],
            true,
            false
        );
        $this->assertGreaterThan(5.0, $total);
        $this->assertLessThan(7.0, $total);
    }

    public function testDefaultWhenEmptyAllowAll(): void
    {
        $this->assertTrue(PricingPesByEncounterClassMetadata::defaultWhenEmptyAllowAll());
    }
}
