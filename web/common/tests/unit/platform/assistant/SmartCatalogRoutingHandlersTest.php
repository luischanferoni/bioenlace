<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Routing\Handlers\SmartCatalogRoutingHandlers;
use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectCatalog;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\CatalogCtaResolver;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingService;
use common\components\Platform\Ai\Cost\AICostTracker;

class SmartCatalogRoutingHandlersTest extends Unit
{
    protected function _after(): void
    {
        SmartCatalogRegistry::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
        AssistantContextAreaAspectCatalog::resetCacheForTests();
        AssistantPlanningLogService::resetForTests();
        if (class_exists(AICostTracker::class)) {
            AICostTracker::finalizarEjecucionPrueba();
        }
    }

    public function testFueraDeHisHandlerReturnsMessageEnvelope(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'necesito una sesion con una medium',
            'tags' => ['fuera_his'],
            'context_areas' => [],
            'extractions' => [],
        ], 0);

        $envelope = SmartCatalogRoutingHandlers::tryHandle($evaluation, 'medium', 0);

        $this->assertIsArray($envelope);
        $this->assertSame('message', $envelope['kind'] ?? null);
        $this->assertNotSame('', trim((string) ($envelope['text'] ?? '')));
        $snap = AssistantPlanningLogService::snapshot();
        $this->assertSame('1ia_fuera', $snap['final_path'] ?? null);
    }

    public function testDudosaHandlerReturnsInteractiveEnvelope(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'xyzzy',
            'routing_hint' => 'sin_pedido',
            'tags' => [],
            'context_areas' => [],
            'extractions' => [],
        ], 0);

        $envelope = SmartCatalogRoutingHandlers::tryHandle($evaluation, 'xyzzy', 0);

        $this->assertIsArray($envelope);
        $this->assertSame('interactive', $envelope['kind'] ?? null);
        $this->assertNotEmpty($envelope['buttons'] ?? []);
        $snap = AssistantPlanningLogService::snapshot();
        $this->assertSame('1ia_dudosa', $snap['final_path'] ?? null);
    }

    public function testTurnosBareWordUsesIncompletasNotDisambiguationButtons(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'turnos',
            'routing_hint' => 'pedido_claro',
            'tags' => ['scheduling'],
            'context_areas' => ['scheduling'],
            'intent_ids_hint' => [],
            'extractions' => [],
        ], 0);

        $this->assertTrue($evaluation->decision->isIncompletas());
        $this->assertFalse($evaluation->decision->shouldRouteIntentDirectly());
    }

    public function testClaraIntentGoesToGuideNotFlow(): void
    {
        if (class_exists(AICostTracker::class)) {
            AICostTracker::iniciarEjecucionPrueba();
        }

        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'necesito una ecografia',
            'routing_hint' => 'pedido_claro',
            'tags' => ['estudio'],
            'context_areas' => [],
            'extractions' => [],
        ], 0);

        $this->assertTrue($evaluation->decision->shouldRouteIntentDirectly());
        $this->assertSame('atencion.necesito-atencion', $evaluation->decision->primaryIntentId());
        $this->assertContains(
            'atencion.necesito-atencion',
            CatalogCtaResolver::declaredIntentIds($evaluation)
        );

        AssistantPlanningLogService::begin($evaluation->firstIa, $evaluation->match->ranked);
        $envelope = SmartCatalogRoutingHandlers::tryHandle(
            $evaluation,
            'necesito una ecografia',
            0
        );

        $this->assertNotSame('flow', is_array($envelope) ? ($envelope['kind'] ?? null) : null);
        if (is_array($envelope)) {
            $this->assertContains($envelope['kind'] ?? null, ['message', 'interactive']);
        }
        $final = AssistantPlanningLogService::snapshot()['final_path'] ?? null;
        $this->assertNotContains($final, ['1ia_clara', 'legacy_operational_intent_classifier']);
        if (is_string($final) && $final !== '') {
            $this->assertContains($final, ['2ia_guide', '3ia_planner_guide']);
        }
    }
}
