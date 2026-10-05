<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\IntentSemanticsPromptFormatter;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;

class IntentSemanticsPromptFormatterTest extends Unit
{
    protected function _after(): void
    {
        AssistantMetadataLoader::resetCacheForTests();
    }

    public function testTurnosCrearEsProsa(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('turnos.crear-como-paciente');

        $this->assertStringStartsWith("Botón \"Turno con un especialista\"\n", $block);
        $this->assertStringContainsString("Para: solo la persona que escribe.\n", $block);
        $this->assertStringContainsString('La persona elige la oferta del centro que tiene agenda.', $block);
        $this->assertStringContainsString('Después: La persona elige el centro de salud.', $block);
        $this->assertStringContainsString(
            'La persona elige horario para reservar el turno. Termina cuando se reserva un turno para quien está a cargo.',
            $block
        );
        $this->assertStringNotContainsString('confirma el turno', $block);
        $this->assertStringNotContainsString('PANTALLA', $block);
        $this->assertStringNotContainsString('funcionalidades', $block);
        $this->assertStringNotContainsString('turnos.crear-como-paciente', $block);
    }

    public function testAtencionEsProsaSinIdsDePantalla(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('atencion.necesito-atencion');

        $this->assertStringContainsString("Botón \"Solicitar Atención\"\n", $block);
        $this->assertStringContainsString('La persona elige qué pedido de atención hace.', $block);
        $this->assertStringNotContainsString(
            'Las siguientes funcionalidades se aplican únicamente',
            $block
        );

        $this->assertStringContainsString("Camino: Malestar nuevo\nPara: solo la persona que escribe.", $block);
        $this->assertStringContainsString("Camino: Estudio o práctica\nPara: solo la persona que escribe.", $block);
        $this->assertStringContainsString("Camino: Control/Seguimiento\nPara: solo la persona que escribe.", $block);
        $this->assertStringContainsString(
            "Camino: Urgencia\nPara: la persona que escribe o también otra persona.",
            $block
        );

        $this->assertStringContainsString('Síntoma general (fiebre, cansancio u otro)', $block);
        $this->assertStringContainsString(
            '- Presencial: La persona elige un servicio entre los filtrados por la forma de atenderse.',
            $block
        );
        $this->assertStringContainsString('- Videollamada: La persona elige día de teleconsulta con Medicina General.', $block);
        $this->assertStringContainsString(
            '- Por mensaje: La persona escribe la consulta para un profesional. Termina cuando quien está a cargo envía la consulta.',
            $block
        );
        $this->assertStringContainsString(
            'La persona elige horario disponible para el turno. Termina cuando se reserva un turno para quien está a cargo.',
            $block
        );
        $this->assertStringContainsString(
            'puede llamar al 107. Termina cuando la persona recibe orientación por urgencia, también para alguien que no está a cargo.',
            $block
        );

        $this->assertStringNotContainsString('confirma el turno', $block);
        $this->assertStringNotContainsString('ORIENTACION_URGENCIA', $block);
        $this->assertStringNotContainsString('PANTALLA', $block);
        $this->assertStringNotContainsString('CUALQUIER_OPCION', $block);
        $this->assertStringNotContainsString('triage_raiz', $block);
        $this->assertStringNotContainsString('Por lo que indicaste', $block);
        $this->assertStringNotContainsString('funcionalidades', $block);
    }

    public function testFormatForIntentIdsUneFlujos(): void
    {
        $wrapped = IntentSemanticsPromptFormatter::formatForIntentIds([
            'turnos.crear-como-paciente',
            'atencion.necesito-atencion',
        ], 4);

        $this->assertSame(2, substr_count($wrapped, 'Botón "'));
        $this->assertStringContainsString('Botón "Turno con un especialista"', $wrapped);
        $this->assertStringContainsString('Botón "Solicitar Atención"', $wrapped);
        $this->assertLessThan(
            strpos($wrapped, 'Botón "Solicitar Atención"'),
            strpos($wrapped, 'Botón "Turno con un especialista"')
        );
    }
}
