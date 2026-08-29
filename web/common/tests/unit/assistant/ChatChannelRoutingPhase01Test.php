<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Channels\Ambiguous\AmbiguousChannel;
use common\components\Platform\Assistant\Chat\Channels\Ambiguous\AmbiguousChannelConfig;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;
use common\components\Platform\Assistant\Chat\Routing\ChatRouter;
use common\components\Platform\Assistant\Chat\Thread\AssistantThreadStateService;

class ChatChannelRoutingPhase01Test extends Unit
{
    protected function _before()
    {
        AmbiguousChannelConfig::resetCacheForTests();
        AssistantThreadStateService::resetCacheForTests();
    }

    protected function _after()
    {
        AmbiguousChannelConfig::resetCacheForTests();
        AssistantThreadStateService::resetCacheForTests();
    }

    public function testCanonicalizeGoalAliases(): void
    {
        $this->assertSame('clinical', ChatPreprocessService::canonicalizeGoal('conversational'));
        $this->assertSame('clinical', ChatPreprocessService::canonicalizeGoal('conversational_clinical'));
        $this->assertSame('informational', ChatPreprocessService::canonicalizeGoal('informational'));
        $this->assertSame('informational', ChatPreprocessService::canonicalizeGoal('informational_conversational'));
        $this->assertSame('ambiguous', ChatPreprocessService::canonicalizeGoal('unclear'));
        $this->assertSame('ambiguous', ChatPreprocessService::canonicalizeGoal('ambiguous_conversational'));
        $this->assertSame('ambiguous', ChatPreprocessService::canonicalizeGoal('no-existe'));
        $this->assertSame('operational', ChatPreprocessService::canonicalizeGoal('operational'));
    }

    public function testGoalsIncludeRenamedChannels(): void
    {
        $this->assertContains('clinical', ChatPreprocessService::GOALS);
        $this->assertContains('informational', ChatPreprocessService::GOALS);
        $this->assertContains('ambiguous', ChatPreprocessService::GOALS);
        $this->assertNotContains('unclear', ChatPreprocessService::GOALS);
        $this->assertNotContains('conversational', ChatPreprocessService::GOALS);
        $this->assertNotContains('conversational_clinical', ChatPreprocessService::GOALS);
    }

    public function testStablePromptListsNewGoals(): void
    {
        $prompt = ChatPreprocessService::stablePromptPrefix();
        $this->assertStringContainsString('"clinical"', $prompt);
        $this->assertStringContainsString('"informational"', $prompt);
        $this->assertStringContainsString('"ambiguous"', $prompt);
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
        $out = ChatRouter::dispatchByGoal('ambiguous', 'asdf qwerty', 0);
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
        $this->assertTrue(AmbiguousChannelConfig::isSteerIntentId('assistant.channel.clinical'));
        $this->assertSame(
            'clinical',
            AmbiguousChannelConfig::channelFromSteerIntentId('assistant.channel.clinical')
        );
        $this->assertSame(
            'clinical',
            ChatPreprocessService::canonicalizeGoal(
                AmbiguousChannelConfig::channelFromSteerIntentId('assistant.channel.conversational_clinical')
            )
        );
        $this->assertFalse(AmbiguousChannelConfig::isSteerIntentId('atencion.necesito-atencion'));
    }

    public function testLateArrivalPolicyQuestionDoesNotForceAmbiguousAfterClinicalThread(): void
    {
        $msg = 'voy a tener problemas si llego 10min tarde al turno?';

        AssistantThreadStateService::observe(42, 'clinical', 'me duele la cabeza');

        $observed = AssistantThreadStateService::observe(42, 'informational', $msg);

        $this->assertSame('informational', $observed['goal']);
        $this->assertSame('product_help', $observed['thread_tag']);
    }

    public function testDispatchLateArrivalPolicyQuestionIsNotAmbiguousSteering(): void
    {
        $msg = 'voy a tener problemas si llego 10min tarde al turno?';
        $out = ChatRouter::dispatchByGoal('informational', $msg, 0);

        $this->assertNotSame('interactive', $out['kind'] ?? null);
        $this->assertStringNotContainsString('Es sobre mi salud o malestar', (string) ($out['text'] ?? ''));
    }
}
