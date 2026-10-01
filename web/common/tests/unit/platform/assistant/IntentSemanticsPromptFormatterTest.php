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

    public function testTurnosCrearIsFlatUnderBoton(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('turnos.crear-como-paciente');
        $data = json_decode($block, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('funcionalidades', $data);
        $item = $data['funcionalidades'][0];
        $this->assertSame('Turno con un especialista', $item['boton']);
        $this->assertSame('La persona elige la oferta del centro que tiene agenda.', $item['al_presionar']);
        $this->assertSame('se reserva un turno para quien está a cargo', $item['objetivo']);
        $this->assertArrayNotHasKey('recorridos', $item);
        $this->assertSame('La persona elige el centro de salud.', $item['pasos'][0]['hace']);
        $this->assertStringContainsString('horario para reservar el turno', $item['pasos'][3]['hace']);
        $this->assertSame('Termina cuando se reserva un turno para quien está a cargo.', $item['exito']);
        $this->assertStringNotContainsString('turnos.crear-como-paciente', $block);
    }

    public function testAtencionUsaRecorridosYEligeEntre(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('atencion.necesito-atencion');
        $data = json_decode($block, true);

        $this->assertIsArray($data);
        $item = $data['funcionalidades'][0];
        $this->assertSame('Solicitar Atención', $item['boton']);
        $this->assertSame('La persona elige qué pedido de atención hace.', $item['al_presionar']);
        $this->assertArrayHasKey('recorridos', $item);

        $byName = [];
        foreach ($item['recorridos'] as $r) {
            $byName[$r['nombre']] = $r;
        }

        $this->assertArrayHasKey('Urgencia', $byName);
        $this->assertSame(
            'la persona recibe orientación por urgencia, también para alguien que no está a cargo',
            $byName['Urgencia']['objetivo']
        );
        $this->assertSame(
            'El sistema muestra orientación por urgencia y frena la reserva en la app.',
            $byName['Urgencia']['pasos'][0]['hace']
        );
        $this->assertSame(['Llamar al 107'], $byName['Urgencia']['pasos'][0]['ofrece']);
        $this->assertSame(
            'Termina cuando la persona recibe orientación por urgencia, también para alguien que no está a cargo.',
            $byName['Urgencia']['exito']
        );

        $this->assertArrayHasKey('Malestar nuevo', $byName);
        $malestar = $byName['Malestar nuevo'];
        $this->assertSame('se reserva un turno para quien está a cargo', $malestar['objetivo']);
        $this->assertSame('La persona elige la zona del malestar.', $malestar['pasos'][0]['hace']);
        $this->assertContains('Síntoma general (fiebre, cansancio u otro)', $malestar['pasos'][0]['elige_entre']);
        $this->assertSame('La persona elige cómo atenderse.', $malestar['pasos'][1]['hace']);
        $this->assertSame(['Presencial', 'Videollamada', 'Por mensaje'], $malestar['pasos'][1]['elige_entre']);
        $this->assertSame(
            'La persona elige un servicio entre los filtrados por la forma de atenderse.',
            $malestar['pasos'][2]['hace']
        );
        $this->assertSame(
            'La persona elige un centro que ofrece el servicio elegido.',
            $malestar['pasos'][3]['hace']
        );
        $this->assertSame('Termina cuando se reserva un turno para quien está a cargo.', $malestar['exito']);

        $this->assertArrayHasKey('Control/Seguimiento', $byName);
        $this->assertSame('quien está a cargo envía la consulta', $byName['Control/Seguimiento']['objetivo']);
        $this->assertStringNotContainsString('triage_raiz', $block);
        $this->assertStringNotContainsString('Por lo que indicaste', $block);
    }

    public function testFormatForIntentIdsJoinsMultipleBotones(): void
    {
        $wrapped = IntentSemanticsPromptFormatter::formatForIntentIds([
            'turnos.crear-como-paciente',
            'atencion.necesito-atencion',
        ], 4);
        $data = json_decode($wrapped, true);

        $this->assertCount(2, $data['funcionalidades']);
        $this->assertSame('Turno con un especialista', $data['funcionalidades'][0]['boton']);
        $this->assertSame('Solicitar Atención', $data['funcionalidades'][1]['boton']);
        $this->assertArrayHasKey('recorridos', $data['funcionalidades'][1]);
    }
}
