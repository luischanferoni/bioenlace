<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Channels\Synthesis\SynthesisChannelConfig;
use common\components\Platform\Assistant\Chat\Channels\Synthesis\SynthesisPromptAssembler;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;

class SynthesisPromptAssemblerTest extends Unit
{
    protected function _after(): void
    {
        SynthesisChannelConfig::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
    }

    public function testPromptIncludesNecesidadAndScopedRecords(): void
    {
        $prompt = SynthesisPromptAssembler::build(
            [
                'necesidad_usuario' => 'Saber si hay problema por llegar 10 minutos tarde.',
                'normalized_text' => '¿Voy a tener problemas si llego 10 minutos tarde?',
                'context_areas' => ['appointments'],
            ],
            "--- context:his ---\n{\"site.appointment.policies\":{\"late_arrival_tolerance_minutes\":null}}\n--- end context:his ---",
            '',
            '¿Voy a tener problemas si llego 10 minutos tarde?'
        );

        $this->assertStringContainsString('Saber si hay problema por llegar 10 minutos tarde.', $prompt);
        $this->assertStringContainsString('site.appointment.policies', $prompt);
        $this->assertStringContainsString('appointments', $prompt);
        $this->assertStringContainsString('no inventes números', $prompt);
    }
}
