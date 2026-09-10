<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;

class ChatPreprocessPromptTest extends Unit
{
    public function testStablePromptIncludesV1Schema(): void
    {
        $prompt = ChatPreprocessService::stablePromptPrefix();

        $this->assertStringContainsString('Información Hospitalaria', $prompt);
        $this->assertStringContainsString('routing_hint', $prompt);
        $this->assertStringContainsString('necesidad_usuario', $prompt);
        $this->assertStringContainsString('tags', $prompt);
        $this->assertStringContainsString('context_areas', $prompt);
        $this->assertStringContainsString('appointments', $prompt);
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
}
