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
        $this->assertStringContainsString('La persona elige la oferta del centro que tiene agenda.', $block);
        $this->assertStringContainsString('Después: profesional de esa oferta en ese centro, día con cupos, horario para reservar el turno.', $block);
        $this->assertStringNotContainsString('objective', $block);
        $this->assertStringNotContainsString('Pasos:', $block);
        $this->assertStringNotContainsString('turnos.crear-como-paciente', $block);
    }

    public function testAtencionPathReachesHorarioWithoutGuards(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('atencion.necesito-atencion');

        $this->assertStringContainsString('- Solicitar Atención', $block);
        $this->assertStringContainsString('La persona elige qué pedido de atención hace.', $block);
        $this->assertStringContainsString('Opciones: Malestar nuevo, Estudio o práctica, Control/Seguimiento, Urgencia.', $block);
        $this->assertStringContainsString('→ Urgencia. Orientación por urgencia. El recorrido se detiene.', $block);
        $this->assertStringContainsString('Síntoma general (fiebre, cansancio u otro)', $block);
        $this->assertStringContainsString('Después: formas de atenderse, servicio del centro para la atención', $block);
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
