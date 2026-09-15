<?php

namespace common\components\Domain\Organization\Domain;

/**
 * Catálogo de dominio (ex metadata/bioenlace/organization/efector-atributos.yaml).
 */
final class EfectorAtributosCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 1,
        'atributos' => [
            'dependencia' => [
                0 => [
                    'value' => 'Nacional',
                    'label' => 'Nacional',
                ],
                1 => [
                    'value' => 'Provincial',
                    'label' => 'Provincial',
                ],
                2 => [
                    'value' => 'Municipal',
                    'label' => 'Municipal',
                ],
                3 => [
                    'value' => 'Privado',
                    'label' => 'Privado',
                ],
                4 => [
                    'value' => 'Obra social',
                    'label' => 'Obra social',
                ],
                5 => [
                    'value' => 'FFAA/Seguridad',
                    'label' => 'FFAA/Seguridad',
                ],
                6 => [
                    'value' => 'Servicio Penitenciario Federal',
                    'label' => 'Servicio Penitenciario Federal',
                ],
                7 => [
                    'value' => 'Otros',
                    'label' => 'Otros',
                ],
            ],
            'origen_financiamiento' => [
                0 => [
                    'value' => 'Público',
                    'label' => 'Público',
                ],
                1 => [
                    'value' => 'Privado',
                    'label' => 'Privado',
                ],
            ],
            'tipologia' => [
                0 => [
                    'value' => 'CAP',
                    'label' => 'CAP (centro de atención primaria)',
                ],
                1 => [
                    'value' => 'CLIN',
                    'label' => 'CLIN (consultorio / clínica)',
                ],
                2 => [
                    'value' => 'ESSIDT',
                    'label' => 'ESSIDT',
                ],
                3 => [
                    'value' => 'ESCIG',
                    'label' => 'ESCIG',
                ],
                4 => [
                    'value' => 'ESSID',
                    'label' => 'ESSID',
                ],
                5 => [
                    'value' => 'ESCL',
                    'label' => 'ESCL',
                ],
                6 => [
                    'value' => 'ESSIT',
                    'label' => 'ESSIT',
                ],
                7 => [
                    'value' => 'ESCIE',
                    'label' => 'ESCIE',
                ],
                8 => [
                    'value' => 'ESCIESM',
                    'label' => 'ESCIESM',
                ],
                9 => [
                    'value' => 'ESCIEM',
                    'label' => 'ESCIEM',
                ],
                10 => [
                    'value' => 'ESCIEP',
                    'label' => 'ESCIEP',
                ],
                11 => [
                    'value' => 'ENA',
                    'label' => 'ENA',
                ],
                12 => [
                    'value' => 'ESC',
                    'label' => 'ESC',
                ],
                13 => [
                    'value' => 'ESIG',
                    'label' => 'ESIG',
                ],
                14 => [
                    'value' => 'ESNOASIST',
                    'label' => 'ESNOASIST',
                ],
            ],
        ],
    ];
    }
}
