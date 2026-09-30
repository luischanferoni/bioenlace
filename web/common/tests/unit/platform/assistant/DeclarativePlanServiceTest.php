<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Context\AssistantContextAnchorBag;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectCatalog;
use common\components\Platform\Assistant\Context\AssistantContextHISAreaAspect;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Planning\AssistantFirstIaAdapter;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\DeclarativePlanService;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingService;

class DeclarativePlanServiceTest extends Unit
{
    protected function _after(): void
    {
        AssistantContextAreaAspectCatalog::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
        AssistantPlanningLogService::resetForTests();
    }

    public function testPlanAppointmentsIncludesPolicyAspects(): void
    {
        $anchors = new AssistantContextAnchorBag();
        $anchors->subjectPersonaId = 1;

        $plan = DeclarativePlanService::plan(
            ['scheduling'],
            [['span' => '10 minutos tarde', 'category' => 'servicio', 'synonyms' => []]],
            $anchors
        );

        $this->assertContains(
            'aspect:' . AssistantContextHISAreaAspect::SITE_APPOINTMENT_POLICIES,
            $plan->toolIds
        );
        $this->assertContains(
            'aspect:' . AssistantContextHISAreaAspect::APPOINTMENT_CURRENT,
            $plan->toolIds
        );
        $this->assertFalse($plan->needsPlanner);
    }

    public function testEmptyPlanDoesNotRequestPlanner(): void
    {
        $plan = DeclarativePlanService::plan([], [], new AssistantContextAnchorBag());

        $this->assertSame([], $plan->toolIds);
        $this->assertFalse($plan->needsPlanner);
        $this->assertNull($plan->plannerReason);
    }

    public function testFirstIaAdapterKeepsOnlyIaTags(): void
    {
        $first = AssistantFirstIaAdapter::fromPreprocess([
            'normalized_text' => 'Me duele la cabeza',
            'user_goal' => 'guide',
            'routing_hint' => 'guide',
            'tags' => ['scheduling'],
            'extractions' => [],
        ]);

        $this->assertSame(['scheduling'], $first['tags']);
    }

    public function testFirstIaAdapterDoesNotInventTagsWhenIaReturnsNone(): void
    {
        $first = AssistantFirstIaAdapter::fromPreprocess([
            'normalized_text' => '¿Voy a tener problemas si llego 10 minutos tarde?',
            'user_goal' => 'guide',
            'tags' => [],
            'extractions' => [],
        ]);

        $this->assertSame([], $first['tags']);
    }

    public function testAdapterIgnoresInvalidAiContextAreas(): void
    {
        $first = AssistantFirstIaAdapter::fromPreprocess([
            'normalized_text' => 'Tengo un pinchazo en el pecho cuando respiro',
            'user_goal' => 'guide',
            'tags' => ['sintoma'],
            'context_areas' => ['clinical_record', 'no_existe'],
            'extractions' => [],
        ]);

        $this->assertSame([], $first['context_areas']);
        $this->assertSame(['sintoma'], $first['tags']);
    }

    public function testRoutingDerivesNoContextAreasFromDiscovery(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'Me duele la cabeza',
            'user_goal' => 'guide',
            'routing_hint' => 'guide',
            'tags' => ['sintoma', 'necesito_atencion'],
            'context_areas' => [],
            'extractions' => [],
        ], 1);

        $this->assertSame([], $evaluation->firstIa['context_areas']);
    }

    public function testFirstIaAdapterDoesNotInferMisAnalisis(): void
    {
        $first = AssistantFirstIaAdapter::fromPreprocess([
            'normalized_text' => 'Mis análisis',
            'user_goal' => 'operational',
            'tags' => [],
            'context_areas' => [],
            'extractions' => [],
        ]);

        $this->assertSame([], $first['tags']);
    }

    public function testRoutingCancelIsClaraNotAgendaCtas(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'Cancelá el turno del martes',
            'user_goal' => 'operational',
            'routing_hint' => 'guide',
            'tags' => ['cancelar_turno', 'scheduling'],
            'context_areas' => ['scheduling'],
            'extractions' => [],
        ], 1);

        $this->assertTrue($evaluation->decision->isMatch100());
        $this->assertSame('turnos.cancelar-como-paciente-flow', $evaluation->decision->primaryIntentId());
    }

    public function testRoutingSintomaIsIncompletasWithAtencionCta(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'Me duele la cabeza',
            'user_goal' => 'guide',
            'routing_hint' => 'guide',
            'tags' => ['sintoma', 'necesito_atencion'],
            'context_areas' => [],
            'extractions' => [],
        ], 1);

        $this->assertContains(
            $evaluation->decision->routingResult,
            ['incompletas', 'clara']
        );
        $this->assertContains('atencion.necesito-atencion', $evaluation->decision->intentIds);
        $this->assertSame([], $evaluation->declarativePlan->toolIds);
    }

    public function testRoutingMisAnalisisIsClaraLab(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'Mis análisis',
            'user_goal' => 'operational',
            'routing_hint' => 'guide',
            'tags' => ['laboratorio'],
            'context_areas' => ['clinical'],
            'extractions' => [
                ['span' => 'análisis', 'synonyms' => []],
            ],
        ], 1);

        $this->assertTrue($evaluation->decision->isMatch100());
        $this->assertSame('laboratorio.ver-resultados-como-paciente', $evaluation->decision->primaryIntentId());
    }

    public function testFirstIaAdapterDoesNotInferEstudio(): void
    {
        $first = AssistantFirstIaAdapter::fromPreprocess([
            'normalized_text' => 'necesito una ecografia',
            'tags' => [],
            'context_areas' => [],
            'extractions' => [],
        ]);

        $this->assertSame([], $first['tags']);
    }

    public function testRoutingFueraDeHisWhenIaTagsIt(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'necesito una sesion con una medium',
            'user_goal' => 'ambiguous',
            'routing_hint' => 'fuera_his',
            'tags' => ['fuera_his'],
            'context_areas' => [],
            'extractions' => [],
        ], 0);

        $this->assertTrue($evaluation->decision->isFueraDeHis());
        $snap = AssistantPlanningLogService::snapshot();
        $this->assertSame('fuera_de_his', $snap['routing_result'] ?? null);
    }

    public function testBareTurnoOrientationPlanHasNoHisTools(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'Quiero un turno',
            'user_goal' => 'guide',
            'routing_hint' => 'guide',
            'tags' => ['pedido_turno_sin_destino', 'scheduling'],
            'context_areas' => ['scheduling'],
            'extractions' => [],
        ], 1);

        $this->assertTrue($evaluation->decision->isIncompletas());
    }
}
