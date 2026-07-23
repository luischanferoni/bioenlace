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
        // AMB: chat 0.0019 + motivos 0.0014 + captura 0.0006 + dictado 0.0025 = 0.0064
        $this->assertSame(0.0064, PricingPesByEncounterClassMetadata::unitCogsPerEncounter(false, false, 'AMB'));
        $this->assertSame(0.0064, PricingPesByEncounterClassMetadata::unitCogsPerEncounter(true, false, 'AMB'));
        // AMB + video @ 40% tele: 0.0064 + 0.0044 = 0.0108
        $this->assertSame(0.0108, PricingPesByEncounterClassMetadata::unitCogsPerEncounter(false, true, 'AMB'));
        // EMER: motivos 0.0018 (45%) + captura 0.0006 + dictado 0.0025 = 0.0049
        $this->assertSame(0.0049, PricingPesByEncounterClassMetadata::unitCogsPerEncounter(false, false, 'EMER'));
        // IMP: motivos 0.0019 (50%) + captura 0.0006 + dictado 0.0025 = 0.0050
        $this->assertSame(0.0050, PricingPesByEncounterClassMetadata::unitCogsPerEncounter(false, false, 'IMP'));
        $this->assertSame(0.0014, PricingPesByEncounterClassMetadata::motivosAudioCogsPerEncounter('AMB'));
        $this->assertSame(0.0018, PricingPesByEncounterClassMetadata::motivosAudioCogsPerEncounter('EMER'));
        $this->assertSame(0.0019, PricingPesByEncounterClassMetadata::motivosAudioCogsPerEncounter('IMP'));
    }

    public function testVolumeDiscountTiersByAttentions(): void
    {
        $this->assertSame('lista', PricingPesByEncounterClassMetadata::tierForTotalAttentions(200)['id']);
        $this->assertSame('mediano', PricingPesByEncounterClassMetadata::tierForTotalAttentions(5000)['id']);
        $this->assertSame('grande', PricingPesByEncounterClassMetadata::tierForTotalAttentions(20000)['id']);
        $this->assertSame('enterprise', PricingPesByEncounterClassMetadata::tierForTotalAttentions(40000)['id']);
        $this->assertSame(134.0, PricingPesByEncounterClassMetadata::marginOnCostPercentForTotalAttentions(20000));
    }

    public function testEstimateMonthlyTotalByAttentions(): void
    {
        // 20.000 AMB, tramo grande: unit 0.0150 × 20000 = 300
        $total = PricingPesByEncounterClassMetadata::estimateMonthlyTotal(
            ['AMB' => 20000],
            true,
            false
        );
        $this->assertSame(300.0, $total);
    }

    public function testOneProfessionalPrice(): void
    {
        // 1 profesional = 200 atenciones → ~USD 4,26
        $total = PricingPesByEncounterClassMetadata::estimateMonthlyTotal(
            ['AMB' => 200],
            true,
            false
        );
        $this->assertSame(4.26, $total);
        $this->assertSame(200.0, PricingPesByEncounterClassMetadata::referenceEncountersPerMonth());
    }

    public function testDefaultWhenEmptyAllowAll(): void
    {
        $this->assertTrue(PricingPesByEncounterClassMetadata::defaultWhenEmptyAllowAll());
    }
}
