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
        // AMB: chat 0.0048 + motivos 0.0006 + captura 0.0006 + dictado 0.0025 = 0.0085
        $this->assertSame(0.0085, PricingPesByEncounterClassMetadata::unitCogsPerEncounter(false, false, 'AMB'));
        $this->assertSame(0.0085, PricingPesByEncounterClassMetadata::unitCogsPerEncounter(true, false, 'AMB'));
        // AMB + video: 0.0085 + 0.0044 = 0.0129
        $this->assertSame(0.0129, PricingPesByEncounterClassMetadata::unitCogsPerEncounter(false, true, 'AMB'));
        // EMER: motivos 0.0006 + captura 0.0006 + dictado 0.0025 = 0.0037
        $this->assertSame(0.0037, PricingPesByEncounterClassMetadata::unitCogsPerEncounter(false, false, 'EMER'));
        // IMP: mismo costo, sin audio en motivos
        $this->assertSame(0.0037, PricingPesByEncounterClassMetadata::unitCogsPerEncounter(false, false, 'IMP'));
        $this->assertSame(0.0006, PricingPesByEncounterClassMetadata::motivosCogsPerEncounter('AMB'));
        $this->assertSame(0.0006, PricingPesByEncounterClassMetadata::motivosCogsPerEncounter('EMER'));
        $this->assertSame(0.0006, PricingPesByEncounterClassMetadata::motivosCogsPerEncounter('IMP'));
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
        // 20.000 AMB, tramo grande: unit 0.0199 × 20000 = 398
        $total = PricingPesByEncounterClassMetadata::estimateMonthlyTotal(
            ['AMB' => 20000],
            true,
            false
        );
        $this->assertSame(398.0, $total);
        $quote = PricingPesByEncounterClassMetadata::estimateMonthlyQuote(['AMB' => 20000], true, false);
        $this->assertSame(566.0, $quote['list_amount_usd']);
        $this->assertSame(168.0, $quote['discount_amount_usd']);
        $this->assertSame(29.7, $quote['discount_percent']);
    }

    public function testFullTimeProfessionalPrice(): void
    {
        // Jornada completa = 500 atenciones → ~USD 14,15
        $quote = PricingPesByEncounterClassMetadata::estimateMonthlyQuote(
            ['AMB' => 500],
            true,
            false
        );
        $this->assertSame(14.15, $quote['amount_usd']);
        $this->assertSame(500, $quote['total_attentions']);
        $this->assertSame([
            'code' => 'AMB',
            'attentions_per_month' => 500,
            'unit_amount_usd' => 0.0283,
            'amount_usd' => 14.15,
        ], $quote['items'][0]);
        $this->assertSame(14.15, $quote['list_amount_usd']);
        $this->assertSame(0.0, $quote['discount_amount_usd']);
        $this->assertSame(0.0, $quote['discount_percent']);
        $this->assertSame(500.0, PricingPesByEncounterClassMetadata::referenceEncountersPerMonth());
        $this->assertSame(700.0, PricingPesByEncounterClassMetadata::referenceEncountersPerMonth('EMER'));
        $this->assertSame(400.0, PricingPesByEncounterClassMetadata::referenceEncountersPerMonth('IMP'));
    }

    public function testHalfTimeProfessionalPrice(): void
    {
        // Media jornada = 250 atenciones → ~USD 7,08
        $quote = PricingPesByEncounterClassMetadata::estimateMonthlyQuote(
            ['AMB' => 250],
            true,
            false
        );
        $this->assertSame(7.08, $quote['amount_usd']);
        $this->assertSame(250, $quote['total_attentions']);
        $this->assertSame(0.0283, $quote['items'][0]['unit_amount_usd']);
    }

    public function testDictadoMinutesDriveSttCogs(): void
    {
        $this->assertSame(5.0, PricingPesByEncounterClassMetadata::dictadoMinutes());
        $this->assertSame(0.0025, PricingPesByEncounterClassMetadata::dictadoCogsPerEncounter());
    }

    public function testDefaultWhenEmptyAllowAll(): void
    {
        $this->assertTrue(PricingPesByEncounterClassMetadata::defaultWhenEmptyAllowAll());
    }
}
