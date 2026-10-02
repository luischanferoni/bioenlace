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
        $this->assertStringContainsString('La persona elige la oferta del centro que tiene agenda.', $block);
        $this->assertStringContainsString('Después: La persona elige el centro de salud.', $block);
        $this->assertStringContainsString(
            'La persona elige horario para reservar el turno, confirma el turno. Ahí termina.',
            $block
        );
        $this->assertStringNotContainsString('PANTALLA', $block);
        $this->assertStringNotContainsString('funcionalidades', $block);
        $this->assertStringNotContainsString('turnos.crear-como-paciente', $block);
        $this->assertSame(1, substr_count($block, "\nLa persona elige el centro de salud.\n"));
    }

    public function testAtencionEsProsaSinIdsDePantalla(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('atencion.necesito-atencion');

        $this->assertStringContainsString("Botón \"Solicitar Atención\"\n", $block);
        $this->assertStringContainsString('La persona elige qué pedido de atención hace.', $block);
        $this->assertStringContainsString('- Malestar nuevo: La persona elige la zona del malestar.', $block);
        $this->assertStringContainsString('- Estudio o práctica: La persona elige el estudio o la práctica que necesita.', $block);
        $this->assertStringContainsString('- Control/Seguimiento: La persona elige el tema del control o seguimiento.', $block);
        $this->assertStringContainsString('- Urgencia: Orientación por urgencia, puede llamar al 107. Ahí termina.', $block);

        $this->assertStringContainsString('- Cabeza, cuello o mareos', $block);
        $this->assertStringContainsString('- Síntoma general (fiebre, cansancio u otro)', $block);
        $this->assertStringContainsString('Cualquiera sigue así: La persona elige cómo atenderse.', $block);
        $this->assertStringContainsString('- Presencial: La persona elige un servicio entre los filtrados por la forma de atenderse.', $block);
        $this->assertStringContainsString('- Videollamada: La persona elige día de teleconsulta con Medicina General.', $block);
        $this->assertStringContainsString('- Por mensaje: La persona escribe la consulta para un profesional. Ahí termina.', $block);
        $this->assertStringContainsString(
            'Después: La persona elige horario disponible para el turno, confirma el turno. Ahí termina.',
            $block
        );

        $this->assertSame(1, substr_count($block, "\nLa persona elige la zona del malestar.\n"));
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
