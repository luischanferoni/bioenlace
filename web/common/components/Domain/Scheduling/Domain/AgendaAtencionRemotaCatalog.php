<?php

namespace common\components\Domain\Scheduling\Domain;

/**
 * Catálogo de dominio (ex Scheduling/metadata/agenda_atencion_remota.yaml).
 */
final class AgendaAtencionRemotaCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => '1',
        'configurar_agenda' => [
            'info_message' => '',
            'acepta_consultas_online' => [
                'label' => 'Acepto videollamada en esta agenda',
                'hint' => 'Los pacientes podrán reservar turnos remotos por video en tus horarios publicados. No cambia turnos ya confirmados. La consulta clínica por mensaje funciona aunque dejes esto en No.',
            ],
        ],
        'insight_agenda_config' => [
            'action_id' => 'profesional-horarios.gestionar-propio',
            'link_label' => 'Configurar mis horarios',
            'assistant_url_path' => '/site/asistente',
        ],
        'kpi' => [
            'label' => 'Presencial (remoto posible)',
            'periodo_dias' => 30,
            'elegibilidad' => 'sugerido',
        ],
    ];
    }
}
