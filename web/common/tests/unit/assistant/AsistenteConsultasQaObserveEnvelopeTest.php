<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Qa\AsistenteConsultasQaService;

class AsistenteConsultasQaObserveEnvelopeTest extends Unit
{
    public function testFlowEnvelopeV3ExposesSessionIntentId(): void
    {
        $obs = AsistenteConsultasQaService::observeEnvelope([
            'kind' => 'flow',
            'text' => '',
            'session' => [
                'intent_id' => 'turnos.cancelar-como-paciente-flow',
                'subintent_id' => 'select_turno',
            ],
            'manifest' => [],
            'step' => [],
        ]);

        $this->assertSame('turnos.cancelar-como-paciente-flow', $obs['flow_intent_id']);
        $this->assertContains('turnos.cancelar-como-paciente-flow', $obs['intent_refs']);
        $this->assertStringContainsString('turnos.cancelar-como-paciente-flow', (string) $obs['reply_text']);
    }

    public function testLabFlowEnvelopePassesIntentIdsAny(): void
    {
        $obs = AsistenteConsultasQaService::observeEnvelope([
            'kind' => 'flow',
            'text' => 'Elegí un informe',
            'session' => [
                'intent_id' => 'laboratorio.ver-resultados-como-paciente',
                'subintent_id' => 'ver_listado',
            ],
            'manifest' => [],
            'step' => [],
        ]);

        $failures = AsistenteConsultasQaService::evaluateExpect([
            'user_goal' => 'operational',
            'intent_ids_any' => ['laboratorio.ver-resultados-como-paciente'],
        ], array_merge($obs, ['user_goal' => 'operational']));

        $this->assertSame([], $failures);
    }
}
