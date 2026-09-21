<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Agents;

/**
 * Política operativa del agente `turno-resolucion-multicanal` (ex YAML platform/agents).
 */
final class TurnoResolucionMulticanalAgentPolicy
{
    public const AGENT_ID = 'turno-resolucion-multicanal';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 1,
        'agent_id' => 'turno-resolucion-multicanal',
        'escalation_hours_after_push' => 24,
        'hours_between_channels' => 12,
        'legal_hour_start' => '09:00',
        'legal_hour_end' => '21:00',
        'link_ttl_days' => 7,
        'channels' => [
            0 => [
                'id' => 'push',
                'label' => 'Push app',
            ],
            1 => [
                'id' => 'email',
                'label' => 'Email',
                'stub' => true,
            ],
            2 => [
                'id' => 'sms',
                'label' => 'SMS',
                'stub' => true,
            ],
        ],
        'message_templates' => [
            'email' => [
                'subject' => 'Tu turno requiere una nueva cita',
                'body' => 'Hola {{nombre}}, tu turno del {{fecha}} a las {{hora}} necesita una nueva cita. Abrí el enlace para cambiar el horario: {{link}}',
            ],
            'sms' => [
                'body' => 'Bioenlace: tu turno del {{fecha}} {{hora}} necesita nueva cita. Cambiá el horario: {{link}}',
            ],
        ],
    ];
    }
}
