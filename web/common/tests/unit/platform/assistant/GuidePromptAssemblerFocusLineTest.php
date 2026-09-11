<?php

namespace common\tests\unit\platform\assistant;

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
            'context_areas' => [AssistantContextHISArea::SCHEDULING],
            'extractions' => [],
        ]);

        $prompt = GuidePromptAssembler::build(
            'llego tarde',
            0,
            new GuideFocusState(AssistantContextHISArea::SCHEDULING, [AssistantContextHISArea::SCHEDULING]),
            null,
            null
        );

        $this->assertStringContainsString('Citas y turnos', $prompt);
        $this->assertStringContainsString('Ambito/s del sistema de información hospitalaria', $prompt);
        $this->assertStringNotContainsString('scheduling', $prompt);
        $this->assertStringNotContainsString('turnos, estudios, controles', $prompt);
        $this->assertStringNotContainsString('Tema de la consulta', $prompt);
        $this->assertStringNotContainsString('Ámbito de esta consulta', $prompt);
    }
}
