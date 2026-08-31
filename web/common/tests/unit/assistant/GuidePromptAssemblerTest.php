<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideChannelConfig;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideFocusState;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideIntentSemanticsFilter;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuidePromptAssembler;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;

class GuidePromptAssemblerTest extends Unit
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

    public function testBlockOrderIncludesSemanticsBeforeHistory(): void
    {
        ChatPreprocessContext::set([
            'ok' => true,
            'normalized_text' => 'llego tarde',
            'user_goal' => 'guide',
            'action_text' => '',
            'context_areas' => [AssistantContextHISArea::APPOINTMENTS],
            'extractions' => [],
        ]);

        $catalog = UiActionCatalog::fromItems([
            new UiActionCatalogItem(
                'turnos.crear-como-paciente',
                'Turno',
                'Sacar turno',
                null,
                '/api/turnos/crear-como-paciente',
                ['turno'],
                ['expected' => [], 'provided' => []],
                ['summary' => 'Reservá turno', 'capabilities' => ['reserva_turno']],
                null,
                null,
                null,
                [AssistantContextHISArea::APPOINTMENTS]
            ),
        ], []);

        $semPos = strpos(
            GuideIntentSemanticsFilter::formatPromptSection(
                $catalog,
                [AssistantContextHISArea::APPOINTMENTS]
            ),
            'context:intent_semantics'
        );
        $this->assertNotFalse($semPos);

        $prompt = GuidePromptAssembler::build(
            'llego tarde',
            0,
            new GuideFocusState(AssistantContextHISArea::APPOINTMENTS, [AssistantContextHISArea::APPOINTMENTS]),
            null,
            '',
            null
        );

        $this->assertStringContainsString('portal del paciente', $prompt);
        $this->assertStringContainsString('context:intent_semantics', $prompt);
        $this->assertStringContainsString('turnos.crear-como-paciente', $prompt);
        $this->assertStringContainsString('Mensaje actual del paciente:', $prompt);

        $semInPrompt = strpos($prompt, 'context:intent_semantics');
        $historyInPrompt = strpos($prompt, 'Conversación previa');
        if ($historyInPrompt !== false) {
            $this->assertLessThan($historyInPrompt, $semInPrompt);
        }
    }
}
