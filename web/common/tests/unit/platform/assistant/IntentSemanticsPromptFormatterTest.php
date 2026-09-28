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

        $this->assertStringContainsString('1. Botón del chat: Turno con un especialista', $block);
        $this->assertStringContainsString('1.1 Al presionarlo: La persona elige la oferta del centro que tiene agenda.', $block);
        $this->assertStringContainsString('1.2 Objetivo: la persona reserva un turno.', $block);
        $this->assertStringContainsString('1.3 La persona elige el centro de salud.', $block);
        $this->assertStringContainsString('1.6 La persona elige horario para reservar el turno.', $block);
        $this->assertStringContainsString('1.7 Éxito: Termina cuando la persona reserva un turno.', $block);
        $this->assertStringNotContainsString('objective', $block);
        $this->assertStringNotContainsString('Pasos:', $block);
        $this->assertStringNotContainsString('turnos.crear-como-paciente', $block);
    }

    public function testAtencionPathReachesHorarioWithoutGuards(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('atencion.necesito-atencion');

        $this->assertStringContainsString('1. Botón del chat: Solicitar Atención', $block);
        $this->assertStringContainsString('1.1 Al presionarlo: La persona elige qué pedido de atención hace.', $block);
        $this->assertStringContainsString("1.2 Urgencia\n1.2.1 Objetivo: la persona recibe orientación por urgencia.", $block);
        $this->assertStringContainsString('1.2.2 El sistema muestra orientación por urgencia y frena la reserva en la app.', $block);
        $this->assertStringContainsString('1.2.2.1 Ofrece: Llamar al 107.', $block);
        $this->assertStringNotContainsString('Por lo que indicaste', $block);
        $this->assertStringContainsString('1.2.3 Éxito: Termina cuando la persona recibe orientación por urgencia.', $block);
        $this->assertStringContainsString("1.3 Malestar nuevo\n1.3.1 Objetivo: la persona reserva un turno.\n1.3.2 La persona elige la zona del malestar.", $block);
        $this->assertStringContainsString('1.3.2.1 Opciones: Cabeza, cuello o mareos, Pecho, corazón o respiración', $block);
        $this->assertStringContainsString('Síntoma general (fiebre, cansancio u otro)', $block);
        $this->assertStringContainsString('1.3.8 La persona elige horario disponible para el turno.', $block);
        $this->assertStringContainsString('1.3.9 Éxito: Termina cuando la persona reserva un turno.', $block);
        $this->assertStringContainsString("1.5 Control/Seguimiento\n1.5.1 Objetivo: la persona envía la consulta.", $block);
        $this->assertStringContainsString('1.5.4 La persona escribe la consulta del control.', $block);
        $this->assertStringContainsString('1.5.5 Éxito: Termina cuando la persona envía la consulta.', $block);
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

        $this->assertStringContainsString('1. Botón del chat: Turno con un especialista', $wrapped);
        $this->assertStringContainsString('2. Botón del chat: Solicitar Atención', $wrapped);
        $this->assertStringContainsString('2.1 Al presionarlo:', $wrapped);
        $this->assertStringNotContainsString('---', $wrapped);
    }
}
