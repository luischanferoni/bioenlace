<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;
use common\components\Platform\Assistant\Chat\Thread\AssistantThreadStateService;
use common\components\Platform\Assistant\Chat\Thread\ThreadNeedList;

class ChatPreprocessPromptTest extends Unit
{
    public function testStablePromptIncludesV1Schema(): void
    {
        $prompt = ChatPreprocessService::stablePromptPrefix();

        $this->assertStringContainsString('routing_hint', $prompt);
        $this->assertStringContainsString('necesidad_usuario', $prompt);
        $this->assertStringContainsString('tags', $prompt);
        $this->assertStringNotContainsString('context_areas', $prompt);
        $this->assertStringNotContainsString('Áreas HIS', $prompt);
        $this->assertStringContainsString('pedido_claro', $prompt);
        $this->assertStringContainsString('pedido_fuera_his', $prompt);
        $this->assertStringContainsString('sin_pedido', $prompt);
        $this->assertStringContainsString('Historial reciente', $prompt);
    }

    public function testBuildFullPromptIncludesCurrentMessage(): void
    {
        $full = ChatPreprocessService::buildFullPrompt('¿Cuáles son mis turnos?', 0);

        $this->assertStringContainsString('¿Cuáles son mis turnos?', $full);
        $this->assertStringContainsString('(sin historial previo)', $full);
    }

    public function testBuildFullPromptIncludesStoredNeeds(): void
    {
        AssistantThreadStateService::resetCacheForTests();
        AssistantThreadStateService::saveNecesidades(0, [
            ['expresion' => 'Ya sacó el turno de clínica.', 'estado' => ThreadNeedList::SATISFECHA],
            ['expresion' => 'Quiere una ecografía.', 'estado' => ThreadNeedList::ACTIVA],
        ]);

        $full = ChatPreprocessService::buildFullPrompt('la ecografía', 0);

        $this->assertStringContainsString('- satisfecha: Ya sacó el turno de clínica.', $full);
        $this->assertStringContainsString('- activa: Quiere una ecografía.', $full);
        $reloaded = AssistantThreadStateService::loadNecesidades(0);
        $this->assertSame('Quiere una ecografía.', ThreadNeedList::activeText($reloaded));
        $this->assertCount(2, $reloaded);

        AssistantThreadStateService::resetCacheForTests();
    }
}
