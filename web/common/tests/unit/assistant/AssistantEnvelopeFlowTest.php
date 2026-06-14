<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Assistant\EntryPoints\Chat\Envelope\AssistantEnvelope;
use common\components\Assistant\Service\AssistantDraftNormalizer;

/**
 * Regresión: flows con `provides` (formulario) no deben romper el sobre con "Array to string conversion".
 */
class AssistantEnvelopeFlowTest extends Unit
{
    public function testScalarStringRejectsArrays(): void
    {
        $this->assertSame('', AssistantDraftNormalizer::scalarString(['nested' => 'x']));
        $this->assertSame('ok', AssistantDraftNormalizer::scalarString(' ok '));
    }

    public function testFlowFromMotorTurnosIndicadoresLikePayload(): void
    {
        $motor = [
            'success' => true,
            'text' => 'Período y filtros de agenda',
            'intent_id' => 'turnos.indicadores-agenda-flow',
            'subintent_id' => 'consultar_indicadores',
            'open_ui' => [
                'action_id' => 'turnos.indicadores-agenda',
                'client_open' => [
                    'kind' => 'ui_json',
                    'api' => [
                        'route' => '/api/v1/turnos/indicadores-agenda',
                        'method' => 'GET|POST',
                        'query' => (object) [],
                    ],
                ],
            ],
            'provides' => ['fecha_desde', 'fecha_hasta', 'id_profesional_efector_servicio'],
            'flow_submit' => [
                'action_id' => 'turnos.indicadores-agenda',
                'route' => '/api/v1/turnos/indicadores-agenda',
                'method' => 'POST',
                'body_template' => [
                    'fecha_desde' => 'draft.fecha_desde',
                    'fecha_hasta' => 'draft.fecha_hasta',
                    'id_profesional_efector_servicio' => 'draft.id_profesional_efector_servicio',
                ],
            ],
            'draft_delta' => (object) [],
        ];

        $envelope = AssistantEnvelope::fromMotorResponse($motor);

        $this->assertSame('flow', $envelope['kind']);
        $this->assertTrue($envelope['step']['active']);
        $this->assertSame('turnos.indicadores-agenda', $envelope['step']['action_id']);
        $this->assertSame('ui_json', $envelope['step']['client_open']['kind']);
        $this->assertSame(
            '/api/v1/turnos/indicadores-agenda',
            $envelope['step']['client_open']['api']['route']
        );
        $this->assertTrue($envelope['submit']['active']);
    }

    public function testFlowFromMotorToleratesArrayLikeClientOpenFields(): void
    {
        $motor = [
            'success' => true,
            'text' => 'Listo',
            'intent_id' => 'turnos.indicadores-agenda-flow',
            'subintent_id' => 'consultar_indicadores',
            'open_ui' => [
                'action_id' => ['invalid'],
                'client_open' => [
                    'kind' => ['ui_json'],
                    'api' => [
                        'route' => ['/api/v1/turnos/indicadores-agenda'],
                        'method' => 'GET|POST',
                    ],
                ],
            ],
            'provides' => [['draft.fecha_desde']],
            'required_draft_fields' => [['draft.internacion_id']],
            'draft_delta' => (object) [],
        ];

        $envelope = AssistantEnvelope::fromMotorResponse($motor);

        $this->assertSame('flow', $envelope['kind']);
        $this->assertFalse($envelope['step']['active']);
        $this->assertSame('', $envelope['step']['action_id']);
        $this->assertSame('', $envelope['step']['client_open']['kind']);
        $this->assertSame([], $envelope['step']['provides']);
        $this->assertSame([], $envelope['step']['pending_fields']);
    }
}
