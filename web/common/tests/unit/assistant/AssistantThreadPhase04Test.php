<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\Chat\Thread\AssistantThreadContext;
use common\components\Platform\Assistant\Chat\Thread\AssistantThreadStateService;
use common\components\Platform\Core\Product\ProductMetadataPaths;

class AssistantThreadPhase04Test extends Unit
{
    protected function _before(): void
    {
        AssistantThreadStateService::resetCacheForTests();
    }

    protected function _after(): void
    {
        AssistantThreadStateService::resetCacheForTests();
        ChatPreprocessContext::clear();
    }

    public function testGuideFocusWithContextAreas(): void
    {
        ChatPreprocessContext::set([
            'ok' => true,
            'normalized_text' => 'llego tarde',
            'user_goal' => 'guide',
            'action_text' => '',
            'context_areas' => ['appointments'],
            'extractions' => [],
        ]);
        $r = AssistantThreadStateService::observe(0, 'guide', 'llego 10 min tarde');
        $this->assertSame('guide:appointments', $r['thread_tag']);
        $focus = AssistantThreadContext::guideFocus();
        $this->assertNotNull($focus);
        $this->assertSame('appointments', $focus['primary_area']);
    }

    public function testThreadStateYamlExists(): void
    {
        $this->assertFileExists(ProductMetadataPaths::threadStateFile());
    }

    public function testTagFromGoal(): void
    {
        $this->assertSame('guide', AssistantThreadStateService::tagFromGoal('guide'));
        $this->assertSame('ambiguous', AssistantThreadStateService::tagFromGoal('clinical'));
        $this->assertSame('ambiguous', AssistantThreadStateService::tagFromGoal('informational'));
        $this->assertSame('operational', AssistantThreadStateService::tagFromGoal('operational'));
    }

    public function testGuideSymptomRaisesConfidenceAndCta(): void
    {
        $r = AssistantThreadStateService::observe(0, 'guide', 'me duele la cabeza');
        $this->assertSame('guide', $r['goal']);
        $this->assertStringStartsWith('guide', $r['thread_tag']);
        $this->assertTrue($r['offer_cta']);
        $this->assertFalse($r['clear_history']);
        $this->assertTrue(AssistantThreadContext::offerCta());
    }

    public function testDiversionToOperationalDoesNotForceAmbiguous(): void
    {
        AssistantThreadStateService::observe(0, 'guide', 'tengo fiebre');
        $r = AssistantThreadStateService::observe(0, 'operational', 'cancelar mi turno');
        $this->assertSame('operational', $r['goal']);
        $this->assertSame('operational', $r['thread_tag']);
        $this->assertTrue($r['clear_history']);
    }

    public function testFromAmbiguousDoesNotCountAsDiversion(): void
    {
        AssistantThreadStateService::observe(0, 'ambiguous', 'asdf');
        $r = AssistantThreadStateService::observe(0, 'guide', 'me duele');
        $this->assertSame('guide', $r['goal']);
        $this->assertFalse($r['clear_history']);
    }

    public function testMetadataRoundtrip(): void
    {
        $this->assertSame(
            'guide',
            AssistantThreadStateService::threadTagFromMetadata(['thread_tag' => 'guide'])
        );
        $this->assertSame('', AssistantThreadStateService::threadTagFromMetadata(null));
    }
}
