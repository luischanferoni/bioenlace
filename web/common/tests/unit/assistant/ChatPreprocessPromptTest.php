<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;

class ChatPreprocessPromptTest extends Unit
{
    public function testStablePromptEsGenericoDeHis(): void
    {
        $prompt = ChatPreprocessService::stablePromptPrefix();

        $this->assertStringContainsString('HIS', $prompt);
        $this->assertStringContainsString('operational: ejecutar o consultar un trámite concreto', $prompt);
        $this->assertStringContainsString('conversational_clinical: saludo, o charla sobre su salud', $prompt);
        $this->assertStringContainsString('ambiguous_conversational:', $prompt);
        $this->assertStringNotContainsString('ecografía', $prompt);
        $this->assertStringNotContainsString('estudio/práctica', $prompt);
        $this->assertStringNotContainsString('hospital cerca', $prompt);
        $this->assertStringNotContainsString('kinesio', $prompt);
    }
}
