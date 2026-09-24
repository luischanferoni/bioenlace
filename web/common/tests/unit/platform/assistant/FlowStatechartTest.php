<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\SubIntentEngine\FlowStatechart;
use Symfony\Component\Yaml\Yaml;

class FlowStatechartTest extends Unit
{
    public function testResolveNextPorGuardaYComodin(): void
    {
        $intent = [
            'initial' => 'triage_raiz',
            'context' => ['triage_raiz', 'pedido_acto'],
            'states' => [
                'select_servicio' => [
                    'description' => 'Servicio',
                    'always' => 'select_efector',
                ],
                'triage_raiz' => [
                    'description' => 'Motivo',
                    'meta' => [
                        'review_prefilled' => true,
                        'open_ui' => ['action_id' => 'turnos.reserva-triage-paso'],
                    ],
                    'always' => [
                        ['guard' => ['triage_raiz' => 'urgencia'], 'target' => 'triage_urgencia'],
                        ['target' => 'select_tipo_atencion'],
                    ],
                ],
            ],
        ];

        $steps = FlowStatechart::ordered($intent);
        $this->assertSame('triage_raiz', $steps[0]['id']);
        $this->assertSame('Motivo', $steps[0]['assistant_text']);
        $this->assertTrue($steps[0]['review_prefilled']);
        $this->assertSame('turnos.reserva-triage-paso', $steps[0]['open_ui']['action_id']);
        $this->assertSame('triage_urgencia', FlowStatechart::resolveNext($steps[0], ['triage_raiz' => 'urgencia']));
        $this->assertSame('select_tipo_atencion', FlowStatechart::resolveNext($steps[0], []));
        $this->assertSame('select_efector', FlowStatechart::resolveNext($steps[1], []));
        $this->assertSame(['triage_raiz', 'pedido_acto'], FlowStatechart::contextKeys($intent));
    }

    public function testEstadoFinalNoTieneSalida(): void
    {
        $intent = [
            'states' => [
                'completar' => [
                    'description' => 'Completá el formulario',
                    'type' => 'final',
                    'always' => 'ignorado',
                    'meta' => [
                        'open_ui' => ['action_id' => 'queja-paciente.enviar-como-paciente'],
                    ],
                ],
            ],
        ];
        $step = FlowStatechart::ordered($intent)[0];
        $this->assertFalse(FlowStatechart::hasOutgoing($step));
        $this->assertSame('', FlowStatechart::resolveNext($step, []));
        $this->assertSame('queja-paciente.enviar-como-paciente', $step['open_ui']['action_id']);
    }

    public function testGuardaYComodinConDestinoVacio(): void
    {
        $intent = [
            'states' => [
                'cs_select_medicamentos' => [
                    'always' => [
                        ['guard' => ['seguimiento_necesidad' => 'solicitar_ajuste'], 'target' => 'cs_captura_ajuste_motivo'],
                        ['guard' => ['seguimiento_necesidad' => 'renovar_medicacion'], 'target' => ''],
                    ],
                ],
                'select_servicio' => [
                    'always' => [
                        ['guard' => ['servicio_acepta_turnos' => 'SI'], 'target' => 'configurar_agenda_datos'],
                        ['target' => ''],
                    ],
                ],
            ],
        ];
        $med = FlowStatechart::find($intent, 'cs_select_medicamentos');
        $servicio = FlowStatechart::find($intent, 'select_servicio');
        $this->assertNotNull($med);
        $this->assertNotNull($servicio);
        $this->assertSame('', FlowStatechart::resolveNext($med, ['seguimiento_necesidad' => 'renovar_medicacion']));
        $this->assertSame('cs_captura_ajuste_motivo', FlowStatechart::resolveNext($med, ['seguimiento_necesidad' => 'solicitar_ajuste']));
        $this->assertSame('', FlowStatechart::resolveNext($servicio, []));
        $this->assertTrue(FlowStatechart::hasOutgoing($med));
    }

    public function testPilotoQuejaEnDisco(): void
    {
        $path = dirname(__DIR__, 4)
            . '/components/Platform/Assistant/Application/Flows/intents/create/plataforma.enviar-queja-como-paciente-flow.yaml';
        $data = Yaml::parseFile($path);
        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('subintents', $data);
        $step = FlowStatechart::ordered($data)[0];
        $this->assertSame('completar', $step['id']);
        $this->assertSame('final', $data['states']['completar']['type']);
        $this->assertFalse(FlowStatechart::hasOutgoing($step));
    }
}
