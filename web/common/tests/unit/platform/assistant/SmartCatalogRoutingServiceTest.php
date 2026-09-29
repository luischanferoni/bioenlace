<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\IntentSchemaPaths;
use common\components\Platform\Assistant\Catalog\StateTagIndex;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectCatalog;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingService;

class SmartCatalogRoutingServiceTest extends Unit
{
    protected function _before(): void
    {
        StateTagIndex::resetCacheForTests();
        IntentSchemaPaths::resetIndexCache();
    }

    protected function _after(): void
    {
        AssistantMetadataLoader::resetCacheForTests();
        AssistantContextAreaAspectCatalog::resetCacheForTests();
        AssistantPlanningLogService::resetForTests();
        StateTagIndex::resetCacheForTests();
        IntentSchemaPaths::resetIndexCache();
    }

    public function testRepresentacionGoesToGuideNotDirectDoor(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'contame sobre representacion de mi hijo',
            'routing_hint' => 'guide',
            'tags' => ['representacion', 'tutela'],
            'context_areas' => ['person'],
            'extractions' => [],
        ], 0);

        $decision = $evaluation->decision;
        $this->assertFalse($decision->isFueraDeHis());
        $this->assertFalse($decision->isDudosa());
        $this->assertContains($decision->routingResult, ['incompletas', 'clara']);
    }

    public function testClaraSingleIntentTurnosConDestino(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'quiero un turno con el cardiologo',
            'routing_hint' => 'guide',
            'tags' => ['turno'],
            'context_areas' => ['scheduling'],
            'extractions' => [
                ['span' => 'cardiólogo', 'synonyms' => []],
            ],
        ], 0);

        $decision = $evaluation->decision;
        $this->assertTrue($decision->isIncompletas());
        $this->assertFalse($decision->shouldRouteIntentDirectly());
        $ids = $decision->intentIds;
        sort($ids);
        $this->assertSame(
            ['atencion.necesito-atencion', 'turnos.crear-como-paciente'],
            $ids
        );
    }

    public function testBareQuieroUnTurnoGoesIncompletas(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'quiero un turno',
            'routing_hint' => 'guide',
            'tags' => ['turno'],
            'context_areas' => ['scheduling'],
            'extractions' => [
                ['span' => 'turno', 'synonyms' => []],
            ],
        ], 0);

        $decision = $evaluation->decision;
        $this->assertTrue($decision->isIncompletas());
        $this->assertFalse($decision->shouldRouteIntentDirectly());
        $ids = $decision->intentIds;
        sort($ids);
        $this->assertSame(
            ['atencion.necesito-atencion', 'turnos.crear-como-paciente'],
            $ids
        );
    }

    public function testEstudioRoutesAtencionNotAgendaPura(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'necesito una ecografia',
            'routing_hint' => 'guide',
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
            'routing_hint' => 'guide',
            'tags' => ['mis_turnos'],
            'context_areas' => ['scheduling'],
            'extractions' => [
                ['span' => 'turnos', 'synonyms' => []],
            ],
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
            'routing_hint' => 'guide',
            'tags' => ['scheduling'],
            'context_areas' => ['scheduling'],
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
            'routing_hint' => 'guide',
            'tags' => ['sintomas'],
            'context_areas' => ['scheduling', 'medication'],
            'intent_ids_hint' => [
                'atencion.necesito-atencion',
                'turnos.crear-como-paciente',
                'turnos.ver-ultimo-en-oferta-como-paciente',
            ],
            'extractions' => [
                ['span' => 'medicación', 'synonyms' => []],
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
            'normalized_text' => 'xyzzy',
            'routing_hint' => 'sin_pedido',
            'tags' => [],
            'context_areas' => [],
            'extractions' => [],
        ], 0);

        $this->assertTrue($evaluation->decision->isDudosa());
    }

    public function testGreetingOnlyRoutesToDudosaWithoutGuide(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'hola',
            'routing_hint' => 'sin_pedido',
            'tags' => [],
            'context_areas' => [],
            'extractions' => [],
        ], 0, 'Hola');

        $this->assertTrue($evaluation->decision->isDudosa());
        $this->assertSame('ambiguous', $evaluation->decision->legacyUserGoal);
    }

    public function testLlegarTardeLoadsPoliciesViaDiscoveryIntent(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => '¿Voy a tener problemas si llego 10 minutos tarde?',
            'routing_hint' => 'guide',
            'tags' => ['llegar_tarde', 'tolerancia'],
            'context_areas' => [],
            'extractions' => [
                ['span' => '10 minutos', 'synonyms' => []],
            ],
        ], 0);

        $this->assertTrue($evaluation->decision->shouldRouteIntentDirectly());
        $this->assertSame(
            'turnos.consultar-politica-autogestion-flow',
            $evaluation->decision->primaryIntentId()
        );
        $this->assertSame([], $evaluation->firstIa['context_areas']);
    }
}
