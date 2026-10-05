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

        $this->assertStringStartsWith("[BOTÓN] \"Turno con un especialista\"\n\n[SECCIÓN]", $block);
        $this->assertStringContainsString('[SECCIÓN] "La persona elige la oferta del centro que tiene agenda."', $block);
        $this->assertStringContainsString('[SECCIÓN] "La persona elige el centro de salud."', $block);
        $this->assertStringContainsString('[SECCIÓN] "La persona elige horario para reservar el turno."', $block);
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

        $this->assertStringStartsWith("[BOTÓN] \"Solicitar Atención\"\n\n[SECCIÓN]", $block);
        $this->assertStringContainsString(
            '[SECCIÓN] "La persona elige el motivo de su pedido de atención."',
            $block
        );
        $this->assertStringContainsString('↓ aparece una nueva sección debajo', $block);
        $this->assertStringNotContainsString(
            'Las siguientes funcionalidades se aplican únicamente',
            $block
        );

        $this->assertStringContainsString('[OPCIÓN] "Malestar nuevo"', $block);
        $this->assertStringContainsString('[OPCIÓN] "Estudio o práctica"', $block);
        $this->assertStringContainsString('[OPCIÓN] "Control/Seguimiento"', $block);
        $this->assertStringContainsString('[OPCIÓN] "Urgencia"', $block);
        $this->assertStringContainsString(
            '[SECCIÓN] "La persona elige la zona de su cuerpo donde tiene malestar."',
            $block
        );
        $this->assertStringContainsString('[OPCIÓN] "Síntoma general (fiebre, cansancio u otro)"', $block);
        $this->assertStringContainsString('[OPCIÓN] "Presencial"', $block);
        $this->assertStringContainsString(
            '[SECCIÓN] "La persona elige un servicio entre los filtrados por la forma de atenderse."',
            $block
        );
        $this->assertStringContainsString('[OPCIÓN] "Videollamada"', $block);
        $this->assertStringContainsString(
            '[SECCIÓN] "La persona elige día de teleconsulta con Medicina General."',
            $block
        );
        $this->assertStringContainsString('[OPCIÓN] "Por mensaje"', $block);
        $this->assertStringContainsString(
            '[SECCIÓN] "La persona escribe la consulta para un profesional."',
            $block
        );
        $this->assertStringContainsString(
            '[SECCIÓN] "La persona elige horario disponible para el turno."',
            $block
        );
        $this->assertStringContainsString(
            '[SECCIÓN] "Orientación por urgencia, para la persona que consulta o por otra persona."',
            $block
        );
        $this->assertStringContainsString('[OPCIÓN] "Llamar al 107"', $block);
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

        $this->assertSame(2, substr_count($wrapped, '[BOTÓN] "'));
        $this->assertStringContainsString("[BOTÓN] \"Turno con un especialista\"\n\n[SECCIÓN]", $wrapped);
        $this->assertStringContainsString("[BOTÓN] \"Solicitar Atención\"\n\n[SECCIÓN]", $wrapped);
        $this->assertLessThan(
            strpos($wrapped, "[BOTÓN] \"Solicitar Atención\"\n\n[SECCIÓN]"),
            strpos($wrapped, "[BOTÓN] \"Turno con un especialista\"\n\n[SECCIÓN]")
        );
    }
}
