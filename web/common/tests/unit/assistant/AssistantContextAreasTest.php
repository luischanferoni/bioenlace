<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectCatalog;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectResolver;
use common\components\Platform\Assistant\Context\AssistantContextAnchorBag;
use common\components\Platform\Assistant\Context\AssistantContextHISAreaAspect;

class AssistantContextAreasTest extends Unit
{
    public function testStablePromptIncludesAreasCatalog(): void
    {
        $prompt = ChatPreprocessService::stablePromptPrefix();

        $this->assertStringContainsString('context_areas', $prompt);
        $this->assertStringContainsString('appointments', $prompt);
        $this->assertStringContainsString('Citas y turnos', $prompt);
    }

    public function testNormalizeContextAreasFiltersInvalid(): void
    {
        $areas = ChatPreprocessService::normalizeContextAreas([
            'appointments',
            'invalid_area',
            'product',
            'appointments',
        ]);

        $this->assertSame(['appointments', 'product'], $areas);
    }

    public function testNormalizeContextAreasEmptyForNonArray(): void
    {
        $this->assertSame([], ChatPreprocessService::normalizeContextAreas(null));
    }

    public function testNormalizeContextAreasEmptyForGreetingScenario(): void
    {
        $this->assertSame([], ChatPreprocessService::normalizeContextAreas([]));
    }

    public function testCatalogListsAllNineAreas(): void
    {
        $this->assertCount(9, AssistantContextHISArea::all());
    }

    protected function _after(): void
    {
        ChatPreprocessService::resetCacheForTests();
        AssistantContextAreaAspectCatalog::resetCacheForTests();
    }

    public function testAreaAspectPlanForAppointmentsWithoutHistory(): void
    {
        $anchors = new AssistantContextAnchorBag();
        $anchors->subjectPersonaId = 1;
        $anchors->siteId = 7;

        $plan = AssistantContextAreaAspectResolver::plan(
            [AssistantContextHISArea::APPOINTMENTS],
            [
                ['span' => '10 minutos tarde', 'category' => 'tiempo', 'synonyms' => []],
            ],
            'guide',
            $anchors
        );

        $this->assertContains(AssistantContextHISAreaAspect::APPOINTMENT_CURRENT, $plan->aspectKeys);
        $this->assertContains(AssistantContextHISAreaAspect::SITE_APPOINTMENT_POLICIES, $plan->aspectKeys);
        $this->assertNotContains(AssistantContextHISAreaAspect::APPOINTMENT_HISTORY_SUBJECT_AT_SITE, $plan->aspectKeys);
    }

    public function testAreaAspectPlanIncludesHistoryWhenAsked(): void
    {
        $anchors = new AssistantContextAnchorBag();
        $anchors->subjectPersonaId = 1;

        $plan = AssistantContextAreaAspectResolver::plan(
            [AssistantContextHISArea::APPOINTMENTS],
            [
                ['span' => 'última vez que fui', 'category' => 'turno', 'synonyms' => []],
            ],
            'guide',
            $anchors
        );

        $this->assertContains(AssistantContextHISAreaAspect::APPOINTMENT_HISTORY_SUBJECT_AT_SITE, $plan->aspectKeys);
    }
}
