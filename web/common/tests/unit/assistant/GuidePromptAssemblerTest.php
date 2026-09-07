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

    public function testSemanticsSectionIsHumanReadableWithoutMarkers(): void
    {
        $catalog = UiActionCatalog::fromItems([
            new UiActionCatalogItem(
                'turnos.crear-como-paciente',
                'Turno',
                'Sacar turno',
                null,
                '/api/turnos/crear-como-paciente',
                ['turno'],
                ['expected' => [], 'provided' => []],
                ['objective' => 'Reservá turno', 'capabilities' => ['reserva_turno']],
                null,
                null,
                null,
                [AssistantContextHISArea::APPOINTMENTS]
            ),
        ], []);

        $section = GuideIntentSemanticsFilter::formatPromptSection(
            $catalog,
            [AssistantContextHISArea::APPOINTMENTS]
        );

        $this->assertStringContainsString('Turno con un especialista:', $section);
        $this->assertStringNotContainsString('context:intent_semantics', $section);
        $this->assertStringNotContainsString('turnos.crear-como-paciente', $section);
        $this->assertStringNotContainsString('---', $section);
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

        $prompt = GuidePromptAssembler::build(
            'llego tarde',
            0,
            new GuideFocusState(AssistantContextHISArea::APPOINTMENTS, [AssistantContextHISArea::APPOINTMENTS]),
            null,
            null
        );

        $this->assertStringContainsString('sistema de salud', $prompt);
        $this->assertStringContainsString('Tema de la consulta', $prompt);
        $this->assertStringContainsString('Mensaje de la persona', $prompt);
        $this->assertStringNotContainsString('context:intent_semantics', $prompt);
        $this->assertStringNotContainsString('Ámbito de esta consulta', $prompt);

        $semInPrompt = strpos($prompt, 'Gestiones que el sistema puede ofrecer ahora');
        $historyInPrompt = strpos($prompt, 'Conversación previa');
        if ($semInPrompt !== false && $historyInPrompt !== false) {
            $this->assertLessThan($historyInPrompt, $semInPrompt);
        }
    }
}
