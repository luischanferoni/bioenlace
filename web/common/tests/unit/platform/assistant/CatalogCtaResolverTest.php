<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\CatalogCtaResolver;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingService;

class CatalogCtaResolverTest extends Unit
{
    protected function _after(): void
    {
        SmartCatalogRegistry::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
        AssistantPlanningLogService::resetForTests();
    }

    public function testBareTurnoDeclaresDualCtasWithHumanLabels(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'Quiero un turno',
            'user_goal' => 'guide',
            'routing_hint' => 'pedido_claro',
            'tags' => ['pedido_turno_sin_destino', 'scheduling'],
            'context_areas' => ['scheduling'],
            'extractions' => [],
        ], 1);

        $ids = CatalogCtaResolver::declaredIntentIds($evaluation);
        $this->assertSame(
            ['turnos.crear-como-paciente', 'atencion.necesito-atencion'],
            $ids
        );

        $buttons = CatalogCtaResolver::resolveAll($evaluation, 1);
        $this->assertCount(2, $buttons);
        $this->assertSame('turnos.crear-como-paciente', $buttons[0]['intent_id']);
        $this->assertNotSame('', $buttons[0]['label']);
        $this->assertSame('atencion.necesito-atencion', $buttons[1]['intent_id']);
        $this->assertNotSame('', $buttons[1]['label']);
    }

    public function testResolveAllRequiresPositiveUserId(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'Quiero un turno',
            'user_goal' => 'guide',
            'routing_hint' => 'pedido_claro',
            'tags' => ['pedido_turno_sin_destino', 'scheduling'],
            'context_areas' => ['scheduling'],
            'extractions' => [],
        ], 1);

        $this->assertSame([], CatalogCtaResolver::resolveAll($evaluation, 0));
        $this->assertNotEmpty(CatalogCtaResolver::declaredIntentIds($evaluation));
    }
}
