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

        $this->assertStringStartsWith("ID: turnos.crear-como-paciente\nTEXTO_BOTÓN: \"Turno con un especialista\"", $block);
        $this->assertStringContainsString('la oferta del centro que tiene agenda', $block);
        $this->assertStringContainsString('el centro de salud', $block);
        $this->assertStringContainsString('horario para reservar el turno', $block);
        $this->assertStringContainsString("RESULTADO:\nTurno agendado.", $block);
        $this->assertStringNotContainsString('[SECCIÓN]', $block);
        $this->assertStringNotContainsString('está a cargo', $block);
        $this->assertStringNotContainsString('confirma el turno', $block);
    }

    public function testAtencionAdjuntaLaFicha(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('atencion.necesito-atencion');

        $this->assertSame(
            <<<'TXT'
ID: atencion.necesito-atencion
TEXTO_BOTÓN: "Solicitar Atención"
QUÉ CUBRE: atención médica en general, incluyendo consultas por síntomas nuevos no urgentes, estudios, controles, medicación y orientación ante urgencias.
REGLAS:
- Ante duda clínica, criterio conservador.
- Si el relato sugiere riesgo vital o el usuario declara urgencia, mencionar en el mensaje que llame al 107.
PARAMS:
- motivo: malestar_nuevo | estudio_pedido | seguimiento_cronico | urgencia
- zona (solo si motivo = malestar_nuevo): zona_cabeza_cuello | zona_pecho | zona_abdomen | zona_musculoesqueletico | zona_piel | zona_sistemas | zona_genitourinario | zona_general
RESULTADO: turno agendado, mensaje enviado, o mención de urgencia.
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
        $this->assertStringContainsString('ID: turnos.crear-como-paciente', $wrapped);
        $this->assertStringContainsString('ID: atencion.necesito-atencion', $wrapped);
        $this->assertLessThan(
            strpos($wrapped, 'ID: atencion.necesito-atencion'),
            strpos($wrapped, 'ID: turnos.crear-como-paciente')
        );
    }
}
