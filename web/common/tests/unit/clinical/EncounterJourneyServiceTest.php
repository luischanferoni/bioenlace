<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Service\EncounterJourney\EncounterJourneyEligibilityService;
use common\components\Domain\Clinical\Service\EncounterJourney\EncounterPhaseEligibilityCatalogService;
use common\components\Domain\Clinical\Service\EncounterJourney\EncounterMotivosIntakeCatalogService;
use common\components\Domain\Clinical\Service\EncounterJourney\EncounterPhaseWindowOverrideCatalogService;
use common\components\Domain\Clinical\Service\EncounterJourney\EncounterPhaseWindowResolver;
use common\components\Domain\Clinical\Service\EncounterJourney\EncounterPhaseWindowService;
use common\components\Domain\Clinical\Service\EncounterJourney\EncounterPhaseWindowsCatalogService;

class EncounterJourneyServiceTest extends Unit
{
    protected function _after(): void
    {
        EncounterPhaseWindowsCatalogService::resetCacheForTests();
        EncounterPhaseEligibilityCatalogService::resetCacheForTests();
        EncounterPhaseWindowOverrideCatalogService::resetCacheForTests();
        EncounterMotivosIntakeCatalogService::resetCacheForTests();
    }

    public function testMotivosSkipWhenEncounterAsync(): void
    {
        $elig = new EncounterJourneyEligibilityService();
        $result = $elig->evaluate('motivos_consulta', [
            'encounter_id' => 10,
            'encounter_class' => 'AMB',
            'encounter_parent_type' => 'SOLICITUD_ASYNC',
            'tipo_atencion' => 'async',
            'turno_estado' => 'PENDIENTE',
        ]);
        $this->assertFalse($result['applies']);
        $this->assertStringContainsString('encounter_parent_type', (string) $result['skip_reason']);
    }

    public function testMotivosAppliesForAmbulatoryTurno(): void
    {
        $elig = new EncounterJourneyEligibilityService();
        $result = $elig->evaluate('motivos_consulta', [
            'encounter_id' => 10,
            'encounter_class' => 'AMB',
            'encounter_parent_type' => 'TURNO',
            'tipo_atencion' => 'presencial',
            'turno_estado' => 'PENDIENTE',
        ]);
        $this->assertTrue($result['applies']);
        $this->assertNull($result['skip_reason']);
    }

    public function testWindowClosedBeforeOpenOffset(): void
    {
        $turnoAt = time() + 10 * 86400;
        $context = ['turno_starts_at' => $turnoAt];
        $window = (new EncounterPhaseWindowService())->state('motivos_consulta', $context);
        $this->assertFalse($window['input_abierto']);
        $this->assertNotNull($window['abre_en']);
    }

    public function testWindowOpenInsideRange(): void
    {
        $turnoAt = time() + 3600;
        $context = ['turno_starts_at' => $turnoAt];
        $window = (new EncounterPhaseWindowService())->state('motivos_consulta', $context);
        $this->assertTrue($window['input_abierto']);
    }

    public function testPostConsultaSkipWhenEncounterNotFinished(): void
    {
        $elig = new EncounterJourneyEligibilityService();
        $result = $elig->evaluate('post_consulta', [
            'encounter_id' => 10,
            'encounter_class' => 'AMB',
            'encounter_finished' => false,
            'care_cohort_enabled' => true,
            'sin_pack_followup' => false,
        ]);
        $this->assertFalse($result['applies']);
    }

    public function testPostConsultaAppliesWhenEncounterFinished(): void
    {
        $elig = new EncounterJourneyEligibilityService();
        $result = $elig->evaluate('post_consulta', [
            'encounter_id' => 10,
            'encounter_class' => 'AMB',
            'encounter_finished' => true,
            'encounter_finished_at' => date('c', time() - 3600),
            'care_cohort_enabled' => true,
            'sin_pack_followup' => false,
        ]);
        $this->assertTrue($result['applies']);
        $this->assertSame('pack_followup', $result['surface']);
    }

    public function testWindowOverrideByEfector(): void
    {
        $overrideCatalog = $this->createMock(EncounterPhaseWindowOverrideCatalogService::class);
        $overrideCatalog->method('phaseOverride')->willReturnCallback(
            static function (string $phaseId, array $context): ?array {
                if ($phaseId === 'motivos_consulta' && (int) ($context['id_efector'] ?? 0) === 99) {
                    return ['open_offset' => '-96h'];
                }

                return null;
            }
        );
        $resolver = new EncounterPhaseWindowResolver(null, $overrideCatalog);
        $def = $resolver->phaseDefinition('motivos_consulta', ['id_efector' => 99]);
        $this->assertSame('-96h', $def['open_offset'] ?? null);
    }

    public function testMotivosIntakeSkipWhenDisabled(): void
    {
        $elig = new EncounterJourneyEligibilityService();
        $result = $elig->evaluate('motivos_intake', [
            'motivos_intake_habilitado' => false,
            'encounter_id' => 10,
            'encounter_class' => 'AMB',
            'turno_estado' => 'PENDIENTE',
        ]);
        $this->assertFalse($result['applies']);
    }

    public function testPhaseIdsByAnchor(): void
    {
        $catalog = new EncounterPhaseWindowsCatalogService();
        $pre = $catalog->phaseIdsByAnchor('turno_start');
        $this->assertContains('motivos_consulta', $pre);
        $this->assertNotContains('post_consulta', $pre);
        $post = $catalog->phaseIdsByAnchor('encounter_finished');
        $this->assertContains('post_consulta', $post);
    }
}
