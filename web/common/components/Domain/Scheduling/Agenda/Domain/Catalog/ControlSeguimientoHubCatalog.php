<?php

namespace common\components\Domain\Scheduling\Agenda\Domain\Catalog;

/**
 * Catálogo de dominio (ex Scheduling/metadata/control_seguimiento_hub.yaml).
 */
final class ControlSeguimientoHubCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => '1',
        'hub' => [
            'title' => '¿Sobre qué es el control o seguimiento?',
            'labels' => [
                'care_plan' => 'Tratamiento: {name}',
                'care_plan_fallback_name' => 'Plan',
                'care_plan_active' => 'Plan activo',
                'care_plan_since' => 'Desde {date}',
                'condition' => 'Condición: {name}',
                'condition_active' => 'Activa',
                'condition_protocol' => 'Protocolo: {title}',
                'protocol_profile_subtitle' => 'Sugerido según tu perfil · Consultá con tu equipo',
            ],
        ],
        'condition_default_actions' => [
            0 => [
                'code' => 'consulta_mensaje',
                'label' => 'Consulta por mensaje',
                'description' => 'Contá tu consulta vinculada a esta condición.',
                'draft' => [
                    'intake_tipo' => 'consulta_general',
                ],
            ],
            1 => [
                'code' => 'solicitar_turno',
                'label' => 'Pedir turno',
                'description' => 'Reservá un control presencial o por videollamada.',
                'draft' => [
                    'triage_raiz' => 'seguimiento_cronico',
                ],
            ],
        ],
    ];
    }
}
