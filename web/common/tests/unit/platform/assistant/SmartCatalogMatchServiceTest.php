<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\DiscoveryIndex;
use common\components\Platform\Assistant\Catalog\IntentSchemaPaths;
use common\components\Platform\Assistant\Catalog\SmartCatalogMatchService;
use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;
use common\components\Platform\Assistant\Catalog\StateTagIndex;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectCatalog;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;

/**
 * direct-doors vacío: el match legacy no rankea; discovery cubre intents.
 */
class SmartCatalogMatchServiceTest extends Unit
{
    protected function _after(): void
    {
        SmartCatalogRegistry::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
        AssistantContextAreaAspectCatalog::resetCacheForTests();
        AssistantPlanningLogService::resetForTests();
        StateTagIndex::resetCacheForTests();
        IntentSchemaPaths::resetIndexCache();
    }

    public function testRegistryHasNoDoorEntries(): void
    {
        $this->assertSame([], SmartCatalogRegistry::entries());
        $this->assertNull(SmartCatalogRegistry::findById('llegar-tarde-politicas'));
    }

    public function testLegacyMatchIsEmptyWithoutDoors(): void
    {
        $result = SmartCatalogMatchService::match([
            'normalized_text' => '¿Voy a tener problemas si llego 10 minutos tarde?',
            'tags' => ['llegar_tarde', 'scheduling'],
            'context_areas' => ['scheduling'],
        ], 0);

        $this->assertTrue($result->isEmpty());
        $this->assertSame([], $result->ranked);
    }

    public function testDiscoveryFindsPoliticaOnLlegarTardeTags(): void
    {
        $result = DiscoveryIndex::match([
            'normalized_text' => '¿Voy a tener problemas si llego 10 minutos tarde?',
            'tags' => ['llegar_tarde', 'tolerancia'],
            'routing_hint' => 'guide',
        ]);

        $this->assertContains(
            'turnos.consultar-politica-autogestion-flow',
            $result->intentIds()
        );
    }

    public function testFueraHisTagDoesNotNeedDoorEntry(): void
    {
        $result = SmartCatalogMatchService::match([
            'normalized_text' => 'necesito una sesion con una medium',
            'tags' => ['fuera_his'],
            'context_areas' => [],
        ], 0);

        $this->assertTrue($result->isEmpty());
    }
}
