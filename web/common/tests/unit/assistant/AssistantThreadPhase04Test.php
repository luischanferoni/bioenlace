<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
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
    }

    public function testThreadStateYamlExists(): void
    {
        $this->assertFileExists(ProductMetadataPaths::threadStateFile());
    }

    public function testTagFromGoal(): void
    {
        $this->assertSame('clinical', AssistantThreadStateService::tagFromGoal('conversational_clinical'));
        $this->assertSame('product_help', AssistantThreadStateService::tagFromGoal('informational'));
        $this->assertSame('ambiguous', AssistantThreadStateService::tagFromGoal('unclear'));
        $this->assertSame('operational', AssistantThreadStateService::tagFromGoal('operational'));
    }

    public function testClinicalSymptomRaisesConfidenceAndCta(): void
    {
        $r = AssistantThreadStateService::observe(0, 'conversational_clinical', 'me duele la cabeza');
        $this->assertSame('conversational_clinical', $r['goal']);
        $this->assertSame('clinical', $r['thread_tag']);
        $this->assertTrue($r['offer_cta']);
        $this->assertFalse($r['clear_history']);
        $this->assertTrue(AssistantThreadContext::offerCta());
    }

    public function testDiversionToProductHelpForcesAmbiguous(): void
    {
        AssistantThreadStateService::observe(0, 'conversational_clinical', 'tengo fiebre');
        $r = AssistantThreadStateService::observe(0, 'informational_conversational', 'cómo saco un turno');
        $this->assertSame('ambiguous_conversational', $r['goal']);
        $this->assertSame('ambiguous', $r['thread_tag']);
        $this->assertTrue($r['clear_history']);
        $this->assertTrue(AssistantThreadContext::diverted());
    }

    public function testDiversionToOperationalDoesNotForceAmbiguous(): void
    {
        AssistantThreadStateService::observe(0, 'conversational_clinical', 'tengo fiebre');
        $r = AssistantThreadStateService::observe(0, 'operational', 'cancelar mi turno');
        $this->assertSame('operational', $r['goal']);
        $this->assertSame('operational', $r['thread_tag']);
        $this->assertTrue($r['clear_history']);
    }

    public function testFromAmbiguousDoesNotCountAsDiversion(): void
    {
        AssistantThreadStateService::observe(0, 'ambiguous_conversational', 'asdf');
        $r = AssistantThreadStateService::observe(0, 'conversational_clinical', 'me duele');
        $this->assertSame('conversational_clinical', $r['goal']);
        $this->assertFalse($r['clear_history']);
    }

    public function testMetadataRoundtrip(): void
    {
        $this->assertSame(
            'clinical',
            AssistantThreadStateService::threadTagFromMetadata(['thread_tag' => 'clinical'])
        );
        $this->assertSame(
            'product_help',
            AssistantThreadStateService::threadTagFromMetadata('{"thread_tag":"product_help"}')
        );
        $this->assertSame('', AssistantThreadStateService::threadTagFromMetadata(null));
    }
}
