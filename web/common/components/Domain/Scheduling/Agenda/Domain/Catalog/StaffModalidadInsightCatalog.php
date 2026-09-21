<?php

namespace common\components\Domain\Scheduling\Agenda\Domain\Catalog;

/**
 * Catálogo de dominio (ex Scheduling/metadata/staff_modalidad_insight.yaml).
 */
final class StaffModalidadInsightCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => '1',
        'show_when_elegibilidad' => [
            0 => 'sugerido',
            1 => 'permitido',
        ],
        'modalidades' => [
            'teleconsulta' => [
                'code' => 'teleconsulta',
                'label' => 'Videollamada con turno',
                'description' => 'El paciente reserva un horario y se atiende por video desde su casa o el consultorio.',
            ],
            'async' => [
                'code' => 'async',
                'label' => 'Consulta clínica por mensaje',
                'description' => 'Sin turno ni videollamada; el paciente escribe y un profesional real responde en un plazo acordado.',
            ],
        ],
        'elegibilidad_modalidades' => [
            'sugerido' => [
                0 => 'teleconsulta',
                1 => 'async',
            ],
            'permitido' => [
                0 => 'async',
            ],
        ],
        'messages' => [
            'sugerido' => [
                'summary' => 'Según el motivo de reserva, este caso suele resolverse sin concurrir presencialmente.',
                'tone' => 'info',
            ],
            'permitido' => [
                'summary' => 'Este motivo también puede atenderse de forma remota en algunos casos.',
                'tone' => 'secondary',
            ],
        ],
        'agenda_no_online_footer' => 'Tu agenda aún no ofrece atención remota. Podés habilitarla cuando quieras desde Configurar agenda.',
    ];
    }
}
