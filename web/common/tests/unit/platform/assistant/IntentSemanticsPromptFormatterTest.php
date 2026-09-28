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

        $this->assertStringContainsString('Botón: Turno con un especialista', $block);
        $this->assertStringContainsString('La persona elige la oferta del centro que tiene agenda.', $block);
        $this->assertStringContainsString('Objetivo: la persona reserva un turno.', $block);
        $this->assertStringContainsString('1. La persona elige el centro de salud.', $block);
        $this->assertStringContainsString('4. La persona elige horario para reservar el turno.', $block);
        $this->assertStringContainsString('Éxito: Termina cuando la persona reserva un turno.', $block);
        $this->assertStringNotContainsString('objective', $block);
        $this->assertStringNotContainsString('Pasos:', $block);
        $this->assertStringNotContainsString('turnos.crear-como-paciente', $block);
    }

    public function testAtencionPathReachesHorarioWithoutGuards(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('atencion.necesito-atencion');

        $this->assertStringContainsString('Botón: Solicitar Atención', $block);
        $this->assertStringContainsString('La persona elige qué pedido de atención hace.', $block);
        $this->assertStringContainsString("Urgencia\nObjetivo: la persona recibe orientación por urgencia.", $block);
        $this->assertStringContainsString('El sistema indica: Por lo que indicaste, conviene atención urgente ahora (guardia o emergencias). No sigas con la reserva en la app. Si empeorás, llamá al 107.', $block);
        $this->assertStringContainsString('Ofrece: Llamar al 107.', $block);
        $this->assertStringContainsString('Éxito: Termina cuando la persona recibe orientación por urgencia.', $block);
        $this->assertStringContainsString("Malestar nuevo\nObjetivo: la persona reserva un turno.\n1. La persona elige la zona del malestar.", $block);
        $this->assertStringContainsString('Opciones: Cabeza, cuello o mareos, Pecho, corazón o respiración', $block);
        $this->assertStringContainsString('Síntoma general (fiebre, cansancio u otro)', $block);
        $this->assertStringContainsString('7. La persona elige horario disponible para el turno.', $block);
        $this->assertStringContainsString('Éxito: Termina cuando la persona reserva un turno.', $block);
        $this->assertStringContainsString("Control/Seguimiento\nObjetivo: la persona envía la consulta.", $block);
        $this->assertStringContainsString('3. La persona escribe la consulta del control.', $block);
        $this->assertStringContainsString('Éxito: Termina cuando la persona envía la consulta.', $block);
        $this->assertStringNotContainsString('Si elige', $block);
        $this->assertStringNotContainsString('En esa pantalla:', $block);
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

        $this->assertStringContainsString('Botón: Turno con un especialista', $wrapped);
        $this->assertStringContainsString('Botón: Solicitar Atención', $wrapped);
        $this->assertStringNotContainsString('---', $wrapped);
    }
}
