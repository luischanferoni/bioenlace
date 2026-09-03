<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Channels\Ambiguous\AmbiguousChannel;
use common\components\Platform\Assistant\Chat\Channels\Ambiguous\AmbiguousChannelConfig;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;
use common\components\Platform\Assistant\Preprocess\PreprocessRoutingHintCatalog;
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

    public function testCanonicalizeGoalRejectsLegacyChannels(): void
    {
        $this->assertSame('ambiguous', ChatPreprocessService::canonicalizeGoal('clinical'));
        $this->assertSame('ambiguous', ChatPreprocessService::canonicalizeGoal('informational'));
        $this->assertSame('ambiguous', ChatPreprocessService::canonicalizeGoal('meta'));
        $this->assertSame('ambiguous', ChatPreprocessService::canonicalizeGoal('conversational'));
        $this->assertSame('guide', ChatPreprocessService::canonicalizeGoal('guide'));
        $this->assertSame('operational', ChatPreprocessService::canonicalizeGoal('operational'));
    }

    public function testGoalsIncludeGuideLegacyAliasForThread(): void
    {
        $this->assertContains('guide', ChatPreprocessService::legacyGoals());
        $this->assertSame('incompletas', ChatPreprocessService::routingHintFromLegacyGoal('guide'));
        $this->assertContains('incompletas', PreprocessRoutingHintCatalog::all());
    }

    public function testStablePromptListsRoutingHints(): void
    {
        $prompt = ChatPreprocessService::stablePromptPrefix();
        $this->assertStringContainsString('- incompletas —', $prompt);
        $this->assertStringContainsString('routing_hint', $prompt);
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

    public function testSteerIntentPrefixGuide(): void
    {
        $this->assertTrue(AmbiguousChannelConfig::isSteerIntentId('assistant.channel.guide'));
        $this->assertSame(
            'guide',
            AmbiguousChannelConfig::channelFromSteerIntentId('assistant.channel.guide')
        );
        $this->assertFalse(AmbiguousChannelConfig::isSteerIntentId('atencion.necesito-atencion'));
    }

    public function testLateArrivalPolicyQuestionKeepsGuideThread(): void
    {
        $msg = 'voy a tener problemas si llego 10min tarde al turno?';

        AssistantThreadStateService::observe(42, 'guide', 'me duele la cabeza');

        $observed = AssistantThreadStateService::observe(42, 'guide', $msg);

        $this->assertSame('guide', $observed['goal']);
        $this->assertSame('guide', $observed['thread_tag']);
    }

    public function testDispatchLateArrivalUsesSmartCatalogNotAmbiguousSteering(): void
    {
        $msg = 'voy a tener problemas si llego 10min tarde al turno?';
        \common\components\Platform\Assistant\Chat\ChatPreprocessContext::set([
            'ok' => true,
            'normalized_text' => $msg,
            'routing_hint' => 'incompletas',
            'tags' => ['llegar_tarde', 'appointments'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ]);

        $out = ChatRouter::dispatchByGoal('guide', $msg, 0);

        $this->assertNotSame('interactive', $out['kind'] ?? null);
        $this->assertStringNotContainsString('Es sobre mi salud o malestar', (string) ($out['text'] ?? ''));
    }

    public function testChatRouterDoesNotReferenceGuideChannel(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/components/Platform/Assistant/Chat/Routing/ChatRouter.php'
        );
        $this->assertIsString($source);
        $this->assertStringNotContainsString('GuideChannel::handle', $source);
    }
}
