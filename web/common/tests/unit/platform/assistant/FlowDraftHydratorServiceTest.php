<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\SubIntentEngine\FlowDraftHydratorService;

class FlowDraftHydratorServiceTest extends Unit
{
    public function testScalarDraftDiffOmiteAssistantTextYRepetidos(): void
    {
        $before = ['triage_raiz' => 'malestar_nuevo'];
        $after = [
            'triage_raiz' => 'estudio_pedido',
            'pedido_acto' => 'http://snomed.info/sct|16310003',
            'assistant_text' => 'no viaja al cliente',
            '_interno' => 'x',
        ];
        $delta = FlowDraftHydratorService::scalarDraftDiff($before, $after);

        $this->assertSame('estudio_pedido', $delta['triage_raiz']);
        $this->assertSame('http://snomed.info/sct|16310003', $delta['pedido_acto']);
        $this->assertArrayNotHasKey('assistant_text', $delta);
        $this->assertArrayNotHasKey('_interno', $delta);
    }

    public function testMergeDeltaActualizaValoresDerivadosYConservaOtrosCampos(): void
    {
        $motor = [
            'draft_delta' => [
                'servicio_acepta_turnos' => 'SI',
                'id_servicio' => '81',
            ],
        ];
        $merged = FlowDraftHydratorService::mergeDeltaIntoMotor($motor, [
            'servicio_acepta_turnos' => 'NO',
        ]);

        $this->assertSame('NO', $merged['draft_delta']['servicio_acepta_turnos']);
        $this->assertSame('81', $merged['draft_delta']['id_servicio']);
    }
}
