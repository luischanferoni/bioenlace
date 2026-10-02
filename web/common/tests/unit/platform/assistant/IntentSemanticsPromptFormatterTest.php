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

    public function testTurnosCrearEsMapaDePantallas(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('turnos.crear-como-paciente');

        $this->assertStringStartsWith("FLUJO: TURNO_ESPECIALISTA\n", $block);
        $this->assertStringContainsString('INICIO: Botón "Turno con un especialista"', $block);
        $this->assertStringContainsString('[PANTALLA: ELEGIR_OFERTA_CENTRO]', $block);
        $this->assertStringContainsString('CUALQUIER_OPCION -> ELEGIR_CENTRO_SALUD', $block);
        $this->assertStringContainsString('Tipo: Selección de opción', $block);
        $this->assertStringContainsString('CUALQUIER_OPCION ->', $block);
        $this->assertStringContainsString('ACCION_FINAL: Confirmar turno', $block);
        $this->assertStringNotContainsString('funcionalidades', $block);
        $this->assertStringNotContainsString('turnos.crear-como-paciente', $block);
        $this->assertSame(1, substr_count($block, '[PANTALLA: ELEGIR_CENTRO_SALUD]'));
    }

    public function testAtencionEsGrafoDePantallasSinRepetirLaCola(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('atencion.necesito-atencion');

        $this->assertStringContainsString("FLUJO: SOLICITAR_ATENCION\n", $block);
        $this->assertStringContainsString('INICIO: Botón "Solicitar Atención"', $block);
        $this->assertStringContainsString('[PANTALLA: SOLICITAR_ATENCION]', $block);
        $this->assertStringContainsString('    - Malestar nuevo', $block);
        $this->assertStringContainsString('    - Estudio o práctica', $block);
        $this->assertStringContainsString('    - Control/Seguimiento', $block);
        $this->assertStringContainsString('    - Urgencia', $block);
        $this->assertStringContainsString('Malestar nuevo      -> ZONA', $block);
        $this->assertStringContainsString('Estudio o práctica  -> ESTUDIO_PRACTICA', $block);
        $this->assertStringContainsString('Control/Seguimiento -> SOBRE_CONTROL_SEGUIMIENTO', $block);
        $this->assertStringContainsString('Urgencia            -> ORIENTACION_URGENCIA', $block);

        $this->assertStringContainsString('    - Cabeza, cuello o mareos', $block);
        $this->assertStringContainsString('    - Síntoma general (fiebre, cansancio u otro)', $block);
        $this->assertStringContainsString('CUALQUIER_OPCION -> MODALIDAD', $block);
        $this->assertStringContainsString('    - Presencial', $block);
        $this->assertStringContainsString('    - Videollamada', $block);
        $this->assertStringContainsString('    - Por mensaje', $block);
        $this->assertStringContainsString('Presencial   -> SERVICIO', $block);
        $this->assertStringContainsString('Videollamada -> DIA_TELECONSULTA_MEDICINA_GENERAL', $block);
        $this->assertStringContainsString('Por mensaje  -> DESCRIBI_TU_CONSULTA_CAMPO_TEXTO_ABAJO', $block);

        $this->assertStringContainsString('CUALQUIER_OPCION -> CENTRO_SALUD', $block);
        $this->assertStringContainsString('CUALQUIER_OPCION -> PROFESIONAL', $block);
        $this->assertStringContainsString('CUALQUIER_OPCION -> DIA', $block);
        $this->assertStringContainsString('CUALQUIER_OPCION -> HORARIO', $block);
        $this->assertStringContainsString('[PANTALLA: HORARIO]', $block);
        $this->assertStringContainsString('ACCION_FINAL: Confirmar turno', $block);
        $this->assertSame(1, substr_count($block, '[PANTALLA: SERVICIO]'));
        $this->assertSame(1, substr_count($block, '[PANTALLA: HORARIO]'));

        $this->assertStringContainsString('[PANTALLA: ORIENTACION_URGENCIA]', $block);
        $this->assertStringContainsString('Tipo: Información', $block);
        $this->assertStringContainsString('FIN_DEL_FLUJO: Sí', $block);
        $this->assertStringContainsString('    - Llamar al 107', $block);
        $this->assertStringContainsString('Tipo: Texto', $block);

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

        $this->assertSame(2, substr_count($wrapped, 'FLUJO: '));
        $this->assertStringContainsString('INICIO: Botón "Turno con un especialista"', $wrapped);
        $this->assertStringContainsString('INICIO: Botón "Solicitar Atención"', $wrapped);
        $this->assertLessThan(
            strpos($wrapped, 'FLUJO: SOLICITAR_ATENCION'),
            strpos($wrapped, 'FLUJO: TURNO_ESPECIALISTA')
        );
    }
}
