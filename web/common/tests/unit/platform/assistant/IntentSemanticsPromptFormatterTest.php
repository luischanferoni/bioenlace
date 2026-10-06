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

        $this->assertStringStartsWith("ID: turno_con_un_especialista\nTEXTO_BOTÓN: \"Turno con un especialista\"", $block);
        $this->assertStringContainsString('la oferta del centro que tiene agenda', $block);
        $this->assertStringContainsString('el centro de salud', $block);
        $this->assertStringContainsString('horario para reservar el turno', $block);
        $this->assertStringContainsString("RESULTADO:\nTurno agendado.", $block);
        $this->assertStringNotContainsString('[SECCIÓN]', $block);
        $this->assertStringNotContainsString('está a cargo', $block);
        $this->assertStringNotContainsString('confirma el turno', $block);
        $this->assertStringNotContainsString('turnos.crear-como-paciente', $block);
    }

    public function testAtencionAdjuntaLaFicha(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('atencion.necesito-atencion');

        $this->assertSame(
            <<<'TXT'
ID: solicitar_atencion
TEXTO_BOTÓN: "Solicitar Atención"

QUÉ CUBRE:
El motivo de su pedido de atención:
- Malestar nuevo.
- Estudio o práctica.
- Control/Seguimiento.
- Renovación o ajuste de medicación.
- Orientación por urgencia (dentro del flujo, deriva a llamar al 107).

URGENCIA:
Si el relato sugiere urgencia o el usuario la declara, mencionar en el mensaje "Llamar al 107", sin retrasar.
El botón "Solicitar Atención" puede ofrecerse igual, porque dentro incluye orientación por urgencia, para la persona que consulta o por otra persona.
Si la urgencia es explícita, priorizar la mención de "Llamar al 107" en el mensaje.

RESULTADO:
Turno agendado, mensaje enviado, o derivación a llamar al 107.
TXT,
            $block
        );
        $this->assertStringNotContainsString('[SECCIÓN]', $block);
        $this->assertStringNotContainsString('[OPCIÓN]', $block);
        $this->assertStringNotContainsString('triage_raiz', $block);
    }

    public function testFormatForIntentIdsUneFlujos(): void
    {
        $wrapped = IntentSemanticsPromptFormatter::formatForIntentIds([
            'turnos.crear-como-paciente',
            'atencion.necesito-atencion',
        ], 4);

        $this->assertSame(2, substr_count($wrapped, 'TEXTO_BOTÓN: "'));
        $this->assertStringContainsString('ID: turno_con_un_especialista', $wrapped);
        $this->assertStringContainsString('ID: solicitar_atencion', $wrapped);
        $this->assertLessThan(
            strpos($wrapped, 'ID: solicitar_atencion'),
            strpos($wrapped, 'ID: turno_con_un_especialista')
        );
    }
}
