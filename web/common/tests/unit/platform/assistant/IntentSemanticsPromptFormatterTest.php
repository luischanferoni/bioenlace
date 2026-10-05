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

        $this->assertStringStartsWith("Botón \"Turno con un especialista\"\n\nSección:", $block);
        $this->assertStringContainsString('Sección: "La persona elige la oferta del centro que tiene agenda."', $block);
        $this->assertStringContainsString('Sección: "La persona elige el centro de salud."', $block);
        $this->assertStringContainsString('Sección: "La persona elige horario para reservar el turno."', $block);
        $this->assertStringContainsString('fin · solo la persona que escribe', $block);
        $this->assertStringNotContainsString('Para:', $block);
        $this->assertStringNotContainsString('está a cargo', $block);
        $this->assertStringNotContainsString('confirma el turno', $block);
        $this->assertStringNotContainsString('PANTALLA', $block);
        $this->assertStringNotContainsString('funcionalidades', $block);
        $this->assertStringNotContainsString('turnos.crear-como-paciente', $block);
    }

    public function testAtencionEsProsaSinIdsDePantalla(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('atencion.necesito-atencion');

        $this->assertStringStartsWith("Botón \"Solicitar Atención\"\n\nSección:", $block);
        $this->assertStringContainsString(
            'Sección: "La persona elige el motivo de su pedido de atención."',
            $block
        );
        $this->assertStringContainsString('↓ aparece una nueva sección debajo', $block);
        $this->assertStringNotContainsString(
            'Las siguientes funcionalidades se aplican únicamente',
            $block
        );

        $this->assertStringContainsString('Opción: "Malestar nuevo"', $block);
        $this->assertStringContainsString('Opción: "Estudio o práctica"', $block);
        $this->assertStringContainsString('Opción: "Control/Seguimiento"', $block);
        $this->assertStringContainsString('Opción: "Urgencia"', $block);
        $this->assertStringContainsString(
            'Sección: "La persona elige la zona de su cuerpo donde tiene malestar."',
            $block
        );
        $this->assertStringContainsString('Opción: "Síntoma general (fiebre, cansancio u otro)"', $block);
        $this->assertStringContainsString('Opción: "Presencial"', $block);
        $this->assertStringContainsString(
            'Sección: "La persona elige un servicio entre los filtrados por la forma de atenderse."',
            $block
        );
        $this->assertStringContainsString('Opción: "Videollamada"', $block);
        $this->assertStringContainsString(
            'Sección: "La persona elige día de teleconsulta con Medicina General."',
            $block
        );
        $this->assertStringContainsString('Opción: "Por mensaje"', $block);
        $this->assertStringContainsString(
            'Sección: "La persona escribe la consulta para un profesional."',
            $block
        );
        $this->assertStringContainsString(
            'Sección: "La persona elige horario disponible para el turno."',
            $block
        );
        $this->assertStringContainsString(
            'Sección: "Orientación por urgencia, para la persona que consulta o por otra persona."',
            $block
        );
        $this->assertStringContainsString('Opción: "Llamar al 107"', $block);
        $this->assertStringContainsString('fin · solo la persona que escribe', $block);
        $this->assertStringContainsString('fin · la persona que escribe o también otra persona', $block);
        $this->assertStringNotContainsString('Para:', $block);
        $this->assertStringNotContainsString('Motivo', $block);
        $this->assertStringNotContainsString('Modalidad', $block);
        $this->assertStringNotContainsString('está a cargo', $block);
        $this->assertStringNotContainsString('también para alguien', $block);

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
        $this->assertStringContainsString("Botón \"Turno con un especialista\"\n\nSección:", $wrapped);
        $this->assertStringContainsString("Botón \"Solicitar Atención\"\n\nSección:", $wrapped);
        $this->assertLessThan(
            strpos($wrapped, "Botón \"Solicitar Atención\"\n\nSección:"),
            strpos($wrapped, "Botón \"Turno con un especialista\"\n\nSección:")
        );
    }
}
