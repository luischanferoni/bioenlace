<?php

namespace common\components\Domain\Clinical\Specialty\Domain;

use common\components\Domain\Clinical\Capture\Domain\RowContract\OdontologiaItemRowContract;
use common\components\Domain\Clinical\Capture\Domain\RowContract\OftalmologiaEstudioRowContract;
use common\models\Clinical\EncounterDefinition;

/**
 * Maps encounter workflow model keys to FHIR specialties.
 */
final class EncounterDefinitionSpecialtyRegistry
{
    public const SPECIALTY_ODONTOLOGY = 'odontology';
    public const SPECIALTY_OPHTHALMOLOGY = 'ophthalmology';
    public const SPECIALTY_MENTAL_HEALTH = 'mental-health';

    /** @var array<string, string> workflow model key → specialty code */
    private const MODEL_TO_SPECIALTY = [
        'EncounterDentalPractice' => self::SPECIALTY_ODONTOLOGY,
        'EncounterDentalDiagnosis' => self::SPECIALTY_ODONTOLOGY,
        'EncounterDentalStatus' => self::SPECIALTY_ODONTOLOGY,
        'OphthalmologyPractice' => self::SPECIALTY_OPHTHALMOLOGY,
        'OphthalmologyStudy' => self::SPECIALTY_OPHTHALMOLOGY,
        'OphthalmologyLensPrescription' => self::SPECIALTY_OPHTHALMOLOGY,
    ];

    private const SPECIALTY_METADATA = [
        self::SPECIALTY_ODONTOLOGY => [
            'code' => self::SPECIALTY_ODONTOLOGY,
            'label' => 'Odontología',
            'sections' => [
                [
                    'key' => 'EncounterDentalPractice',
                    'label' => 'Prácticas odontológicas',
                    'order' => 1,
                    'fields' => [
                        [
                            'key' => OdontologiaItemRowContract::FIELD_TIPO,
                            'label' => 'Tipo',
                            'value' => null,
                            'unit' => null,
                            'format' => 'text',
                            'order' => 1,
                        ],
                        [
                            'key' => OdontologiaItemRowContract::FIELD_CODIGO,
                            'label' => 'Código',
                            'value' => null,
                            'unit' => null,
                            'format' => 'text',
                            'order' => 2,
                        ],
                    ],
                    'validation' => [
                        'type' => 'at_least_one',
                        'fields' => [
                            OdontologiaItemRowContract::FIELD_TIPO,
                            OdontologiaItemRowContract::FIELD_CODIGO,
                        ],
                    ],
                ],
                [
                    'key' => 'EncounterDentalDiagnosis',
                    'label' => 'Diagnósticos odontológicos',
                    'order' => 2,
                    'fields' => [
                        [
                            'key' => OdontologiaItemRowContract::FIELD_TIPO,
                            'label' => 'Tipo',
                            'value' => null,
                            'unit' => null,
                            'format' => 'text',
                            'order' => 1,
                        ],
                        [
                            'key' => OdontologiaItemRowContract::FIELD_CODIGO,
                            'label' => 'Código',
                            'value' => null,
                            'unit' => null,
                            'format' => 'text',
                            'order' => 2,
                        ],
                    ],
                    'validation' => [
                        'type' => 'at_least_one',
                        'fields' => [
                            OdontologiaItemRowContract::FIELD_TIPO,
                            OdontologiaItemRowContract::FIELD_CODIGO,
                        ],
                    ],
                ],
                [
                    'key' => 'EncounterDentalStatus',
                    'label' => 'Estado dental',
                    'order' => 3,
                    'fields' => [
                        [
                            'key' => OdontologiaItemRowContract::FIELD_TIPO,
                            'label' => 'Tipo',
                            'value' => null,
                            'unit' => null,
                            'format' => 'text',
                            'order' => 1,
                        ],
                        [
                            'key' => OdontologiaItemRowContract::FIELD_CODIGO,
                            'label' => 'Código',
                            'value' => null,
                            'unit' => null,
                            'format' => 'text',
                            'order' => 2,
                        ],
                    ],
                    'validation' => [
                        'type' => 'at_least_one',
                        'fields' => [
                            OdontologiaItemRowContract::FIELD_TIPO,
                            OdontologiaItemRowContract::FIELD_CODIGO,
                        ],
                    ],
                ],
            ],
        ],
        self::SPECIALTY_OPHTHALMOLOGY => [
            'code' => self::SPECIALTY_OPHTHALMOLOGY,
            'label' => 'Oftalmología',
            'sections' => [
                [
                    'key' => 'OphthalmologyPractice',
                    'label' => 'Prácticas oftalmológicas',
                    'order' => 1,
                    'fields' => [
                        ['key' => 'ojo', 'label' => 'Ojo', 'value' => null, 'unit' => null, 'format' => 'text', 'order' => 1],
                        ['key' => 'prueba', 'label' => 'Prueba', 'value' => null, 'unit' => null, 'format' => 'text', 'order' => 2],
                        ['key' => 'estado', 'label' => 'Estado', 'value' => null, 'unit' => null, 'format' => 'text', 'order' => 3],
                        ['key' => 'informe', 'label' => 'Informe', 'value' => null, 'unit' => null, 'format' => 'text', 'order' => 4],
                        ['key' => 'tipo', 'label' => 'Tipo', 'value' => null, 'unit' => null, 'format' => 'text', 'order' => 5],
                    ],
                ],
                [
                    'key' => 'OphthalmologyStudy',
                    'label' => 'Estudios oftalmológicos',
                    'order' => 2,
                    'fields' => [
                        [
                            'key' => OftalmologiaEstudioRowContract::FIELD_CODIGO,
                            'label' => 'Código',
                            'value' => null,
                            'unit' => null,
                            'format' => 'text',
                            'order' => 1,
                        ],
                        [
                            'key' => OftalmologiaEstudioRowContract::FIELD_OJO,
                            'label' => 'Ojo',
                            'value' => null,
                            'unit' => null,
                            'format' => 'text',
                            'order' => 2,
                        ],
                        [
                            'key' => OftalmologiaEstudioRowContract::FIELD_INFORME,
                            'label' => 'Informe',
                            'value' => null,
                            'unit' => null,
                            'format' => 'text',
                            'order' => 3,
                        ],
                    ],
                    'validation' => [
                        'type' => 'at_least_one',
                        'fields' => [
                            OftalmologiaEstudioRowContract::FIELD_CODIGO,
                            OftalmologiaEstudioRowContract::FIELD_INFORME,
                        ],
                    ],
                ],
                [
                    'key' => 'OphthalmologyLensPrescription',
                    'label' => 'Receta de lentes',
                    'order' => 3,
                    'fields' => [
                        ['key' => 'od_esfera', 'label' => 'OD esfera', 'value' => null, 'unit' => 'D', 'format' => 'decimal', 'order' => 1],
                        ['key' => 'od_cilindro', 'label' => 'OD cilindro', 'value' => null, 'unit' => 'D', 'format' => 'decimal', 'order' => 2],
                        ['key' => 'od_eje', 'label' => 'OD eje', 'value' => null, 'unit' => '°', 'format' => 'integer', 'order' => 3],
                        ['key' => 'od_add', 'label' => 'OD adición', 'value' => null, 'unit' => 'D', 'format' => 'decimal', 'order' => 4],
                        ['key' => 'oi_esfera', 'label' => 'OI esfera', 'value' => null, 'unit' => 'D', 'format' => 'decimal', 'order' => 5],
                        ['key' => 'oi_cilindro', 'label' => 'OI cilindro', 'value' => null, 'unit' => 'D', 'format' => 'decimal', 'order' => 6],
                        ['key' => 'oi_eje', 'label' => 'OI eje', 'value' => null, 'unit' => '°', 'format' => 'integer', 'order' => 7],
                        ['key' => 'oi_add', 'label' => 'OI adición', 'value' => null, 'unit' => 'D', 'format' => 'decimal', 'order' => 8],
                    ],
                ],
            ],
        ],
    ];

    /**
     * @return list<string> códigos de especialidad habilitados para la definición
     */
    public function specialtiesForDefinition(EncounterDefinition $definition): array
    {
        $found = [];
        foreach (EncounterDefinition::getCategoriasParaPrompt($definition) as $cat) {
            $model = $cat['modelo'] ?? '';
            if ($model !== '' && isset(self::MODEL_TO_SPECIALTY[$model])) {
                $found[self::MODEL_TO_SPECIALTY[$model]] = true;
            }
        }

        return array_keys($found);
    }

    /**
     * @param list<string> $specialties
     * @return list<array{
     *   code: string,
     *   label: string,
     *   sections: list<array{
     *     key: string,
     *     label: string,
     *     order: int,
     *     fields: list<array{key:string,label:string,value:mixed,unit:?string,format:string,order:int}>,
     *     validation?: array{type:string,fields:list<string>}
     *   }>
     * }>
     */
    public function metadataForSpecialties(array $specialties): array
    {
        $metadata = [];
        foreach (array_values(array_unique($specialties)) as $specialty) {
            if (isset(self::SPECIALTY_METADATA[$specialty])) {
                $metadata[] = self::SPECIALTY_METADATA[$specialty];
            }
        }

        return $metadata;
    }

    public function specialtyForModel(string $modelKey): ?string
    {
        return self::MODEL_TO_SPECIALTY[$modelKey] ?? null;
    }

    public function isModelAllowed(EncounterDefinition $definition, string $legacyModel): bool
    {
        foreach (EncounterDefinition::getCategoriasParaPrompt($definition) as $cat) {
            if (($cat['modelo'] ?? '') === $legacyModel) {
                return true;
            }
        }

        return false;
    }
}
