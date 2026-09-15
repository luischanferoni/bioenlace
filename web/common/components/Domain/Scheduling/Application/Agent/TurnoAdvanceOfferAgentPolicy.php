<?php

namespace common\components\Domain\Scheduling\Application\Agent;

/**
 * Política operativa del agente `turno-advance-offer` (ex YAML platform/agents).
 */
final class TurnoAdvanceOfferAgentPolicy
{
    public const AGENT_ID = 'turno-advance-offer';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 1,
        'agent_id' => 'turno-advance-offer',
        'min_lead_minutes_to_offer' => 1440,
        'offer_step_minutes' => 120,
        'stop_new_offers_minutes_before_slot' => 360,
        'candidate_horizon' => 'd2_same_halfday',
        'order' => 'd2_then_d1_same_halfday',
        'day_counting' => 'calendar',
        'halfday_split_hour' => 13,
        'compatibility' => [
            'same_efector' => true,
            'same_servicio' => true,
            'same_pes' => true,
            'same_modalidad' => true,
        ],
        'require_active_push' => true,
        'texts' => [
            'title' => 'Se liberó un turno más temprano',
            'body' => 'Hay un horario el {fecha} a las {hora}. Podés adelantar tu consulta si todavía está disponible.',
            'action_label' => 'Adelantar mi turno',
        ],
        'push_type' => 'TURNO_ADVANCE_OFFER',
        'action' => 'adelantar_turno',
    ];
    }
}
