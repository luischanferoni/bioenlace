<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;
use common\components\Platform\Assistant\Service\AssistantDraftNormalizer;

/**
 * Regresión: flows con `provides` (formulario) no deben romper el sobre con "Array to string conversion".
 * El wire `kind: flow` omite scaffolds inactivos y metadata de manifiesto no usada por clientes.
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
                'label' => 'Consultar indicadores',
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
        $this->assertArrayHasKey('submit', $envelope);
        $this->assertTrue($envelope['submit']['active']);
        $this->assertSame('Consultar indicadores', $envelope['submit']['label']);
        $this->assertArrayNotHasKey('dismiss', $envelope);
        $this->assertArrayNotHasKey('hints', $envelope);
        $this->assertArrayNotHasKey('draft_delta', $envelope['session']);
        $this->assertSame(
            ['fecha_desde', 'fecha_hasta', 'id_profesional_efector_servicio'],
            $envelope['step']['provides']
        );
        $this->assertArrayNotHasKey('composer_capture', $envelope['step']);
        $this->assertArrayNotHasKey('draft_keys', $envelope['manifest']);
        $this->assertArrayNotHasKey('entry_subintent_id', $envelope['manifest']);
        $this->assertArrayNotHasKey('schema_version', $envelope['manifest']);
    }

    public function testFlowFromMotorOmitsInactiveScaffolds(): void
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
        $this->assertArrayNotHasKey('action_id', $envelope['step']);
        $this->assertArrayNotHasKey('client_open', $envelope['step']);
        $this->assertArrayNotHasKey('provides', $envelope['step']);
        $this->assertArrayNotHasKey('pending_fields', $envelope['step']);
        $this->assertArrayNotHasKey('submit', $envelope);
        $this->assertArrayNotHasKey('dismiss', $envelope);
        $this->assertArrayNotHasKey('hints', $envelope);
    }

    public function testFlowFromMotorPreservesNativeClientOpenTargets(): void
    {
        $motor = [
            'success' => true,
            'text' => 'Representantes',
            'intent_id' => 'personas.designar-representante-flow',
            'subintent_id' => 'gestionar_delegacion',
            'open_ui' => [
                'action_id' => 'person-representation.hub',
                'client_open' => [
                    'kind' => 'native',
                    'mobile' => [
                        'screen_id' => 'person_representation_hub',
                    ],
                ],
            ],
            'draft_delta' => (object) [],
        ];

        $envelope = AssistantEnvelope::fromMotorResponse($motor);

        $this->assertSame('native', $envelope['step']['client_open']['kind']);
        $this->assertSame(
            'person_representation_hub',
            $envelope['step']['client_open']['mobile']['screen_id']
        );
        $this->assertArrayNotHasKey('web', $envelope['step']['client_open']);
    }

    public function testFlowFromMotorComposerCaptureStep(): void
    {
        $motor = [
            'success' => true,
            'text' => 'Describí tu consulta',
            'intent_id' => 'atencion.necesito-atencion',
            'subintent_id' => 'solicitud_async',
            'composer_capture' => [
                'active' => true,
                'draft_field' => 'mensaje',
                'placeholder' => 'Contanos tu consulta…',
                'min_length' => 10,
                'action_id' => 'consulta-async.solicitar-como-paciente',
                'route' => '/api/v1/consulta-async/solicitar-como-paciente',
                'method' => 'POST',
                'body_template' => [
                    'mensaje' => 'draft.mensaje',
                    'triage_raiz' => 'draft.triage_raiz',
                ],
            ],
            'required_draft_fields' => ['draft.mensaje'],
            'draft_delta' => (object) [],
        ];

        $envelope = AssistantEnvelope::fromMotorResponse($motor);

        $this->assertSame('flow', $envelope['kind']);
        $this->assertTrue($envelope['step']['composer_capture']['active']);
        $this->assertSame('mensaje', $envelope['step']['composer_capture']['draft_field']);
        $this->assertFalse($envelope['step']['active']);
        $this->assertArrayNotHasKey('client_open', $envelope['step']);
        $this->assertSame(['draft.mensaje'], $envelope['step']['pending_fields']);
    }

    public function testFlowFromMotorStripsRedundantActiveStepUiWhenClientOpenPresent(): void
    {
        $motor = [
            'success' => true,
            'text' => 'Elegí el servicio',
            'intent_id' => 'profesional-horarios.gestionar-propio',
            'subintent_id' => 'select_servicio',
            'open_ui' => [
                'action_id' => 'profesional-efector-servicio.listar-mis-servicios-en-efector',
                'client_open' => [
                    'kind' => 'ui_json',
                    'api' => [
                        'route' => '/api/v1/profesional-efector-servicio/listar-mis-servicios-en-efector',
                        'method' => 'GET|POST',
                        'query' => ['incluir_sin_agenda' => '1'],
                    ],
                ],
            ],
            'provides' => ['id_servicio', 'id_profesional_efector_servicio'],
            'flow_manifest' => [
                'schema_version' => '1',
                'intent_id' => 'profesional-horarios.gestionar-propio',
                'action_name' => 'Configurar mis horarios',
                'operation' => 'edit',
                'crud_tone' => 'update',
                'draft_keys' => ['id_servicio'],
                'entry_subintent_id' => 'select_servicio',
                'steps' => [
                    [
                        'id' => 'select_servicio',
                        'assistant_text' => 'Elegí el servicio',
                        'next' => 'select_encounter_class',
                        'provides' => ['draft.id_servicio'],
                    ],
                ],
                'active_subintent_id' => 'select_servicio',
                'active_step' => [
                    'id' => 'select_servicio',
                    'assistant_text' => 'Elegí el servicio',
                    'requires' => [],
                    'provides' => ['draft.id_servicio'],
                    'next' => 'select_encounter_class',
                    'ui' => [
                        'default_tab' => 'default',
                        'tabs' => [
                            [
                                'id' => 'default',
                                'label' => 'Elegir',
                                'action_id' => 'profesional-efector-servicio.listar-mis-servicios-en-efector',
                                'route' => '/api/v1/profesional-efector-servicio/listar-mis-servicios-en-efector',
                                'params' => ['incluir_sin_agenda' => '1'],
                            ],
                        ],
                    ],
                ],
            ],
            'draft_delta' => ['id_servicio' => 3],
        ];

        $envelope = AssistantEnvelope::fromMotorResponse($motor);

        $this->assertArrayNotHasKey('ui', $envelope['manifest']['active_step']);
        $this->assertSame('select_servicio', $envelope['manifest']['active_step']['id']);
        $this->assertSame('Elegí el servicio', $envelope['manifest']['active_step']['assistant_text']);
        $this->assertArrayNotHasKey('requires', $envelope['manifest']['active_step']);
        $this->assertArrayNotHasKey('draft_keys', $envelope['manifest']);
        $this->assertSame(['id_servicio' => 3], $envelope['session']['draft_delta']);
        $this->assertSame('ui_json', $envelope['step']['client_open']['kind']);
    }

    public function testFlowFromMotorKeepsMultiTabActiveStepUi(): void
    {
        $motor = [
            'success' => true,
            'text' => 'Elegí',
            'intent_id' => 'demo.multi-tab',
            'subintent_id' => 'pick',
            'open_ui' => [
                'action_id' => 'demo.a',
                'client_open' => [
                    'kind' => 'ui_json',
                    'api' => [
                        'route' => '/api/v1/demo/a',
                        'method' => 'GET|POST',
                    ],
                ],
            ],
            'flow_manifest' => [
                'intent_id' => 'demo.multi-tab',
                'action_name' => 'Demo',
                'steps' => [['id' => 'pick', 'assistant_text' => 'Elegí', 'next' => '']],
                'active_subintent_id' => 'pick',
                'active_step' => [
                    'id' => 'pick',
                    'assistant_text' => 'Elegí',
                    'ui' => [
                        'default_tab' => 'a',
                        'tabs' => [
                            ['id' => 'a', 'label' => 'A', 'route' => '/api/v1/demo/a'],
                            ['id' => 'b', 'label' => 'B', 'route' => '/api/v1/demo/b'],
                        ],
                    ],
                ],
            ],
        ];

        $envelope = AssistantEnvelope::fromMotorResponse($motor);

        $this->assertArrayHasKey('ui', $envelope['manifest']['active_step']);
        $this->assertCount(2, $envelope['manifest']['active_step']['ui']['tabs']);
    }

    public function testInteractiveButtonKeepsOriginContent(): void
    {
        $envelope = AssistantEnvelope::interactive('Oferta', [
            [
                'label' => 'Solicitar Atención',
                'intent_id' => 'atencion.necesito-atencion',
                'content' => 'Necesito una ecografía',
            ],
        ]);

        $this->assertSame('interactive', $envelope['kind']);
        $this->assertSame('Necesito una ecografía', $envelope['buttons'][0]['content']);
    }
}
