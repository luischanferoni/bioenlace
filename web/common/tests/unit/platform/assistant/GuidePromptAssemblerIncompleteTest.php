<?php

namespace common\tests\unit\platform\assistant;

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
                'context_areas' => ['scheduling'],
            ],
            '¿Voy a tener problemas si llego 10 minutos tarde?',
            0,
            "--- context:his ---\n{\"site.appointment.policies\":{\"late_arrival_tolerance_minutes\":null}}\n--- end context:his ---",
            ''
        );

        $this->assertStringContainsString('site.appointment.policies', $prompt);
        $this->assertStringContainsString('Qué necesita la persona', $prompt);
        $this->assertStringContainsString('Saber si hay problema por llegar 10 minutos tarde.', $prompt);
        $this->assertStringContainsString('Ambito/s del sistema de información hospitalaria', $prompt);
        $this->assertStringContainsString('Citas y turnos', $prompt);
        $this->assertStringNotContainsString('scheduling —', $prompt);
        $this->assertStringNotContainsString('{necesidad_usuario}', $prompt);
    }

    public function testGuideSeesOnlyActiveNeed(): void
    {
        $prompt = GuidePromptAssembler::buildForIncomplete(
            [
                'necesidad_usuario' => 'Ya tiene el turno de clínica.',
                'necesidades_usuario' => [
                    ['expresion' => 'Ya tiene el turno de clínica.', 'estado' => 'satisfecha'],
                    ['expresion' => 'Quiere una ecografía.', 'estado' => 'activa'],
                ],
                'normalized_text' => 'la ecografía',
                'context_areas' => ['scheduling'],
            ],
            'la ecografía',
            0,
            '',
            ''
        );

        $this->assertStringContainsString('Quiere una ecografía.', $prompt);
        $this->assertStringNotContainsString('Ya tiene el turno de clínica.', $prompt);
    }
}
