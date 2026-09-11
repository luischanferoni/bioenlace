<?php

namespace common\tests\unit\platform\assistant;

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
        $this->assertStringContainsString('scheduling', $prompt);
        $this->assertStringContainsString('Citas, agenda y turnos', $prompt);
    }

    public function testNormalizeContextAreasFiltersInvalid(): void
    {
        $areas = ChatPreprocessService::normalizeContextAreas([
            'scheduling',
            'invalid_area',
            'product',
            'scheduling',
        ]);

        $this->assertSame(['scheduling', 'product'], $areas);
    }

    public function testNormalizeContextAreasEmptyForNonArray(): void
    {
        $this->assertSame([], ChatPreprocessService::normalizeContextAreas(null));
    }

    public function testNormalizeContextAreasEmptyForGreetingScenario(): void
    {
        $this->assertSame([], ChatPreprocessService::normalizeContextAreas([]));
    }

    public function testCatalogListsDomainAndContextOnlyAreas(): void
    {
        $this->assertCount(7, AssistantContextHISArea::all());
        $this->assertTrue(AssistantContextHISArea::isContextOnly(AssistantContextHISArea::PRODUCT));
        $this->assertTrue(AssistantContextHISArea::isContextOnly(AssistantContextHISArea::GEO_RESOURCES));
        $this->assertFalse(AssistantContextHISArea::isContextOnly(AssistantContextHISArea::SCHEDULING));
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
            [AssistantContextHISArea::SCHEDULING],
            [
                ['span' => '10 minutos tarde', 'category' => 'servicio', 'synonyms' => []],
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
            [AssistantContextHISArea::SCHEDULING],
            [
                ['span' => 'última vez que fui', 'category' => 'servicio', 'synonyms' => []],
            ],
            'guide',
            $anchors
        );

        $this->assertContains(AssistantContextHISAreaAspect::APPOINTMENT_HISTORY_SUBJECT_AT_SITE, $plan->aspectKeys);
    }
}
