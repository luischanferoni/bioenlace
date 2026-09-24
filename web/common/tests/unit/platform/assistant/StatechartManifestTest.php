<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\SubIntentEngine\StatechartManifest;
use Symfony\Component\Yaml\Yaml;

class StatechartManifestTest extends Unit
{
    public function testAlwaysConGuardaYComodin(): void
    {
        $out = StatechartManifest::apply([
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
                        'provides' => ['draft.triage_raiz'],
                        'open_ui' => ['action_id' => 'turnos.reserva-triage-paso'],
                    ],
                    'always' => [
                        ['guard' => ['triage_raiz' => 'urgencia'], 'target' => 'triage_urgencia'],
                        ['target' => 'select_tipo_atencion'],
                    ],
                ],
            ],
        ]);

        $steps = $out['subintents'];
        $this->assertSame('triage_raiz', $steps[0]['id']);
        $this->assertSame('Motivo', $steps[0]['assistant_text']);
        $this->assertTrue($steps[0]['review_prefilled']);
        $this->assertSame('turnos.reserva-triage-paso', $steps[0]['open_ui']['action_id']);
        $this->assertSame(
            [
                'when' => ['draft_equals' => ['triage_raiz' => 'urgencia']],
                'next' => 'triage_urgencia',
            ],
            $steps[0]['next_routing'][0]
        );
        $this->assertSame(['default' => true], $steps[0]['next_routing'][1]['when']);
        $this->assertSame('select_efector', $steps[1]['next']);
        $this->assertSame(['triage_raiz', 'pedido_acto'], $out['draft_keys_extra']);
    }

    public function testEstadoFinalNoTieneTransicion(): void
    {
        $out = StatechartManifest::apply([
            'states' => [
                'completar' => [
                    'description' => 'Completá el formulario',
                    'type' => 'final',
                    'meta' => [
                        'open_ui' => ['action_id' => 'queja-paciente.enviar-como-paciente'],
                    ],
                    'always' => 'ignorado',
                ],
            ],
        ]);

        $step = $out['subintents'][0];
        $this->assertSame('completar', $step['id']);
        $this->assertArrayNotHasKey('next', $step);
        $this->assertArrayNotHasKey('next_routing', $step);
        $this->assertSame('queja-paciente.enviar-como-paciente', $step['open_ui']['action_id']);
    }

    public function testPilotoQuejaEnDisco(): void
    {
        $path = dirname(__DIR__, 4)
            . '/components/Platform/Assistant/Application/Flows/intents/create/plataforma.enviar-queja-como-paciente-flow.yaml';
        $data = Yaml::parseFile($path);
        $this->assertIsArray($data);
        $out = StatechartManifest::apply($data);
        $this->assertArrayNotHasKey('subintents', $data);
        $this->assertSame('completar', $out['subintents'][0]['id']);
        $this->assertSame('final', $data['states']['completar']['type']);
        $this->assertArrayNotHasKey('next', $out['subintents'][0]);
    }

    public function testGuardaConDestinoVacioTerminaLaRama(): void
    {
        $out = StatechartManifest::apply([
            'states' => [
                'cs_select_medicamentos' => [
                    'always' => [
                        ['guard' => ['seguimiento_necesidad' => 'solicitar_ajuste'], 'target' => 'cs_captura_ajuste_motivo'],
                        ['guard' => ['seguimiento_necesidad' => 'renovar_medicacion'], 'target' => ''],
                    ],
                ],
            ],
        ]);

        $routing = $out['subintents'][0]['next_routing'];
        $this->assertSame('', $routing[1]['next']);
        $this->assertSame(
            ['seguimiento_necesidad' => 'renovar_medicacion'],
            $routing[1]['when']['draft_equals']
        );
    }

    public function testComodinConDestinoVacioTerminaLaRama(): void
    {
        $out = StatechartManifest::apply([
            'states' => [
                'select_servicio' => [
                    'always' => [
                        ['guard' => ['servicio_acepta_turnos' => 'SI'], 'target' => 'configurar_agenda_datos'],
                        ['target' => ''],
                    ],
                ],
            ],
        ]);

        $routing = $out['subintents'][0]['next_routing'];
        $this->assertTrue($routing[1]['when']['default']);
        $this->assertSame('', $routing[1]['next']);
    }

    public function testSinStatesNoTocaElManifiesto(): void
    {
        $raw = ['subintents' => [['id' => 'a', 'next' => '']]];
        $this->assertSame($raw, StatechartManifest::apply($raw));
    }
}
