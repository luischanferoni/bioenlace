<?php

namespace common\components\Domain\Scheduling\Domain;

/**
 * Catálogo de dominio (ex Scheduling/metadata/reserva_modalidad_atencion.yaml).
 */
final class ReservaModalidadAtencionCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => '1',
        'opciones' => [
            'presencial' => [
                'code' => 'presencial',
                'label' => 'Presencial (voy al centro de salud)',
                'label_short' => 'Presencial',
                'always_if_not_halt' => true,
            ],
            'teleconsulta' => [
                'code' => 'teleconsulta',
                'label' => 'Remoto (videollamada con turno)',
                'label_short' => 'Videollamada',
                'requires_teleconsulta_visible' => true,
            ],
            'async' => [
                'code' => 'async',
                'label' => 'Consulta clínica por mensaje (sin turno ni videollamada)',
                'label_short' => 'Por mensaje',
                'requires_triage_raiz' => [
                    0 => 'seguimiento_cronico',
                ],
                'requires_elegibilidad' => [
                    0 => 'sugerido',
                    1 => 'permitido',
                ],
            ],
        ],
        'teleconsulta_hub_sin_cupos' => [
            'summary' => 'No hay horarios de videollamada disponibles en este momento.',
            'hint' => 'Podés elegir consulta clínica por mensaje en el paso anterior o intentar un turno presencial.',
        ],
    ];
    }
}
