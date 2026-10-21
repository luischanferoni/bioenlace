<?php

namespace common\components\Domain\Clinical\Specialty\Domain;

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
