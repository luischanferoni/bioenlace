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

        $this->assertStringContainsString('- Botón: Turno con un especialista', $block);
        $this->assertStringContainsString('Al presionarlo se abre una pantalla donde la persona elige la oferta del centro que tiene agenda.', $block);
        $this->assertStringContainsString('Después, en este orden: centros de salud, incluidos los cercanos, profesional de esa oferta en ese centro, día con cupos, horario para reservar el turno. Ahí el recorrido se detiene.', $block);
        $this->assertStringNotContainsString('objective', $block);
        $this->assertStringNotContainsString('Pasos:', $block);
        $this->assertStringNotContainsString('turnos.crear-como-paciente', $block);
    }

    public function testAtencionPathReachesHorarioWithoutGuards(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('atencion.necesito-atencion');

        $this->assertStringContainsString('- Botón: Solicitar Atención', $block);
        $this->assertStringContainsString('Al presionarlo se abre una pantalla donde la persona elige qué pedido de atención hace.', $block);
        $this->assertStringContainsString('En esa pantalla: Malestar nuevo, Estudio o práctica, Control/Seguimiento, Urgencia.', $block);
        $this->assertStringContainsString('Si elige Urgencia, se abre otra pantalla: Orientación por urgencia. El recorrido se detiene.', $block);
        $this->assertStringContainsString('Si elige Malestar nuevo, se abre otra pantalla: La persona elige la zona del malestar.', $block);
        $this->assertStringContainsString('Síntoma general (fiebre, cansancio u otro)', $block);
        $this->assertStringContainsString('Después, en este orden: modalidad, servicio, centro de salud, profesional, día, horario. Ahí el recorrido se detiene.', $block);
        $this->assertStringContainsString('Después, en este orden: ¿Qué necesitás?, escribí tu consulta en el campo de abajo. Ahí el recorrido se detiene.', $block);
        $this->assertStringNotContainsString('Se abre otra pantalla: La persona elige el servicio del centro.', $block);
        $this->assertStringNotContainsString('Se abre otra pantalla: La persona elige la forma de atenderse.', $block);
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

        $this->assertStringContainsString('- Botón: Turno con un especialista', $wrapped);
        $this->assertStringContainsString('- Botón: Solicitar Atención', $wrapped);
        $this->assertStringNotContainsString('---', $wrapped);
    }
}
