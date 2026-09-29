<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectCatalog;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectResolver;
use common\components\Platform\Assistant\Context\AssistantContextAnchorBag;
use common\components\Platform\Assistant\Context\AssistantContextHISAreaAspect;

class AssistantContextAreasTest extends Unit
{
    protected function _after(): void
    {
        ChatPreprocessService::resetCacheForTests();
        AssistantContextAreaAspectCatalog::resetCacheForTests();
    }

    public function testNormalizeContextAreasAlwaysEmpty(): void
    {
        $this->assertSame([], ChatPreprocessService::normalizeContextAreas([
            'scheduling',
            'invalid_area',
            'product',
        ]));
        $this->assertSame([], ChatPreprocessService::normalizeContextAreas(null));
        $this->assertSame([], ChatPreprocessService::normalizeContextAreas([]));
    }

    public function testAreaAspectPlanForAppointmentsWithoutHistory(): void
    {
        $anchors = new AssistantContextAnchorBag();
        $anchors->subjectPersonaId = 1;
        $anchors->siteId = 7;

        $plan = AssistantContextAreaAspectResolver::plan(
            ['scheduling'],
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
            ['scheduling'],
            [
                ['span' => 'última vez que fui', 'category' => 'servicio', 'synonyms' => []],
            ],
            'guide',
            $anchors
        );

        $this->assertContains(AssistantContextHISAreaAspect::APPOINTMENT_HISTORY_SUBJECT_AT_SITE, $plan->aspectKeys);
    }
}
