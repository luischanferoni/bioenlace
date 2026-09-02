<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\Chat\Channels\Planner\PlannerChannelConfig;
use common\components\Platform\Assistant\Chat\Routing\Handlers\IncompleteRoutingHandler;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectCatalog;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\PlannerPlanResult;
use common\components\Platform\Assistant\Planning\PlannerRoutingStep;
use common\components\Platform\Assistant\Planning\PlannerShortlistBuilder;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingService;
use Yii;

class PlannerRoutingStepTest extends Unit
{
    protected function _after(): void
    {
        SmartCatalogRegistry::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
        AssistantContextAreaAspectCatalog::resetCacheForTests();
        AssistantPlanningLogService::resetForTests();
        PlannerRoutingStep::setOverrideForTests(null);
        PlannerChannelConfig::resetCacheForTests();
        ChatPreprocessContext::set([]);
        Yii::$app->params['asistente_planner_enabled'] = true;
    }

    public function testNormalizeFromAiRejectsToolsOutsideShortlist(): void
    {
        $result = PlannerRoutingStep::normalizeFromAi(
            [
                'tool_ids_ordered' => [
                    'aspect:site.appointment.policies',
                    'aspect:forbidden.tool',
                ],
                'rationale' => 'políticas',
            ],
            ['aspect:site.appointment.policies']
        );

        $this->assertInstanceOf(PlannerPlanResult::class, $result);
        $this->assertSame(['aspect:site.appointment.policies'], $result->toolIdsOrdered);
    }

    public function testShortlistForLlegarTardeIncludesAppointmentAspects(): void
    {
        $firstIa = [
            'normalized_text' => '¿Voy a tener problemas si llego 10 minutos tarde?',
            'necesidad_usuario' => 'Saber tolerancia de llegada tarde.',
            'tags' => ['llegar_tarde', 'appointments'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ];
        $match = SmartCatalogRoutingService::evaluate($firstIa, 0)->match;

        $shortlist = PlannerShortlistBuilder::build($firstIa, $match, 0);
        $toolIds = array_column($shortlist, 'tool_id');

        $this->assertContains('aspect:site.appointment.policies', $toolIds);
        $this->assertContains('aspect:appointment.current', $toolIds);
    }

    public function testPlannerFlowRecordsGapsAndFinalPath(): void
    {
        ChatPreprocessContext::set([
            'ok' => true,
            'normalized_text' => 'consulta vaga sobre turnos',
            'context_areas' => ['appointments'],
            'extractions' => [],
        ]);

        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'consulta vaga sobre turnos',
            'routing_hint' => 'incompletas',
            'necesidad_usuario' => 'Entender algo sobre mis turnos.',
            'tags' => ['appointments'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ], 0);

        AssistantPlanningLogService::resetForTests();
        AssistantPlanningLogService::begin($evaluation->firstIa, $evaluation->match->ranked);
        AssistantPlanningLogService::setDeclarativePlan([], 'empty', true);

        PlannerRoutingStep::setOverrideForTests(
            static function (array $firstIa, array $shortlist, array $declarativeToolIds): PlannerPlanResult {
                return new PlannerPlanResult(
                    [
                        'aspect:site.appointment.policies',
                        'aspect:appointment.current',
                    ],
                    'políticas y cita actual'
                );
            }
        );

        Yii::$app->params['asistente_planner_enabled'] = true;

        $envelope = IncompleteRoutingHandler::handle(
            new \common\components\Platform\Assistant\Planning\SmartCatalogRoutingEvaluation(
                $evaluation->firstIa,
                $evaluation->match,
                $evaluation->decision,
                new \common\components\Platform\Assistant\Planning\DeclarativePlanResult(
                    [],
                    'empty',
                    true,
                    'empty_plan'
                )
            ),
            'consulta vaga sobre turnos',
            0
        );

        if ($envelope === null) {
            $this->markTestSkipped('Síntesis no disponible en este entorno (IAManager).');
        }

        $snap = AssistantPlanningLogService::snapshot();
        $this->assertTrue($snap['planner_invoked'] ?? false);
        $this->assertContains('aspect:appointment.current', $snap['gaps'] ?? []);
        $this->assertSame('3ia_planner_synthesis', $snap['final_path'] ?? null);
    }

    public function testGapsComputedAgainstDeclarativePlan(): void
    {
        AssistantPlanningLogService::begin([
            'normalized_text' => 'test',
            'necesidad_usuario' => 'test',
            'routing_hint' => 'incompletas',
            'tags' => [],
            'context_areas' => [],
            'extractions' => [],
            'intent_ids_hint' => [],
        ], []);
        AssistantPlanningLogService::setDeclarativePlan(
            ['aspect:site.appointment.policies'],
            'areas',
            true
        );
        AssistantPlanningLogService::setPlannerPlan(
            ['aspect:site.appointment.policies', 'aspect:appointment.current'],
            'necesita cita',
            'empty_plan'
        );

        $snap = AssistantPlanningLogService::snapshot();
        $this->assertSame(['aspect:appointment.current'], $snap['gaps'] ?? null);
    }
}
