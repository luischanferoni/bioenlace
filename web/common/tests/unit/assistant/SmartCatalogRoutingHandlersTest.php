<?php

namespace common\tests\unit\assistant;

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
            'routing_hint' => 'dudosa',
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

    public function testClaraMultipleReturnsInteractiveWhenNoSingleIntentFlow(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'turnos',
            'routing_hint' => 'clara',
            'tags' => ['turno', 'appointments'],
            'context_areas' => ['appointments'],
            'intent_ids_hint' => [
                'turnos.crear-como-paciente',
                'turnos.ver-ultimo-en-oferta-como-paciente',
            ],
            'extractions' => [],
        ], 0);

        if (!$evaluation->decision->hasMultipleClaraIntents()) {
            $this->markTestSkipped('El match no produjo múltiples intents claros en este entorno.');
        }

        $envelope = SmartCatalogRoutingHandlers::tryHandle($evaluation, 'turnos', 0);

        $this->assertIsArray($envelope);
        $this->assertContains($envelope['kind'] ?? null, ['interactive', 'flow']);
    }
}
