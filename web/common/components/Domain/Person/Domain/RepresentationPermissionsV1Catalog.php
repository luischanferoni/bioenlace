<?php

namespace common\components\Domain\Person\Domain;

/**
 * Catálogo de dominio (ex Person/Representation/metadata/representation_permissions_v1.yaml).
 */
final class RepresentationPermissionsV1Catalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 1,
        'template_id' => 'representation_permissions_v1',
        'permissions' => [
            'scheduling.turno' => [
                'label' => 'Turnos y pedido de atención',
            ],
            'clinical.motivos' => [
                'label' => 'Motivos de consulta',
            ],
            'clinical.care_pack_assistance' => [
                'label' => 'Pre y post consulta de cohorte (asistencia y seguimiento)',
            ],
            'clinical.care_plan' => [
                'label' => 'Tratamientos, condiciones y recetas delegadas',
            ],
            'clinical.historia_resumen' => [
                'label' => 'Historia clínica (alcance paciente)',
            ],
        ],
        'default_permissions' => [
            0 => 'scheduling.turno',
            1 => 'clinical.motivos',
            2 => 'clinical.care_pack_assistance',
            3 => 'clinical.care_plan',
            4 => 'clinical.historia_resumen',
        ],
    ];
    }
}
