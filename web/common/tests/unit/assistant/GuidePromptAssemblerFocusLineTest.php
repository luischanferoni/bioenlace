<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideChannelConfig;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideFocusState;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuidePromptAssembler;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;

class GuidePromptAssemblerFocusLineTest extends Unit
{
    protected function _before(): void
    {
        GuideChannelConfig::resetCacheForTests();
        ChatPreprocessContext::clear();
    }

    protected function _after(): void
    {
        GuideChannelConfig::resetCacheForTests();
        ChatPreprocessContext::clear();
    }

    public function testIncludesDynamicFocusAreasFromPreprocess(): void
    {
        ChatPreprocessContext::set([
            'ok' => true,
            'normalized_text' => 'llego tarde',
            'user_goal' => 'guide',
            'action_text' => '',
            'context_areas' => [AssistantContextHISArea::APPOINTMENTS],
            'extractions' => [],
        ]);

        $prompt = GuidePromptAssembler::build(
            'llego tarde',
            0,
            new GuideFocusState(AssistantContextHISArea::APPOINTMENTS, [AssistantContextHISArea::APPOINTMENTS]),
            null,
            '',
            null
        );

        $this->assertStringContainsString('Ámbito de la consulta', $prompt);
        $this->assertStringContainsString('appointments', $prompt);
        $this->assertStringContainsString('Citas y turnos', $prompt);
        $this->assertStringNotContainsString('turnos, estudios, controles', $prompt);
    }
}
