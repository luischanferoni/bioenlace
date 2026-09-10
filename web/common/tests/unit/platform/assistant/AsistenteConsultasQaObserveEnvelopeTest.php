<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Qa\AsistenteConsultasQaService;

class AsistenteConsultasQaObserveEnvelopeTest extends Unit
{
    protected function _after(): void
    {
        AssistantPlanningLogService::resetForTests();
        ChatPreprocessContext::clear();
    }

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

    public function testEffectiveUserGoalFromIncompletasRouting(): void
    {
        AssistantPlanningLogService::resetForTests();
        AssistantPlanningLogService::begin([
            'normalized_text' => 'pinchazo',
            'necesidad_usuario' => '',
            'routing_hint' => 'pedido_claro',
            'tags' => [],
            'context_areas' => [],
            'extractions' => [],
            'intent_ids_hint' => [],
        ], []);
        AssistantPlanningLogService::setRoutingResult('incompletas');
        AssistantPlanningLogService::setFinalPath('2ia_guide');

        ChatPreprocessContext::set([
            'ok' => true,
            'user_goal' => 'operational',
            'routing_hint' => 'pedido_claro',
            'normalized_text' => 'pinchazo',
            'tags' => [],
            'context_areas' => [],
        ]);

        $obs = AsistenteConsultasQaService::observeEnvelope([
            'kind' => 'interactive',
            'text' => 'Podés solicitar atención',
            'buttons' => [
                ['label' => 'Solicitar Atención', 'intent_id' => 'atencion.necesito-atencion'],
            ],
        ]);

        $this->assertSame('guide', $obs['user_goal']);
        $this->assertSame('operational', $obs['preprocess_user_goal']);
    }
}
