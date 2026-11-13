<?php

namespace common\components\Domain\Clinical\Specialty\Domain;

use common\components\Domain\Clinical\Specialty\Domain\Catalog\ClinicalSpecialtyCatalog;
use common\models\Clinical\EncounterDefinition;

/**
 * Especialidades habilitadas para una definición de encounter y su metadata de UI.
 *
 * La fuente de especialidades, secciones y campos es {@see ClinicalSpecialtyCatalog};
 * esta clase solo resuelve qué especialidades aplican a una definición concreta.
 */
final class EncounterDefinitionSpecialtyRegistry
{
    public const SPECIALTY_ODONTOLOGY = ClinicalSpecialtyCatalog::ODONTOLOGY;
    public const SPECIALTY_OPHTHALMOLOGY = ClinicalSpecialtyCatalog::OPHTHALMOLOGY;
    public const SPECIALTY_MENTAL_HEALTH = ClinicalSpecialtyCatalog::MENTAL_HEALTH;

    /**
     * @return list<string> códigos de especialidad habilitados para la definición
     */
    public function specialtiesForDefinition(EncounterDefinition $definition): array
    {
        $found = [];
        foreach (EncounterDefinition::getCategoriasParaPrompt($definition) as $cat) {
            $model = $cat['modelo'] ?? '';
            if ($model === '') {
                continue;
            }
            $specialty = ClinicalSpecialtyCatalog::specialtyForCaptureModel($model);
            if ($specialty !== null) {
                $found[$specialty] = true;
            }
        }

        return array_keys($found);
    }

    /**
     * @param list<string> $specialties
     * @return list<array{code: string, label: string, sections: list<array<string, mixed>>}>
     */
    public function metadataForSpecialties(array $specialties): array
    {
        $metadata = [];
        foreach (array_values(array_unique($specialties)) as $specialty) {
            $definition = ClinicalSpecialtyCatalog::get($specialty);
            if ($definition === null) {
                continue;
            }
            $metadata[] = [
                'code' => $definition['code'],
                'label' => $definition['label'],
                'sections' => $definition['sections'],
            ];
        }

        return $metadata;
    }

    public function specialtyForModel(string $modelKey): ?string
    {
        return ClinicalSpecialtyCatalog::specialtyForCaptureModel($modelKey);
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