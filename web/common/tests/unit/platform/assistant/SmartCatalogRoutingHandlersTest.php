<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Routing\Handlers\SmartCatalogRoutingHandlers;
use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectCatalog;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingService;

class SmartCatalogRoutingHandlersTest extends Unit
{
    protected function _after(): void
    {
        SmartCatalogRegistry::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
        AssistantContextAreaAspectCatalog::resetCacheForTests();
        AssistantPlanningLogService::resetForTests();
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
            'normalized_text' => 'hola',
            'routing_hint' => 'sin_pedido',
            'tags' => [],
            'context_areas' => [],
            'extractions' => [],
        ], 0);

        $envelope = SmartCatalogRoutingHandlers::tryHandle($evaluation, 'hola', 0);

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
}
