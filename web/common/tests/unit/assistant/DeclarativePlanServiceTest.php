<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Context\AssistantContextAnchorBag;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectCatalog;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;
use common\components\Platform\Assistant\Context\AssistantContextHISAreaAspect;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Planning\AssistantFirstIaAdapter;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\DeclarativePlanService;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingService;
use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;

class DeclarativePlanServiceTest extends Unit
{
    protected function _after(): void
    {
        AssistantContextAreaAspectCatalog::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
        SmartCatalogRegistry::resetCacheForTests();
        AssistantPlanningLogService::resetForTests();
    }

    public function testPlanAppointmentsIncludesPolicyAspects(): void
    {
        $anchors = new AssistantContextAnchorBag();
        $anchors->subjectPersonaId = 1;

        $plan = DeclarativePlanService::plan(
            [AssistantContextHISArea::APPOINTMENTS],
            [['span' => '10 minutos tarde', 'category' => 'tiempo', 'synonyms' => []]],
            $anchors,
            null
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

    public function testFirstIaAdapterInfersLateArrivalTags(): void
    {
        $first = AssistantFirstIaAdapter::fromPreprocess([
            'normalized_text' => '¿Voy a tener problemas si llego 10 minutos tarde?',
            'user_goal' => 'guide',
            'context_areas' => ['appointments'],
            'extractions' => [],
        ]);

        $this->assertContains('llegar_tarde', $first['tags']);
        $this->assertSame('incompletas', $first['routing_hint']);
    }

    public function testRoutingFueraDeHisForMedium(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'necesito una sesion con una medium',
            'user_goal' => 'ambiguous',
            'context_areas' => [],
            'extractions' => [],
        ], 0);

        $this->assertTrue($evaluation->decision->isFueraDeHis());
        $snap = AssistantPlanningLogService::snapshot();
        $this->assertSame('fuera_de_his', $snap['routing_result'] ?? null);
    }
}
