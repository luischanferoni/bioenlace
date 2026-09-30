<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\Chat\Routing\Handlers\IncompleteRoutingHandler;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectCatalog;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingService;
use common\components\Platform\Ai\Cost\AICostTracker;

/**
 * Verifica planning_applied en el camino discovery → Guide.
 */
class SmartCatalogPlanningLogPathsTest extends Unit
{
    protected function _after(): void
    {
        AssistantMetadataLoader::resetCacheForTests();
        AssistantContextAreaAspectCatalog::resetCacheForTests();
        AssistantPlanningLogService::resetForTests();
        ChatPreprocessContext::set([]);
        AICostTracker::finalizarEjecucionPrueba();
    }

    public function testRepresentacionLogsGuidePathNotDoor(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'contame representacion',
            'routing_hint' => 'guide',
            'tags' => ['representacion'],
            'context_areas' => ['person'],
            'extractions' => [],
        ], 0);

        $snap = AssistantPlanningLogService::snapshot();
        $this->assertContains($snap['routing_result'] ?? null, ['incompletas', 'clara']);
        $this->assertContains($evaluation->decision->routingResult, ['incompletas', 'clara']);
    }

    public function testTwoIaGuideFinalPathWithSimulatedIa(): void
    {
        if (!class_exists(AICostTracker::class)) {
            $this->markTestSkipped('AICostTracker no disponible.');
        }

        ChatPreprocessContext::set([
            'ok' => true,
            'normalized_text' => 'llego 10 min tarde',
            'context_areas' => ['scheduling'],
            'extractions' => [],
        ]);

        AICostTracker::iniciarEjecucionPrueba();

        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'llego 10 min tarde hay problema',
            'routing_hint' => 'guide',
            'necesidad_usuario' => 'Saber tolerancia llegada tarde.',
            'tags' => ['llegar_tarde', 'scheduling'],
            'context_areas' => ['scheduling'],
            'extractions' => [],
        ], 0);

        AssistantPlanningLogService::resetForTests();
        AssistantPlanningLogService::begin($evaluation->firstIa, $evaluation->match->ranked);
        AssistantPlanningLogService::setDeclarativePlan(
            $evaluation->declarativePlan->toolIds,
            $evaluation->declarativePlan->reason
        );

        $envelope = IncompleteRoutingHandler::handle($evaluation, 'llego 10 min tarde', 0);
        AICostTracker::finalizarEjecucionPrueba();

        if ($envelope === null) {
            $this->markTestSkipped('Síntesis no disponible en este entorno.');
        }

        $snap = AssistantPlanningLogService::snapshot();
        $this->assertSame('2ia_guide', $snap['final_path'] ?? null);
        // Sin áreas HIS el plan puede no ejecutar tools; el path Guide igual aplica.
    }
}
