<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\SubIntentEngine\FlowStatechart;
use Symfony\Component\Yaml\Yaml;

class ProfesionalEfectorServicioCrearFlowTest extends Unit
{
    /**
     * @return array<string, mixed>
     */
    private function intent(): array
    {
        $path = dirname(__DIR__, 3)
            . '/components/Domain/Organization/Pes/Application/Flows/intents/create/profesional-efector-servicio.crear-flow.yaml';
        $intent = Yaml::parseFile($path);

        $this->assertIsArray($intent);

        return $intent;
    }

    public function testPersonaSinCuilAvanzaALaCapturaDeCuil(): void
    {
        $state = FlowStatechart::find($this->intent(), 'select_persona');

        $this->assertNotNull($state);
        $this->assertSame(
            'capture_cuil',
            FlowStatechart::resolveNext($state, ['persona_requiere_cuil' => 'SI'])
        );
    }

    public function testPersonaConCuilAvanzaASeleccionDeServicio(): void
    {
        $state = FlowStatechart::find($this->intent(), 'select_persona');

        $this->assertNotNull($state);
        $this->assertSame(
            'select_servicio',
            FlowStatechart::resolveNext($state, ['persona_requiere_cuil' => 'NO'])
        );
    }

    public function testSoloServiciosQueAceptanTurnosAvanzanAConfigurarAgenda(): void
    {
        $state = FlowStatechart::find($this->intent(), 'select_servicio');

        $this->assertNotNull($state);
        $this->assertSame(
            'configurar_agenda_datos',
            FlowStatechart::resolveNext($state, ['servicio_acepta_turnos' => 'SI'])
        );
        $this->assertSame(
            'finalizar_alta',
            FlowStatechart::resolveNext($state, ['servicio_acepta_turnos' => 'NO'])
        );
    }
}
