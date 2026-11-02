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
        // AMB: chat 0.0048 + motivos 0.0014 + captura 0.0006 + dictado 0.0025 = 0.0093
        $this->assertSame(0.0093, PricingPesByEncounterClassMetadata::unitCogsPerEncounter(false, false, 'AMB'));
        $this->assertSame(0.0093, PricingPesByEncounterClassMetadata::unitCogsPerEncounter(true, false, 'AMB'));
        // AMB + video: 0.0093 + 0.0044 = 0.0137
        $this->assertSame(0.0137, PricingPesByEncounterClassMetadata::unitCogsPerEncounter(false, true, 'AMB'));
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
        // 20.000 AMB, tramo grande: unit 0.0218 × 20000 = 436
        $total = PricingPesByEncounterClassMetadata::estimateMonthlyTotal(
            ['AMB' => 20000],
            true,
            false
        );
        $this->assertSame(436.0, $total);
        $quote = PricingPesByEncounterClassMetadata::estimateMonthlyQuote(['AMB' => 20000], true, false);
        $this->assertSame(620.0, $quote['list_amount_usd']);
        $this->assertSame(184.0, $quote['discount_amount_usd']);
        $this->assertSame(29.7, $quote['discount_percent']);
    }

    public function testOneProfessionalPrice(): void
    {
        // 1 profesional = 200 atenciones → ~USD 6,20
        $quote = PricingPesByEncounterClassMetadata::estimateMonthlyQuote(
            ['AMB' => 200],
            true,
            false
        );
        $this->assertSame(6.2, $quote['amount_usd']);
        $this->assertSame(200, $quote['total_attentions']);
        $this->assertSame([
            'code' => 'AMB',
            'attentions_per_month' => 200,
            'unit_amount_usd' => 0.031,
            'amount_usd' => 6.2,
        ], $quote['items'][0]);
        $this->assertSame(6.2, $quote['list_amount_usd']);
        $this->assertSame(0.0, $quote['discount_amount_usd']);
        $this->assertSame(0.0, $quote['discount_percent']);
        $this->assertSame(200.0, PricingPesByEncounterClassMetadata::referenceEncountersPerMonth());
    }

    public function testDefaultWhenEmptyAllowAll(): void
    {
        $this->assertTrue(PricingPesByEncounterClassMetadata::defaultWhenEmptyAllowAll());
    }
}
