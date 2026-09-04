<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Qa\AsistenteConsultasQaService;

class AsistenteConsultasQaCatalogTest extends Unit
{
    public function testCatalogLoadsAndHasSmokeCases(): void
    {
        $catalog = AsistenteConsultasQaService::loadCatalog();
        $this->assertNotEmpty($catalog['cases']);

        $ids = array_column($catalog['cases'], 'id');
        $this->assertContains('smoke-quiero-turno', $ids);
        $this->assertContains('smoke-sintoma-cabeza', $ids);
    }

    public function testFilterByCoberturaAndSeccion(): void
    {
        $smoke = AsistenteConsultasQaService::filterCases(['Hoy'], 'smoke', null, null);
        $this->assertNotEmpty($smoke);
        foreach ($smoke as $case) {
            $this->assertSame('Hoy', $case['cobertura']);
            $this->assertSame('smoke', $case['seccion']);
            $this->assertNotEmpty($case['mensajes']);
        }

        $borde = AsistenteConsultasQaService::filterCases(null, 'borde', null, null);
        $this->assertNotEmpty($borde);
        $ids = array_column($borde, 'id');
        $this->assertContains('borde-llegar-tarde-10min', $ids);
        $this->assertContains('fuera-sesion-medium', $ids);
    }

    public function testEvaluateExpectUserGoalAndIntent(): void
    {
        $obs = [
            'user_goal' => 'guide',
            'intent_refs' => ['atencion.necesito-atencion'],
            'button_intent_ids' => ['atencion.necesito-atencion'],
            'reply_text' => 'Podés solicitar atención.',
        ];
        $this->assertSame([], AsistenteConsultasQaService::evaluateExpect([
            'user_goal' => 'guide',
            'offer_intent' => 'atencion.necesito-atencion',
            'must_not_intent' => ['turnos.crear-como-paciente'],
        ], $obs));

        $failures = AsistenteConsultasQaService::evaluateExpect([
            'user_goal' => 'operational',
        ], $obs);
        $this->assertNotEmpty($failures);
    }

    public function testReadableFlowLegendFor2iaAnd1ia(): void
    {
        $twoIa = AsistenteConsultasQaService::formatFlowLegendLines([
            'user_goal' => 'guide',
            'routing_hint' => 'incompletas',
            'flow_intent_id' => '',
            'planning_applied' => [
                'final_path' => '2ia_synthesis',
                'routing_result' => 'incompletas',
                'executed_tools' => [
                    ['tool_id' => 'aspect:appointment.current'],
                    ['tool_id' => 'aspect:site.appointment.policies'],
                ],
            ],
        ]);
        $joined = implode("\n", $twoIa);
        $this->assertStringContainsString('preprocess + 2 IA', $joined);
        $this->assertStringContainsString('aspect:appointment.current', $joined);

        $oneIa = AsistenteConsultasQaService::formatFlowLegendLines([
            'user_goal' => 'operational',
            'routing_hint' => 'clara',
            'flow_intent_id' => '',
            'planning_applied' => [
                'final_path' => '1ia_dudosa',
                'routing_result' => 'dudosa',
                'executed_tools' => [],
            ],
        ]);
        $joined1 = implode("\n", $oneIa);
        $this->assertStringContainsString('preprocess + PHP solamente', $joined1);
        $this->assertStringContainsString('dudosa', $joined1);
    }

    public function testFormatReadableReportIncludesMessageAndReply(): void
    {
        $txt = AsistenteConsultasQaService::formatReadableReport([
            'started_at' => 't0',
            'finished_at' => 't1',
            'user_id' => 1,
            'report_path' => '/tmp/x.json',
            'summary' => ['total' => 1, 'pass' => 0, 'fail' => 1, 'observe' => 0, 'error' => 0],
            'results' => [[
                'id' => 'smoke-demo',
                'seccion' => 'smoke',
                'tipo' => 'demo',
                'cobertura' => 'Hoy',
                'status' => 'fail',
                'failures' => ['ejemplo'],
                'detalle' => [[
                    'indice' => 0,
                    'mensaje' => 'Quiero un turno',
                    'observation' => [
                        'reply_text' => 'Indicame el servicio.',
                        'buttons' => [
                            ['label' => 'Charla', 'intent_id' => 'assistant.channel.guide'],
                        ],
                        'user_goal' => 'guide',
                        'planning_applied' => [
                            'final_path' => '2ia_synthesis',
                            'routing_result' => 'incompletas',
                            'executed_tools' => [
                                ['tool_id' => 'aspect:appointment.current'],
                            ],
                        ],
                    ],
                ]],
            ]],
        ]);
        $this->assertStringContainsString('Usuario: Quiero un turno', $txt);
        $this->assertStringContainsString('Asistente: Indicame el servicio.', $txt);
        $this->assertStringContainsString('"Charla" → assistant.channel.guide', $txt);
        $this->assertStringContainsString('preprocess + 2 IA', $txt);
    }
}
