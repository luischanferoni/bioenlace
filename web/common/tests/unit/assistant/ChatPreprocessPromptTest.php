<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;

class ChatPreprocessPromptTest extends Unit
{
    public function testStablePromptEsGenericoSistemaSalud(): void
    {
        $prompt = ChatPreprocessService::stablePromptPrefix();

        $this->assertStringContainsString('sistema de salud', $prompt);
        $this->assertStringContainsString('operational: ejecutar o consultar un trámite concreto', $prompt);
        $this->assertStringContainsString('clinical:', $prompt);
        $this->assertStringContainsString('ambiguous:', $prompt);
        $this->assertStringContainsString('No incluye saludo solo', $prompt);
        $this->assertStringNotContainsString('ecografía', $prompt);
        $this->assertStringNotContainsString('estudio/práctica', $prompt);
        $this->assertStringNotContainsString('hospital cerca', $prompt);
        $this->assertStringNotContainsString('kinesio', $prompt);
    }
}
