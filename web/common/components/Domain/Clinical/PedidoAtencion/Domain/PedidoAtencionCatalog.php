<?php

namespace common\components\Domain\Clinical\PedidoAtencion\Domain;

/**
 * Catálogo de dominio (ex metadata/bioenlace/clinical/pedido-atencion.yaml).
 */
final class PedidoAtencionCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'capacity_rules' => [
            0 => [
                'specialty_system' => 'http://snomed.info/sct',
                'specialty_code' => '394914008',
                'act_ecl' => '<< 363679005 |Imaging (procedure)|',
            ],
            1 => [
                'match_tipo' => 'laboratorio',
                'act_ecl' => '<< 15220000 |Laboratory test|',
            ],
            2 => [
                'match_tipo' => 'consulta',
                'act_ecl' => '<< 11429006 |Consultation|',
            ],
            3 => [
                'match_tipo' => 'procedimiento',
                'act_ecl' => '<< 91251008 |Physical therapy procedure|',
            ],
        ],
        'acto_nl_aliases' => [
            0 => [
                'code' => '16310003',
                'code_system' => 'http://snomed.info/sct',
                'label' => 'Ecografía',
                'aliases' => [
                    0 => 'ecografia',
                    1 => 'eco',
                    2 => 'ultrasonido',
                    3 => 'ultrasonografia',
                    4 => 'ultrasonography',
                    5 => 'ultrasound',
                ],
            ],
            1 => [
                'code' => '363680008',
                'code_system' => 'http://snomed.info/sct',
                'label' => 'Radiografía',
                'aliases' => [
                    0 => 'radiografia',
                    1 => 'rayos x',
                    2 => 'rayos',
                    3 => 'radiographic',
                ],
            ],
            2 => [
                'code' => '71651007',
                'code_system' => 'http://snomed.info/sct',
                'label' => 'Mamografía',
                'aliases' => [
                    0 => 'mamografia',
                    1 => 'mammography',
                ],
            ],
            3 => [
                'code' => '15220000',
                'code_system' => 'http://snomed.info/sct',
                'label' => 'Laboratorio',
                'aliases' => [
                    0 => 'laboratorio',
                    1 => 'analisis',
                    2 => 'analisis de sangre',
                    3 => 'analisis de orina',
                    4 => 'laboratory',
                ],
            ],
            4 => [
                'code' => '91251008',
                'code_system' => 'http://snomed.info/sct',
                'label' => 'Kinesiología',
                'aliases' => [
                    0 => 'kinesio',
                    1 => 'kinesiologia',
                    2 => 'fisioterapia',
                    3 => 'rehabilitacion',
                    4 => 'physical therapy',
                ],
            ],
        ],
        'linea_nl_aliases' => [
            0 => [
                'specialty_code' => '394807007',
                'specialty_system' => 'http://snomed.info/sct',
                'aliases' => [
                    0 => 'clinico',
                    1 => 'clínico',
                    2 => 'medico clinico',
                    3 => 'médico clínico',
                    4 => 'medicina clinica',
                    5 => 'medicina clínica',
                    6 => 'med clinica',
                    7 => 'med clínica',
                ],
            ],
            1 => [
                'specialty_code' => '394814009',
                'specialty_system' => 'http://snomed.info/sct',
                'aliases' => [
                    0 => 'medico general',
                    1 => 'médico general',
                    2 => 'medicina general',
                    3 => 'med general',
                    4 => 'generalista',
                ],
            ],
        ],
        'acto_coding' => [
            'snomed_category' => 'procedimientos',
            'snowstorm_profile' => 'procedimientos',
            'candidate_limit' => 8,
        ],
    ];
    }
}
