<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\SmartCatalogMatchService;
use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectCatalog;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;

class SmartCatalogMatchServiceTest extends Unit
{
    protected function _after(): void
    {
        SmartCatalogRegistry::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
        AssistantContextAreaAspectCatalog::resetCacheForTests();
        AssistantPlanningLogService::resetForTests();
    }

    public function testRegistryLoadsSeedEntries(): void
    {
        $entries = SmartCatalogRegistry::entries();

        $this->assertNotEmpty($entries);
        $this->assertNotNull(SmartCatalogRegistry::findById('llegar-tarde-politicas'));
    }

    public function testMatchLlegarTardeScoresAppointmentsAspects(): void
    {
        $result = SmartCatalogMatchService::match([
            'normalized_text' => '¿Voy a tener problemas si llego 10 minutos tarde?',
            'tags' => ['llegar_tarde', 'appointments'],
            'context_areas' => ['appointments'],
            'extractions' => [
                ['span' => '10 minutos', 'category' => 'tiempo', 'synonyms' => []],
            ],
        ], 0);

        $this->assertFalse($result->isEmpty());
        $this->assertSame('llegar-tarde-politicas', $result->best?->id);
        $this->assertGreaterThanOrEqual(30, $result->bestScore);

        $ids = array_column($result->ranked, 'catalog_id');
        $this->assertContains('llegar-tarde-cita-actual', $ids);
    }

    public function testMatchFueraHisMedium(): void
    {
        $result = SmartCatalogMatchService::match([
            'normalized_text' => 'necesito una sesion con una medium',
            'tags' => ['fuera_his'],
            'context_areas' => [],
        ], 0);

        $this->assertSame('fuera-his-servicios-inexistentes', $result->best?->id);
        $this->assertSame('fuera_de_his', $result->best?->routingResult);
    }

    public function testPlanningLogCapturesMatchSnapshot(): void
    {
        $firstIa = [
            'normalized_text' => 'contame sobre representacion',
            'necesidad_usuario' => 'Entender representacion de menores.',
            'routing_hint' => 'directo',
            'tags' => ['representacion'],
            'context_areas' => ['representation'],
            'extractions' => [],
            'intent_ids_hint' => [],
        ];
        $match = SmartCatalogMatchService::match($firstIa, 0);
        AssistantPlanningLogService::begin($firstIa, $match->ranked);
        AssistantPlanningLogService::setRoutingResult('clara');
        AssistantPlanningLogService::setFinalPath('1ia_direct');

        $snap = AssistantPlanningLogService::snapshot();
        $this->assertIsArray($snap);
        $this->assertSame('clara', $snap['routing_result']);
        $this->assertNotEmpty($snap['catalog_matches']);
    }
}
