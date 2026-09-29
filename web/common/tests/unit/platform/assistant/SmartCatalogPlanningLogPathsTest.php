<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\Chat\Routing\Handlers\IncompleteRoutingHandler;
use common\components\Platform\Assistant\Chat\Routing\Handlers\SmartCatalogRoutingHandlers;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectCatalog;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\DeclarativePlanResult;
use common\components\Platform\Assistant\Planning\PlannerPlanResult;
use common\components\Platform\Assistant\Planning\PlannerRoutingStep;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingEvaluation;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingService;
use common\components\Platform\Ai\Cost\AICostTracker;
use Yii;

/**
 * Verifica planning_applied en caminos discovery→Guide y planner.
 */
class SmartCatalogPlanningLogPathsTest extends Unit
{
    protected function _after(): void
    {
        AssistantMetadataLoader::resetCacheForTests();
        AssistantContextAreaAspectCatalog::resetCacheForTests();
        AssistantPlanningLogService::resetForTests();
        PlannerRoutingStep::setOverrideForTests(null);
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
            $evaluation->declarativePlan->reason,
            $evaluation->declarativePlan->needsPlanner
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

    public function testThreeIaPlannerGuideFinalPath(): void
    {
        ChatPreprocessContext::set([
            'ok' => true,
            'normalized_text' => 'consulta vaga turnos',
            'context_areas' => ['scheduling'],
            'extractions' => [],
        ]);

        $base = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'consulta vaga turnos',
            'routing_hint' => 'guide',
            'necesidad_usuario' => 'Entender algo sobre turnos.',
            'tags' => ['scheduling'],
            'context_areas' => ['scheduling'],
            'extractions' => [],
        ], 0);

        $evaluation = new SmartCatalogRoutingEvaluation(
            $base->firstIa,
            $base->match,
            $base->decision,
            new DeclarativePlanResult([], 'empty', true, 'empty_plan')
        );

        AssistantPlanningLogService::resetForTests();
        AssistantPlanningLogService::begin($evaluation->firstIa, $evaluation->match->ranked);
        AssistantPlanningLogService::setDeclarativePlan([], 'empty', true);

        PlannerRoutingStep::setOverrideForTests(
            static fn (): PlannerPlanResult => new PlannerPlanResult(
                ['aspect:site.appointment.policies'],
                'políticas'
            )
        );
        Yii::$app->params['asistente_planner_enabled'] = true;

        if (!class_exists(AICostTracker::class)) {
            $this->markTestSkipped('AICostTracker no disponible.');
        }
        AICostTracker::iniciarEjecucionPrueba();

        $envelope = IncompleteRoutingHandler::handle($evaluation, 'consulta vaga turnos', 0);
        AICostTracker::finalizarEjecucionPrueba();

        if ($envelope === null) {
            $this->markTestSkipped('Planner+guide no disponible en este entorno.');
        }

        $snap = AssistantPlanningLogService::snapshot();
        $this->assertTrue($snap['planner_invoked'] ?? false);
        $this->assertSame('3ia_planner_guide', $snap['final_path'] ?? null);
    }
}
