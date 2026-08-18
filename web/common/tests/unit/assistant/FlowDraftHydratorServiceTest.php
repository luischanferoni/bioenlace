<?php

namespace common\tests\unit\assistant;

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

    public function testMergeDeltaNoPisaSeleccionDelMotor(): void
    {
        $motor = [
            'draft_delta' => ['pedido_acto' => 'elegido-por-el-usuario'],
        ];
        $merged = FlowDraftHydratorService::mergeDeltaIntoMotor($motor, [
            'triage_raiz' => 'estudio_pedido',
            'pedido_acto' => 'desde-hydrator',
        ]);

        $this->assertSame('estudio_pedido', $merged['draft_delta']['triage_raiz']);
        $this->assertSame('elegido-por-el-usuario', $merged['draft_delta']['pedido_acto']);
    }
}
