<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\Chat\Routing\Handlers\IncompleteRoutingHandler;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectCatalog;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\DeclarativePlanExecutor;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingService;
use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;
use common\components\Platform\Ai\Cost\AICostTracker;

class IncompleteRoutingHandlerTest extends Unit
{
    protected function _before(): void
    {
        ChatPreprocessContext::set([
            'ok' => true,
            'normalized_text' => '¿Voy a tener problemas si llego 10 minutos tarde?',
            'user_goal' => 'guide',
            'context_areas' => ['appointments'],
            'extractions' => [
                ['span' => '10 minutos', 'category' => 'tiempo', 'synonyms' => []],
            ],
        ]);
    }

    protected function _after(): void
    {
        SmartCatalogRegistry::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
        AssistantContextAreaAspectCatalog::resetCacheForTests();
        AssistantPlanningLogService::resetForTests();
        ChatPreprocessContext::set([]);
        AICostTracker::finalizarEjecucionPrueba();
    }

    public function testLlegarTardeRoutesIncompletasWithDeclarativePlan(): void
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
        $this->assertNotEmpty($evaluation->declarativePlan->toolIds);
    }

    public function testDeclarativePlanExecutorLogsExecutedTools(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => '¿Voy a tener problemas si llego 10 minutos tarde?',
            'routing_hint' => 'incompletas',
            'tags' => ['llegar_tarde', 'appointments'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ], 0);

        AssistantPlanningLogService::resetForTests();
        AssistantPlanningLogService::begin($evaluation->firstIa, $evaluation->match->ranked);

        DeclarativePlanExecutor::execute($evaluation->declarativePlan->toolIds, 0);

        $snap = AssistantPlanningLogService::snapshot();
        $this->assertNotEmpty($snap['executed_tools'] ?? []);
    }

    public function testIncompleteHandlerSetsFinalPathWithSimulatedIa(): void
    {
        if (!class_exists(AICostTracker::class)) {
            $this->markTestSkipped('AICostTracker no disponible.');
        }

        AICostTracker::iniciarEjecucionPrueba();

        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => '¿Voy a tener problemas si llego 10 minutos tarde?',
            'routing_hint' => 'incompletas',
            'necesidad_usuario' => 'Saber si hay problema por llegar tarde.',
            'tags' => ['llegar_tarde', 'appointments'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ], 0);

        AssistantPlanningLogService::resetForTests();
        AssistantPlanningLogService::begin($evaluation->firstIa, $evaluation->match->ranked);
        AssistantPlanningLogService::setDeclarativePlan(
            $evaluation->declarativePlan->toolIds,
            $evaluation->declarativePlan->reason,
            $evaluation->declarativePlan->needsPlanner
        );

        $envelope = IncompleteRoutingHandler::handle(
            $evaluation,
            '¿Voy a tener problemas si llego 10 minutos tarde?',
            0
        );

        AICostTracker::finalizarEjecucionPrueba();

        if ($envelope === null) {
            $this->markTestSkipped('Síntesis no disponible en este entorno (IAManager).');
        }

        $this->assertContains($envelope['kind'] ?? null, ['message', 'interactive']);
        $snap = AssistantPlanningLogService::snapshot();
        $this->assertSame('2ia_synthesis', $snap['final_path'] ?? null);
    }
}
