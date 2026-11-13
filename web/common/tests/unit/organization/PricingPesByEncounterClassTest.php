<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Organization\Pes\Domain\Catalog\PricingPesByEncounterClassCatalog;
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
        // AMB: chat 0.0048 + motivos 0.0006 + captura 0.0007 + dictado 0.0025 + infra 0.00006 = 0.00866
        $this->assertSame(0.00866, PricingPesByEncounterClassMetadata::unitCogsPerEncounter([], 'AMB'));
        // AMB + video (addon opcional elegido): 0.00866 + 0.0044 = 0.01306
        $this->assertSame(
            0.01306,
            PricingPesByEncounterClassMetadata::unitCogsPerEncounter(['videollamada' => true], 'AMB')
        );
        // EMER: motivos 0.0006 + captura 0.0007 + dictado 0.0025 + infra (0.03/700) = 0.003843
        $this->assertSame(0.003843, PricingPesByEncounterClassMetadata::unitCogsPerEncounter([], 'EMER'));
        // IMP: mismo costo + infra (0.03/400)
        $this->assertSame(0.003875, PricingPesByEncounterClassMetadata::unitCogsPerEncounter([], 'IMP'));
        // La videollamada no aplica a EMER: elegirla no mueve el COGS.
        $this->assertSame(
            0.003843,
            PricingPesByEncounterClassMetadata::unitCogsPerEncounter(['videollamada' => true], 'EMER')
        );
        $this->assertSame(0.0006, PricingPesByEncounterClassMetadata::motivosCogsPerEncounter('AMB'));
        $this->assertSame(0.0006, PricingPesByEncounterClassMetadata::motivosCogsPerEncounter('EMER'));
        $this->assertSame(0.0006, PricingPesByEncounterClassMetadata::motivosCogsPerEncounter('IMP'));
    }

    public function testInfraCogsIsSpreadOverReferenceEncounters(): void
    {
        $infra = (float) PricingPesByEncounterClassMetadata::loadConfig()
            ['cogs_assumptions']['infra_usd_per_professional_month'];

        foreach (PricingPesByEncounterClassMetadata::sellableClassCodes() as $class) {
            $reference = PricingPesByEncounterClassMetadata::referenceEncountersPerMonth($class);
            $this->assertEqualsWithDelta(
                $infra / $reference,
                PricingPesByEncounterClassMetadata::infraCogsPerEncounter($class),
                1e-9
            );
        }
        $this->assertEqualsWithDelta(
            $infra / PricingPesByEncounterClassMetadata::referenceEncountersPerMonth('AMB'),
            PricingPesByEncounterClassMetadata::infraCogsPerEncounter(),
            1e-9
        );
    }

    public function testVolumeDiscountTiersByAttentions(): void
    {
        $this->assertSame('lista', PricingPesByEncounterClassMetadata::tierForTotalAttentions(200)['id']);
        $this->assertSame('mediano', PricingPesByEncounterClassMetadata::tierForTotalAttentions(5000)['id']);
        $this->assertSame('grande', PricingPesByEncounterClassMetadata::tierForTotalAttentions(20000)['id']);
        $this->assertSame('alto_volumen', PricingPesByEncounterClassMetadata::tierForTotalAttentions(40000)['id']);
        $this->assertSame(134.0, PricingPesByEncounterClassMetadata::marginOnCostPercentForTotalAttentions(20000));
    }

    public function testEstimateMonthlyTotalByAttentions(): void
    {
        // 20.000 AMB, tramo grande: unit 0.0203 × 20000 = 406
        $total = PricingPesByEncounterClassMetadata::estimateMonthlyTotal(['AMB' => 20000]);
        $this->assertSame(406.0, $total);
        $quote = PricingPesByEncounterClassMetadata::estimateMonthlyQuote(['AMB' => 20000]);
        $this->assertSame(576.0, $quote['list_amount_usd']);
        $this->assertSame(170.0, $quote['discount_amount_usd']);
        $this->assertSame(29.5, $quote['discount_percent']);
    }

    public function testFullTimeProfessionalPrice(): void
    {
        // Jornada completa = 500 atenciones → ~USD 14,40
        $quote = PricingPesByEncounterClassMetadata::estimateMonthlyQuote(['AMB' => 500]);
        $this->assertSame(14.4, $quote['amount_usd']);
        $this->assertSame(500, $quote['total_attentions']);
        $this->assertSame([
            'code' => 'AMB',
            'attentions_per_month' => 500,
            'unit_amount_usd' => 0.0288,
            'amount_usd' => 14.4,
        ], $quote['items'][0]);
        $this->assertSame(14.4, $quote['list_amount_usd']);
        $this->assertSame(0.0, $quote['discount_amount_usd']);
        $this->assertSame(0.0, $quote['discount_percent']);
        $this->assertSame(500.0, PricingPesByEncounterClassMetadata::referenceEncountersPerMonth());
        $this->assertSame(700.0, PricingPesByEncounterClassMetadata::referenceEncountersPerMonth('EMER'));
        $this->assertSame(400.0, PricingPesByEncounterClassMetadata::referenceEncountersPerMonth('IMP'));
    }

    public function testHalfTimeProfessionalPrice(): void
    {
        // Media jornada = 250 atenciones → ~USD 7,20
        $quote = PricingPesByEncounterClassMetadata::estimateMonthlyQuote(['AMB' => 250]);
        $this->assertSame(7.2, $quote['amount_usd']);
        $this->assertSame(250, $quote['total_attentions']);
        $this->assertSame(0.0288, $quote['items'][0]['unit_amount_usd']);
    }

    public function testDictadoMinutesDriveSttCogs(): void
    {
        $assumptions = PricingPesByEncounterClassMetadata::loadConfig()['cogs_assumptions'];
        $this->assertSame((float) $assumptions['dictado_minutes'], PricingPesByEncounterClassMetadata::dictadoMinutes());
        $this->assertEqualsWithDelta(
            (float) $assumptions['dictado_minutes'] * (float) $assumptions['dictado_effective_usd_per_minute'],
            PricingPesByEncounterClassMetadata::dictadoCogsPerEncounter(),
            1e-9
        );
    }

    public function testVolumePresetsDeriveFromReferenceCapacity(): void
    {
        $presets = PricingPesByEncounterClassMetadata::attentionVolumePresets();
        $multipliers = array_values(array_map(
            'floatval',
            (array) PricingPesByEncounterClassMetadata::loadConfig()['attention_volume_multipliers']
        ));
        $one = array_search(1.0, $multipliers, true);
        $last = count($multipliers) - 1;

        foreach (PricingPesByEncounterClassMetadata::sellableClassCodes() as $class) {
            $reference = PricingPesByEncounterClassMetadata::referenceEncountersPerMonth($class);
            $this->assertCount(count($multipliers), $presets[$class]);
            $this->assertSame((int) round($reference * $multipliers[0]), $presets[$class][0]['attentions']);
            $this->assertNotFalse($one);
            $this->assertSame((int) round($reference * $multipliers[$one]), $presets[$class][$one]['attentions']);
            $this->assertSame((int) round($reference * $multipliers[$last]), $presets[$class][$last]['attentions']);
        }

        // El copy sigue saliendo del catálogo y no repite el volumen.
        $this->assertSame('Media jornada · 250 atenciones / mes', $presets['AMB'][0]['label']);
        $this->assertSame('Jornada completa · 500 atenciones / mes', $presets['AMB'][1]['label']);
        $this->assertSame('~25 atenciones/día', $presets['AMB'][1]['hint']);
        $this->assertSame('Medio turno de guardia · 350 atenciones / mes', $presets['EMER'][0]['label']);
        $this->assertSame('Guardia completa · 700 atenciones / mes', $presets['EMER'][1]['label']);
        $this->assertSame('Media jornada · 200 pases / mes', $presets['IMP'][0]['label']);
        $this->assertSame('Jornada completa · 400 pases / mes', $presets['IMP'][1]['label']);
        $this->assertNull($presets['AMB'][2]['label']);
    }

    public function testInitialAttentionsDeriveFromReferenceCapacity(): void
    {
        $defaults = PricingPesByEncounterClassMetadata::initialAttentionsByClass();

        $this->assertSame(250, $defaults['AMB']);
        $this->assertSame(350, $defaults['EMER']);
        $this->assertSame(200, $defaults['IMP']);
        $this->assertSame(250, PricingPesByEncounterClassMetadata::initialAttentionsForClass('AMB'));
        $this->assertNull(PricingPesByEncounterClassMetadata::initialAttentionsForClass('VR'));
    }

    public function testMissingReferenceCapacityFailsExplicitly(): void
    {
        $broken = PricingPesByEncounterClassMetadata::loadConfig();
        unset($broken['reference_encounters_per_professional_month']);
        $property = new \ReflectionProperty(PricingPesByEncounterClassMetadata::class, 'config');
        $property->setAccessible(true);

        try {
            $property->setValue(null, $broken);
            $this->expectException(\yii\base\InvalidConfigException::class);
            PricingPesByEncounterClassMetadata::referenceEncountersPerMonth('AMB');
        } finally {
            PricingPesByEncounterClassMetadata::reset();
        }
    }

    public function testMissingVolumeTiersFailExplicitly(): void
    {
        $broken = PricingPesByEncounterClassMetadata::loadConfig();
        unset($broken['volume_discount_tiers']);
        $property = new \ReflectionProperty(PricingPesByEncounterClassMetadata::class, 'config');
        $property->setAccessible(true);

        try {
            $property->setValue(null, $broken);
            $this->expectException(\yii\base\InvalidConfigException::class);
            PricingPesByEncounterClassMetadata::tierForTotalAttentions(1);
        } finally {
            PricingPesByEncounterClassMetadata::reset();
        }
    }

    public function testAddonPlanValuesFollowTheCatalogMatrix(): void
    {
        foreach (PricingPesByEncounterClassMetadata::sellableClassCodes() as $class) {
            $values = PricingPesByEncounterClassMetadata::addonPlanValues($class);
            foreach (PricingPesByEncounterClassMetadata::addonPlanFields() as $addonId => $field) {
                $state = PricingPesByEncounterClassMetadata::addonState($addonId, $class);
                $this->assertSame(
                    $state === PricingPesByEncounterClassCatalog::ADDON_INCLUDED,
                    $values[$field],
                    $class . '/' . $addonId
                );
            }
        }
    }

    public function testIncludedAddonCannotBeDisabledByTheClient(): void
    {
        $this->assertTrue(
            PricingPesByEncounterClassMetadata::addonPlanValues('AMB', ['dictado_incluido' => false])['dictado_incluido']
        );
        $this->assertTrue(
            PricingPesByEncounterClassMetadata::addonPlanValues('AMB', ['videollamada_permitida' => true])['videollamada_permitida']
        );
        $this->assertFalse(
            PricingPesByEncounterClassMetadata::addonPlanValues('EMER', ['videollamada_permitida' => true])['videollamada_permitida']
        );
    }

    public function testListTierInheritsTheLicenseMargin(): void
    {
        $tiers = PricingPesByEncounterClassMetadata::volumeDiscountTiers();
        $this->assertArrayNotHasKey('margin_on_cost_percent', $tiers[0]);
        $this->assertSame(
            PricingPesByEncounterClassMetadata::marginOnCostPercent(),
            PricingPesByEncounterClassMetadata::marginOnCostPercentForTotalAttentions(1)
        );
    }

    public function testAddonCatalogDeclaresInclusionPerClass(): void
    {
        $this->assertSame(
            'included',
            PricingPesByEncounterClassMetadata::addonState('autogestion_paciente', 'AMB')
        );
        $this->assertSame(
            'unavailable',
            PricingPesByEncounterClassMetadata::addonState('autogestion_paciente', 'EMER')
        );
        $this->assertSame(
            'included',
            PricingPesByEncounterClassMetadata::addonState('autogestion_personal', 'IMP')
        );
        $this->assertSame(
            'optional',
            PricingPesByEncounterClassMetadata::addonState('videollamada', 'AMB')
        );

        $this->assertSame('all', PricingPesByEncounterClassMetadata::addonScope('autogestion_personal'));
        $this->assertSame('all', PricingPesByEncounterClassMetadata::addonScope('dictado'));
        $this->assertSame('per_class', PricingPesByEncounterClassMetadata::addonScope('autogestion_paciente'));
    }

    public function testOptionalAddonChangesPrice(): void
    {
        $base = PricingPesByEncounterClassMetadata::estimateMonthlyQuote(['AMB' => 500]);
        $withVideo = PricingPesByEncounterClassMetadata::estimateMonthlyQuote(
            ['AMB' => 500],
            ['videollamada' => true]
        );

        $this->assertSame(14.4, $base['amount_usd']);
        $this->assertSame(21.75, $withVideo['amount_usd']);
    }

    public function testWhatsappAddonIsDeclaredButDisabled(): void
    {
        // El add-on de WhatsApp está comentado en el catálogo: no se ofrece ni
        // mueve el precio. La config queda para una habilitación futura.
        $this->assertNotContains('whatsapp', PricingPesByEncounterClassMetadata::addonIds());
        $this->assertNull(PricingPesByEncounterClassMetadata::addonState('whatsapp', 'AMB'));
        $this->assertArrayNotHasKey('whatsapp', PricingPesByEncounterClassMetadata::addonCogsPerEncounter());
    }

    public function testAfterTaxMarginsDeriveFromCatalogAssumptions(): void
    {
        $tax = PricingPesByEncounterClassMetadata::taxAssumptions();
        $this->assertSame('Santiago del Estero (AR)', $tax['jurisdiction']);
        $this->assertSame(4.0, $tax['iibb_rate_percent']);
        $this->assertSame(35.0, $tax['income_tax_rate_percent']);

        // (1 − 0,04 − 1/3,33) × 0,65 = 42,9 %; con IDCB 0,6 % = 42,5 %.
        $this->assertSame(42.9, PricingPesByEncounterClassMetadata::afterTaxMarginPercent(233));
        $this->assertSame(42.5, PricingPesByEncounterClassMetadata::afterTaxMarginPercent(233, true));
        $this->assertSame(37.7, PricingPesByEncounterClassMetadata::afterTaxMarginPercent(163));
        $this->assertSame(34.6, PricingPesByEncounterClassMetadata::afterTaxMarginPercent(134));
        $this->assertSame(32.4, PricingPesByEncounterClassMetadata::afterTaxMarginPercent(117));

        $lista = PricingPesByEncounterClassMetadata::tierForTotalAttentions(200);
        $this->assertSame(42.9, $lista['margin_after_iibb_ganancias_percent']);
        $this->assertSame(42.5, $lista['margin_after_all_taxes_percent']);
    }

    public function testChangingTaxAssumptionsRecomputesMargins(): void
    {
        $config = PricingPesByEncounterClassMetadata::loadConfig();
        $config['tax_assumptions']['iibb_rate_percent'] = 3.0;
        $config['tax_assumptions']['income_tax_rate_percent'] = 25.0;
        $property = new \ReflectionProperty(PricingPesByEncounterClassMetadata::class, 'config');
        $property->setAccessible(true);

        try {
            $property->setValue(null, $config);
            // (1 − 0,03 − 1/3,33) × 0,75 = 50,2 %
            $this->assertSame(50.2, PricingPesByEncounterClassMetadata::afterTaxMarginPercent(233));
            $this->assertSame(
                50.2,
                PricingPesByEncounterClassMetadata::tierForTotalAttentions(200)['margin_after_iibb_ganancias_percent']
            );
        } finally {
            PricingPesByEncounterClassMetadata::reset();
        }
    }

    public function testAddonMarginIsIndependentFromLicenseMargin(): void
    {
        $config = PricingPesByEncounterClassMetadata::loadConfig();
        $config['addon_margin_on_cost_percent'] = 100.0;
        $property = new \ReflectionProperty(PricingPesByEncounterClassMetadata::class, 'config');
        $property->setAccessible(true);

        try {
            $property->setValue(null, $config);
            // base 0.00866 × 3.33 + video 0.0044 × 2 = 0.037638 → 0.0376 × 500 = 18.80
            $quote = PricingPesByEncounterClassMetadata::estimateMonthlyQuote(
                ['AMB' => 500],
                ['videollamada' => true]
            );
            $this->assertSame(18.8, $quote['amount_usd']);
            // El precio base no se mueve.
            $this->assertSame(14.4, PricingPesByEncounterClassMetadata::estimateMonthlyQuote(['AMB' => 500])['amount_usd']);
        } finally {
            PricingPesByEncounterClassMetadata::reset();
        }
    }

    public function testDefaultWhenEmptyAllowAll(): void
    {
        $this->assertTrue(PricingPesByEncounterClassMetadata::defaultWhenEmptyAllowAll());
    }
}
