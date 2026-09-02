<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\SmartCatalogMatchService;
use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectCatalog;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingService;

class SmartCatalogRoutingServiceTest extends Unit
{
    protected function _after(): void
    {
        SmartCatalogRegistry::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
        AssistantContextAreaAspectCatalog::resetCacheForTests();
        AssistantPlanningLogService::resetForTests();
    }

    public function testDirectArticleRepresentacion(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'contame sobre representacion de mi hijo',
            'routing_hint' => 'directo',
            'tags' => ['representacion', 'tutela'],
            'context_areas' => ['representation'],
            'extractions' => [],
        ], 0);

        $decision = $evaluation->decision;
        $this->assertSame('directo', $decision->routingResult);
        $this->assertTrue($decision->isDirectArticle());
        $this->assertSame('representacion', $decision->articleTopic);
        $this->assertSame('articulo-representacion', $decision->catalogEntry?->id);
    }

    public function testClaraSingleIntentTurnos(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'quiero sacar un turno',
            'routing_hint' => 'clara',
            'tags' => ['sacar_turno', 'turno'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ], 0);

        $decision = $evaluation->decision;
        $this->assertSame('clara', $decision->routingResult);
        $this->assertTrue($decision->shouldRouteIntentDirectly());
        $this->assertSame('turnos.crear-como-paciente', $decision->primaryIntentId());
    }

    public function testClaraMultipleIntentsFromHints(): void
    {
        $match = SmartCatalogMatchService::match([
            'normalized_text' => 'turnos',
            'tags' => ['turno', 'appointments'],
            'context_areas' => ['appointments'],
            'intent_ids_hint' => [
                'turnos.crear-como-paciente',
                'turnos.ver-ultimo-en-oferta-como-paciente',
            ],
        ], 0);

        $intentIds = SmartCatalogMatchService::collectClaraIntentIds($match, [
            'intent_ids_hint' => [
                'turnos.crear-como-paciente',
                'turnos.ver-ultimo-en-oferta-como-paciente',
            ],
        ], 0);

        $this->assertCount(2, $intentIds);

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

        $decision = $evaluation->decision;
        $this->assertSame('clara', $decision->routingResult);
        $this->assertTrue($decision->hasMultipleClaraIntents());
        $this->assertCount(2, $decision->intentIds);
    }

    public function testDudosaWhenNoMatch(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'hola',
            'routing_hint' => 'dudosa',
            'tags' => [],
            'context_areas' => [],
            'extractions' => [],
        ], 0);

        $this->assertTrue($evaluation->decision->isDudosa());
    }

    public function testLlegarTardeRoutesIncompletas(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => '¿Voy a tener problemas si llego 10 minutos tarde?',
            'routing_hint' => 'incompletas',
            'tags' => ['llegar_tarde', 'appointments'],
            'context_areas' => ['appointments'],
            'extractions' => [
                ['span' => '10 minutos', 'category' => 'tiempo', 'synonyms' => []],
            ],
        ], 0);

        $this->assertTrue($evaluation->decision->isIncompletas());
        $this->assertFalse($evaluation->declarativePlan->needsPlanner);
        $this->assertContains(
            'aspect:site.appointment.policies',
            $evaluation->declarativePlan->toolIds
        );
    }
}
