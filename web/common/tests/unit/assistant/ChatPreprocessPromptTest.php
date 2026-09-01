<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;

class ChatPreprocessPromptTest extends Unit
{
    public function testStablePromptEsGenericoPortalPaciente(): void
    {
        $prompt = ChatPreprocessService::stablePromptPrefix();

        $this->assertStringContainsString('Información Hospitalaria', $prompt);
        $this->assertStringContainsString('context_areas', $prompt);
        $this->assertStringContainsString('appointments', $prompt);
        $this->assertStringContainsString('operational: ejecutar o consultar un trámite concreto', $prompt);
        $this->assertStringContainsString('guide:', $prompt);
        $this->assertStringContainsString('ambiguous:', $prompt);
        $this->assertStringContainsString('Devolvé solo claves de la lista', $prompt);
        $this->assertStringNotContainsString('ecografía', $prompt);
        $this->assertStringNotContainsString('estudio/práctica', $prompt);
        $this->assertStringNotContainsString('hospital cerca', $prompt);
        $this->assertStringNotContainsString('kinesio', $prompt);
    }
}
