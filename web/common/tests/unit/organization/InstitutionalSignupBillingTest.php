<?php

namespace common\tests\unit\organization;

use common\components\Domain\Organization\Efector\Application\UseCase\SignUpInstitutionalEfector;
use common\components\Domain\Organization\Efector\Infrastructure\External\Billing\SimulatedPaymentGateway;
use common\components\Platform\Core\Product\PricingPesByEncounterClassMetadata;
use Codeception\Test\Unit;

class InstitutionalSignupBillingTest extends Unit
{
    public function testEstimateMonthlyUsdAmbWithDictadoIncluded(): void
    {
        $usd = SignUpInstitutionalEfector::estimateMonthlyUsd([
            'classes' => [
                'AMB' => [
                    'attentions_per_month' => 5000,
                    'dictado_incluido' => false, // se ignora: dictado siempre incluido
                    'videollamada_permitida' => false,
                ],
            ],
        ]);
        // 5000 × 0.0093 × 2.63 (tramo mediano, redondeo unitario) = 122.50
        $this->assertSame(122.5, $usd);
    }

    public function testEstimatePlanMonthlyQuoteIncludesClassAndVolumeDiscountBreakdown(): void
    {
        $quote = SignUpInstitutionalEfector::estimatePlanMonthlyQuote([
            'classes' => [
                'AMB' => [
                    'attentions_per_month' => 20000,
                    'dictado_incluido' => true,
                    'videollamada_permitida' => false,
                ],
            ],
        ]);

        $this->assertSame(436.0, $quote['amount_usd']);
        $this->assertSame(620.0, $quote['list_amount_usd']);
        $this->assertSame(184.0, $quote['discount_amount_usd']);
        $this->assertSame(29.7, $quote['discount_percent']);
        $this->assertSame(20000, $quote['total_attentions']);
        $this->assertSame('AMB', $quote['items'][0]['code']);
        $this->assertSame(20000, $quote['items'][0]['attentions_per_month']);
        $this->assertSame(0.0218, $quote['items'][0]['unit_amount_usd']);
        $this->assertSame(436.0, $quote['items'][0]['amount_usd']);
    }

    public function testAvailableCentersRejectOverlongQueryWithoutDatabase(): void
    {
        $this->assertSame(
            [],
            SignUpInstitutionalEfector::listAvailableCenters(str_repeat('a', 81))
        );
    }

    public function testFindAvailableCenterRejectsInvalidId(): void
    {
        $this->assertNull(SignUpInstitutionalEfector::findAvailableCenter(0));
    }

    public function testRetiredCommercialProfilesAreRejected(): void
    {
        foreach (['CONSULTORIO', 'CLINICA'] as $profile) {
            try {
                SignUpInstitutionalEfector::prepareDraft(['perfil' => $profile]);
                $this->fail('El perfil comercial legacy debía ser rechazado: ' . $profile);
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString('contrato unificado', $exception->getMessage());
            }
        }
    }

    public function testSignupQuoteUsesUnifiedSpaceProfile(): void
    {
        $quote = SignUpInstitutionalEfector::estimatePlanMonthlyQuote([
            'classes' => [
                'AMB' => ['attentions_per_month' => 200],
            ],
        ]);

        $this->assertGreaterThan(0, $quote['amount_usd']);
        $this->assertSame(200, $quote['total_attentions']);
    }

    public function testSisaCatalogSearchRejectsQueriesOutsideSupportedLength(): void
    {
        $this->assertSame([], SignUpInstitutionalEfector::searchAvailablePublicEfectores('A'));
        $this->assertSame([], SignUpInstitutionalEfector::searchAvailablePublicEfectores(str_repeat('x', 81)));
    }

    public function testUnifiedVolumeDoesNotSetAProfessionalSeatLimit(): void
    {
        $normalizer = new \ReflectionMethod(SignUpInstitutionalEfector::class, 'normalizePlan');
        $normalizer->setAccessible(true);

        $plan = $normalizer->invoke(null, [
            'classes' => [
                'AMB' => [
                    'attentions_per_month' => 200,
                    'max_pes' => 1,
                ],
            ],
        ]);

        $this->assertNull($plan['classes']['AMB']['max_pes']);
    }

    public function testPersistedLegacyConsultorioCheckoutKeepsItsExistingLimit(): void
    {
        $normalizer = new \ReflectionMethod(SignUpInstitutionalEfector::class, 'normalizePlan');
        $normalizer->setAccessible(true);

        $plan = $normalizer->invoke(null, [
            'classes' => [
                'AMB' => [
                    'attentions_per_month' => 200,
                    'max_pes' => 1,
                ],
            ],
        ], 'CONSULTORIO');

        $this->assertSame(1, $plan['classes']['AMB']['max_pes']);
    }

    public function testSimFailPanConstant(): void
    {
        $this->assertSame('4000000000000002', SimulatedPaymentGateway::SIM_FAIL_PAN);
    }

    public function testPlanesCatalogHasSellableClasses(): void
    {
        $catalog = SignUpInstitutionalEfector::planesCatalog();
        $this->assertArrayHasKey('sellable_classes', $catalog);
        $this->assertArrayHasKey('AMB', $catalog['sellable_classes']);
        $this->assertArrayHasKey('cogs_usd_per_encounter', $catalog);
    }

    public function testPublicPlanesCatalogHidesInternalCosts(): void
    {
        $catalog = PricingPesByEncounterClassMetadata::publicConfig();

        $this->assertArrayHasKey('sellable_classes', $catalog);
        $this->assertArrayHasKey('attention_volume_presets', $catalog);
        $this->assertArrayHasKey('addons', $catalog);
        $this->assertArrayNotHasKey('cogs_usd_per_encounter', $catalog);
        $this->assertArrayNotHasKey('cogs_assumptions', $catalog);
        $this->assertArrayNotHasKey('margin_on_cost_percent', $catalog);
        $this->assertArrayNotHasKey('volume_discount_tiers', $catalog);
        $this->assertArrayNotHasKey('cogs_source', $catalog);

        $amb = $catalog['sellable_classes']['AMB'];
        $this->assertTrue($amb['audio_included']);
        $this->assertTrue($amb['videollamada_allowed']);
        $this->assertArrayNotHasKey('product_gates', $amb);
        $this->assertArrayNotHasKey('max_pes', $amb);
    }
}
