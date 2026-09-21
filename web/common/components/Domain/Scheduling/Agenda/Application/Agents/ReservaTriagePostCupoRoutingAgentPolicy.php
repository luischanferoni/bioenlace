<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Agents;

/**
 * Política operativa del agente `reserva-triage-post-cupo-routing` (ex YAML platform/agents).
 */
final class ReservaTriagePostCupoRoutingAgentPolicy
{
    public const AGENT_ID = 'reserva-triage-post-cupo-routing';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 1,
        'agent_id' => 'reserva-triage-post-cupo-routing',
        'push' => [
            'title' => 'Alternativas de atención',
            'body_template' => '{{mensaje}}',
        ],
        'deep_links' => [
            'async' => '/asistente?intent=consulta-async.solicitar-flow',
            'tele_hub' => '/asistente?intent=turnos.crear-como-paciente&modo=teleconsulta_hub',
            'presencial_primaria' => '/asistente?intent=turnos.crear-como-paciente',
            'administrativo' => '/asistente?intent=atencion.tramite-admin-flow',
        ],
        'rules' => [
            0 => [
                'id' => 'banda_a_halt',
                'when' => [
                    'field' => 'urgency_band',
                    'equals' => 'A',
                ],
                'action' => 'halt',
                'mensaje' => 'Por lo que indicaste conviene atención urgente ahora. Acudí a guardia o emergencias.',
            ],
            1 => [
                'id' => 'tramite_admin',
                'when' => [
                    'field' => 'reserva_triage_code',
                    'in' => [
                        0 => 'tramite_admin',
                        1 => 'tramite',
                        2 => 'certificado',
                        3 => 'duplicado_receta',
                    ],
                ],
                'action' => 'recommend',
                'channel' => 'administrativo',
                'mensaje' => 'Tu trámite no requiere turno médico. Te guiamos por el canal administrativo digital.',
            ],
            2 => [
                'id' => 'cronico_async',
                'when' => [
                    'all' => [
                        0 => [
                            'field' => 'async_available',
                            'equals' => 'true',
                        ],
                        1 => [
                            'field' => 'urgency_band',
                            'in' => [
                                0 => 'C',
                                1 => 'D',
                            ],
                        ],
                    ],
                ],
                'action' => 'recommend',
                'channel' => 'async',
                'mensaje' => 'No hay turnos pronto; podés enviar una consulta clínica por mensaje y el equipo te responderá.',
                'commit_async' => false,
            ],
            3 => [
                'id' => 'tele_hub_sin_especialista',
                'when' => [
                    'all' => [
                        0 => [
                            'field' => 'tele_hub_available',
                            'equals' => 'true',
                        ],
                        1 => [
                            'field' => 'especialista_sin_cupo',
                            'equals' => 'true',
                        ],
                    ],
                ],
                'action' => 'recommend',
                'channel' => 'tele_hub',
                'mensaje' => 'No hay turno con el especialista elegido; te ofrecemos videollamada con clínica en los próximos días.',
            ],
            4 => [
                'id' => 'primaria_presencial',
                'when' => [
                    'field' => 'primaria_available',
                    'equals' => 'true',
                ],
                'action' => 'recommend',
                'channel' => 'presencial_primaria',
                'mensaje' => 'No hay cupo en el servicio elegido; podés reservar con clínica general con nota de derivación si corresponde.',
            ],
            5 => [
                'id' => 'sin_cupo_halt',
                'when' => [
                    'field' => 'slots_empty',
                    'equals' => 'true',
                ],
                'action' => 'halt',
                'mensaje' => 'No hay horarios disponibles ahora. Probá más tarde; si se libera un cupo compatible con un turno tuyo posterior, te podemos avisar para adelantarlo.',
            ],
        ],
    ];
    }
}
