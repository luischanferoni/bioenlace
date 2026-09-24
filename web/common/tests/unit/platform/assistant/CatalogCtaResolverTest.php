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
            'normalized_text' => 'necesito una ecografia',
            'user_goal' => 'guide',
            'routing_hint' => 'pedido_claro',
            'tags' => ['estudio'],
            'context_areas' => [],
            'extractions' => [],
        ], 1);

        $ids = CatalogCtaResolver::declaredIntentIds($evaluation);
        $this->assertSame(
            ['atencion.necesito-atencion'],
            $ids
        );

        $buttons = CatalogCtaResolver::resolveAll($evaluation, 1);
        $this->assertCount(1, $buttons);
        $this->assertSame('atencion.necesito-atencion', $buttons[0]['intent_id']);
        $this->assertNotSame('', $buttons[0]['label']);
        $this->assertCount(1, $buttons);
    }

    public function testResolveAllRequiresPositiveUserId(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'necesito una ecografia',
            'user_goal' => 'guide',
            'routing_hint' => 'pedido_claro',
            'tags' => ['estudio'],
            'context_areas' => [],
            'extractions' => [],
        ], 1);

        $this->assertSame([], CatalogCtaResolver::resolveAll($evaluation, 0));
        $this->assertNotEmpty(CatalogCtaResolver::declaredIntentIds($evaluation));
    }
}
