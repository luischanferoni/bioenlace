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
            'routing_hint' => 'guide',
            'tags' => [],
            'context_areas' => [],
            'extractions' => [],
            'intent_ids_hint' => [],
        ], []);
        AssistantPlanningLogService::setRoutingResult('incompletas');
        AssistantPlanningLogService::setFinalPath('2ia_guide');
        AssistantPlanningLogService::setGuidePrompt("SYSTEM\npregunta del paciente");

        ChatPreprocessContext::set([
            'ok' => true,
            'user_goal' => 'operational',
            'routing_hint' => 'guide',
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
        $this->assertSame("SYSTEM\npregunta del paciente", $obs['guide_prompt']);
    }

    public function testReadableReportIncludesGuidePrompt(): void
    {
        $txt = AsistenteConsultasQaService::formatReadableReport([
            'started_at' => '2026-01-01T00:00:00+00:00',
            'finished_at' => '2026-01-01T00:00:01+00:00',
            'user_id' => 1,
            'report_path' => '/tmp/x.json',
            'summary' => ['total' => 1, 'pass' => 1, 'fail' => 0, 'observe' => 0, 'error' => 0],
            'results' => [[
                'id' => 'smoke-demo',
                'status' => 'pass',
                'tipo' => 'síntoma',
                'seccion' => 'smoke',
                'cobertura' => 'Hoy',
                'failures' => [],
                'detalle' => [[
                    'indice' => 0,
                    'mensaje' => 'me duele la cabeza',
                    'observation' => [
                        'reply_text' => 'Orientación breve',
                        'kind' => 'interactive',
                        'buttons' => [],
                        'guide_prompt' => "PROMPT FINAL GUIDE\nlínea 2",
                        'planning_applied' => ['final_path' => '2ia_guide'],
                    ],
                ]],
            ]],
        ]);

        $this->assertStringContainsString('Prompt Guide (final):', $txt);
        $this->assertStringContainsString("PROMPT FINAL GUIDE\nlínea 2", $txt);
    }

    public function testClaraAnsweredByGuideCountsAsGuide(): void
    {
        AssistantPlanningLogService::resetForTests();
        AssistantPlanningLogService::begin([
            'normalized_text' => 'es una urgencia',
            'necesidad_usuario' => '',
            'routing_hint' => 'guide',
            'tags' => ['urgencia'],
            'context_areas' => [],
            'extractions' => [],
            'intent_ids_hint' => [],
        ], []);
        AssistantPlanningLogService::setRoutingResult('clara');
        AssistantPlanningLogService::setFinalPath('2ia_guide');

        ChatPreprocessContext::set([
            'ok' => true,
            'user_goal' => 'operational',
            'routing_hint' => 'guide',
            'normalized_text' => 'es una urgencia',
            'tags' => ['urgencia'],
            'context_areas' => [],
        ]);

        $obs = AsistenteConsultasQaService::observeEnvelope([
            'kind' => 'interactive',
            'text' => 'Si es una urgencia, andá a la guardia.',
            'buttons' => [
                ['label' => 'Solicitar Atención', 'intent_id' => 'atencion.necesito-atencion'],
            ],
        ]);

        $this->assertSame('guide', $obs['user_goal']);
    }
}
