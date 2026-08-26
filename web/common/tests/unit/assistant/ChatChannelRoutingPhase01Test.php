<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Channels\Ambiguous\AmbiguousChannel;
use common\components\Platform\Assistant\Chat\Channels\Ambiguous\AmbiguousChannelConfig;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;
use common\components\Platform\Assistant\Chat\Routing\ChatRouter;

class ChatChannelRoutingPhase01Test extends Unit
{
    protected function _before()
    {
        AmbiguousChannelConfig::resetCacheForTests();
    }

    protected function _after()
    {
        AmbiguousChannelConfig::resetCacheForTests();
    }

    public function testCanonicalizeGoalAliases(): void
    {
        $this->assertSame('conversational_clinical', ChatPreprocessService::canonicalizeGoal('conversational'));
        $this->assertSame('informational_conversational', ChatPreprocessService::canonicalizeGoal('informational'));
        $this->assertSame('ambiguous_conversational', ChatPreprocessService::canonicalizeGoal('unclear'));
        $this->assertSame('ambiguous_conversational', ChatPreprocessService::canonicalizeGoal('no-existe'));
        $this->assertSame('operational', ChatPreprocessService::canonicalizeGoal('operational'));
    }

    public function testGoalsIncludeRenamedChannels(): void
    {
        $this->assertContains('conversational_clinical', ChatPreprocessService::GOALS);
        $this->assertContains('informational_conversational', ChatPreprocessService::GOALS);
        $this->assertContains('ambiguous_conversational', ChatPreprocessService::GOALS);
        $this->assertNotContains('unclear', ChatPreprocessService::GOALS);
        $this->assertNotContains('conversational', ChatPreprocessService::GOALS);
    }

    public function testStablePromptListsNewGoals(): void
    {
        $prompt = ChatPreprocessService::stablePromptPrefix();
        $this->assertStringContainsString('conversational_clinical', $prompt);
        $this->assertStringContainsString('informational_conversational', $prompt);
        $this->assertStringContainsString('ambiguous_conversational', $prompt);
        $this->assertStringContainsString('Alcance válido', $prompt);
    }

    public function testAmbiguousChannelEnvelopeFromMetadata(): void
    {
        $envelope = AmbiguousChannel::handle();
        $this->assertSame('interactive', $envelope['kind']);
        $this->assertNotSame('', trim((string) $envelope['text']));
        $this->assertNotEmpty($envelope['buttons']);
        foreach ($envelope['buttons'] as $btn) {
            $this->assertTrue(AmbiguousChannelConfig::isSteerIntentId((string) $btn['intent_id']));
            $this->assertNotSame('', trim((string) ($btn['content'] ?? '')));
        }
    }

    public function testDispatchAmbiguousReturnsInteractive(): void
    {
        $out = ChatRouter::dispatchByGoal('ambiguous_conversational', 'asdf qwerty', 0);
        $this->assertSame('interactive', $out['kind']);
        $this->assertNotEmpty($out['buttons']);
    }

    public function testDispatchLegacyUnclearMapsToAmbiguous(): void
    {
        $out = ChatRouter::dispatchByGoal('unclear', 'no se', 0);
        $this->assertSame('interactive', $out['kind']);
    }

    public function testSteerIntentPrefix(): void
    {
        $this->assertTrue(AmbiguousChannelConfig::isSteerIntentId('assistant.channel.conversational_clinical'));
        $this->assertSame(
            'conversational_clinical',
            AmbiguousChannelConfig::channelFromSteerIntentId('assistant.channel.conversational_clinical')
        );
        $this->assertFalse(AmbiguousChannelConfig::isSteerIntentId('atencion.necesito-atencion'));
    }
}
