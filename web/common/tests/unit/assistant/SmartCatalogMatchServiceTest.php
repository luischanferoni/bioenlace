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

    public function testBareTurnoDoesNotRankArticleWithoutTrigger(): void
    {
        $result = SmartCatalogMatchService::match([
            'normalized_text' => 'quiero un turno',
            'tags' => ['pedido_turno_sin_destino', 'appointments'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ], 0);

        $this->assertSame('agenda-pedido-sin-destino', $result->best?->id);
        $this->assertTrue($result->isClearWinner);
        $ids = array_column($result->ranked, 'catalog_id');
        $this->assertNotContains('articulo-representacion', $ids);
        $this->assertSame(
            ['turnos.crear-como-paciente', 'atencion.necesito-atencion'],
            $result->best?->ctaIntentIds
        );
    }

    public function testNoBaseScoreLeakWithoutTriggerHit(): void
    {
        $result = SmartCatalogMatchService::match([
            'normalized_text' => 'hola',
            'tags' => [],
            'context_areas' => [],
            'extractions' => [],
        ], 0);

        $this->assertTrue($result->isEmpty());
        $this->assertSame([], $result->ranked);
    }

    public function testHistorialTurnosBeatsMisTurnosProximos(): void
    {
        $result = SmartCatalogMatchService::match([
            'normalized_text' => 'Mostrame los turnos que ya tuve',
            'tags' => ['historial_turnos', 'appointments'],
            'context_areas' => ['appointments'],
        ], 0);

        $this->assertSame('turnos-historial-paciente', $result->best?->id);
        $this->assertSame('turnos.ver-turnos-anteriores-como-paciente', $result->best?->toolRef);
    }

    public function testPoliticaCancelacionBeatsCancelarFlow(): void
    {
        $result = SmartCatalogMatchService::match([
            'normalized_text' => '¿Hasta cuándo puedo cancelar?',
            'tags' => ['politica_turnos', 'appointments'],
            'context_areas' => ['appointments'],
        ], 0);

        $this->assertSame('turnos-politica-autogestion', $result->best?->id);
        $this->assertSame('turnos.consultar-politica-autogestion-flow', $result->best?->toolRef);
    }
}
