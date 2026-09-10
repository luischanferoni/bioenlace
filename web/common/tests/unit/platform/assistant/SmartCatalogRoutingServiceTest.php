<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
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
            'routing_hint' => 'pedido_claro',
            'tags' => ['representacion', 'tutela'],
            'context_areas' => ['representation'],
            'extractions' => [],
        ], 0);

        $decision = $evaluation->decision;
        $this->assertSame('clara', $decision->routingResult);
        $this->assertTrue($decision->isDirectArticle());
        $this->assertSame('representacion', $decision->articleTopic);
        $this->assertSame('articulo-representacion', $decision->catalogEntry?->id);
    }

    public function testClaraSingleIntentTurnosConDestino(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'quiero un turno con el cardiologo',
            'routing_hint' => 'pedido_claro',
            'tags' => ['sacar_turno'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ], 0);

        $decision = $evaluation->decision;
        $this->assertSame('clara', $decision->routingResult);
        $this->assertTrue($decision->shouldRouteIntentDirectly());
        $this->assertSame('turnos.crear-como-paciente', $decision->primaryIntentId());
    }

    public function testBareQuieroUnTurnoGoesIncompletas(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'quiero un turno',
            'routing_hint' => 'pedido_claro',
            'tags' => ['pedido_turno_sin_destino'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ], 0);

        $decision = $evaluation->decision;
        $this->assertTrue($decision->isIncompletas());
        $this->assertFalse($decision->shouldRouteIntentDirectly());
        $this->assertSame('agenda-pedido-sin-destino', $decision->catalogEntry?->id);
        $this->assertSame(
            ['turnos.crear-como-paciente', 'atencion.necesito-atencion'],
            $decision->catalogEntry?->ctaIntentIds
        );
    }

    public function testEstudioRoutesAtencionNotAgendaPura(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'necesito una ecografia',
            'routing_hint' => 'pedido_claro',
            'tags' => ['estudio'],
            'context_areas' => [],
            'extractions' => [],
        ], 0);

        $decision = $evaluation->decision;
        $this->assertSame('clara', $decision->routingResult);
        $this->assertTrue($decision->shouldRouteIntentDirectly());
        $this->assertSame('atencion.necesito-atencion', $decision->primaryIntentId());
    }

    public function testMisTurnosNotCrearTurno(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'cuales son mis turnos',
            'routing_hint' => 'pedido_claro',
            'tags' => ['mis_turnos'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ], 0);

        $decision = $evaluation->decision;
        $this->assertSame('clara', $decision->routingResult);
        $this->assertTrue($decision->shouldRouteIntentDirectly());
        $this->assertSame('turnos.ver-mis-turnos-como-paciente', $decision->primaryIntentId());
    }

    public function testTurnosBareWordWithoutDiscriminatorGoesIncompletas(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'turnos',
            'routing_hint' => 'pedido_claro',
            'tags' => ['appointments'],
            'context_areas' => ['appointments'],
            'intent_ids_hint' => [],
            'extractions' => [],
        ], 0);

        $decision = $evaluation->decision;
        $this->assertTrue($decision->isIncompletas());
        $this->assertFalse($decision->shouldRouteIntentDirectly());
        $this->assertSame([], $decision->intentIds);
    }

    public function testEfectoAdversoMatchesAtencionNotTurnosCluster(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'quiero informar un efecto adverso de una medicacion',
            'necesidad_usuario' => 'Informar un efecto adverso de una medicación.',
            'routing_hint' => 'pedido_claro',
            'tags' => ['sintoma', 'medicamento', 'necesito_atencion', 'appointments'],
            'context_areas' => ['appointments', 'medication'],
            'intent_ids_hint' => [
                'atencion.necesito-atencion',
                'turnos.crear-como-paciente',
                'turnos.ver-ultimo-en-oferta-como-paciente',
            ],
            'extractions' => [
                ['span' => 'medicación', 'category' => 'servicio', 'synonyms' => []],
            ],
        ], 0);

        $decision = $evaluation->decision;
        $this->assertSame('clara', $decision->routingResult);
        $this->assertTrue($decision->shouldRouteIntentDirectly());
        $this->assertSame('atencion.necesito-atencion', $decision->primaryIntentId());
    }

    public function testDudosaWhenNoMatch(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'hola',
            'routing_hint' => 'sin_pedido',
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
            'routing_hint' => 'pedido_claro',
            'tags' => ['llegar_tarde', 'appointments'],
            'context_areas' => ['appointments'],
            'extractions' => [
                ['span' => '10 minutos', 'category' => 'servicio', 'synonyms' => []],
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
