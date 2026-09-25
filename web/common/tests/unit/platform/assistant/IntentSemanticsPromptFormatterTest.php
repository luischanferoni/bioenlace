<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\IntentSemanticsPromptFormatter;
use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;

class IntentSemanticsPromptFormatterTest extends Unit
{
    protected function _after(): void
    {
        SmartCatalogRegistry::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
    }

    public function testTurnosCrearIsAPathWithoutObjective(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('turnos.crear-como-paciente');

        $this->assertStringContainsString('- Turno con un especialista', $block);
        $this->assertStringContainsString('Elegir oferta del centro', $block);
        $this->assertStringContainsString('→ Horario para reservar el turno', $block);
        $this->assertStringNotContainsString('objective', $block);
        $this->assertStringNotContainsString('Pasos:', $block);
        $this->assertStringNotContainsString('turnos.crear-como-paciente', $block);
    }

    public function testAtencionPathReachesHorarioWithoutGuards(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('atencion.necesito-atencion');

        $this->assertStringContainsString('- Solicitar Atención', $block);
        $this->assertStringContainsString('Posibles motivos de consulta de la persona (opciones)', $block);
        $this->assertStringContainsString('→ Orientación por urgencia (opciones)', $block);
        $this->assertStringContainsString('→ Horario disponible para el turno (opciones)', $block);
        $this->assertStringNotContainsString('triage_raiz', $block);
        $this->assertStringNotContainsString('Cierres:', $block);
        $this->assertStringNotContainsString('objective', $block);
        $this->assertStringNotContainsString('atencion.necesito-atencion', $block);
    }

    public function testFormatForIntentIdsJoinsWithoutTechnicalFence(): void
    {
        $wrapped = IntentSemanticsPromptFormatter::formatForIntentIds([
            'turnos.crear-como-paciente',
            'atencion.necesito-atencion',
        ], 4);

        $this->assertStringContainsString('- Turno con un especialista', $wrapped);
        $this->assertStringContainsString('- Solicitar Atención', $wrapped);
        $this->assertStringNotContainsString('---', $wrapped);
    }
}
