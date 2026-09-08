<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideChannelConfig;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuidePromptAssembler;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;

class GuidePromptAssemblerIncompleteTest extends Unit
{
    protected function _after(): void
    {
        GuideChannelConfig::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
    }

    public function testIncompletePromptIncludesScopedRecordsAndAreas(): void
    {
        $prompt = GuidePromptAssembler::buildForIncomplete(
            [
                'necesidad_usuario' => 'Saber si hay problema por llegar 10 minutos tarde.',
                'normalized_text' => '¿Voy a tener problemas si llego 10 minutos tarde?',
                'context_areas' => ['appointments'],
            ],
            '¿Voy a tener problemas si llego 10 minutos tarde?',
            0,
            "--- context:his ---\n{\"site.appointment.policies\":{\"late_arrival_tolerance_minutes\":null}}\n--- end context:his ---",
            ''
        );

        $this->assertStringContainsString('site.appointment.policies', $prompt);
        $this->assertStringContainsString('Tema de la consulta', $prompt);
        $this->assertStringContainsString('Citas y turnos', $prompt);
        $this->assertStringNotContainsString('appointments —', $prompt);
        $this->assertStringContainsString('NO pidas alergias', $prompt);
    }
}
