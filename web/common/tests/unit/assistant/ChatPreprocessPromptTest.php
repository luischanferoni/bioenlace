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
        $this->assertStringContainsString('operational: quiere hacer o consultar algo en el sistema', $prompt);
        $this->assertStringContainsString('conversational: saludo, síntomas, malestar', $prompt);
        $this->assertStringNotContainsString('ecografía', $prompt);
        $this->assertStringNotContainsString('estudio/práctica', $prompt);
        $this->assertStringNotContainsString('hospital cerca', $prompt);
        $this->assertStringNotContainsString('kinesio', $prompt);
    }
}
