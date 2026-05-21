<?php

namespace common\components\Clinical\Specialty;

use common\models\Clinical\EncounterDefinition;

/**
 * Mapea nombres de modelo legacy (workflow_json → relacion) a especialidad FHIR.
 */
final class EncounterDefinitionSpecialtyRegistry
{
    public const SPECIALTY_ODONTOLOGY = 'odontology';
    public const SPECIALTY_OPHTHALMOLOGY = 'ophthalmology';
    public const SPECIALTY_MENTAL_HEALTH = 'mental-health';

    /** @var array<string, string> modelo legacy → código especialidad */
    private const MODEL_TO_SPECIALTY = [
        'ConsultaOdontologiaPracticas' => self::SPECIALTY_ODONTOLOGY,
        'ConsultaOdontologiaDiagnosticos' => self::SPECIALTY_ODONTOLOGY,
        'ConsultaOdontologiaEstados' => self::SPECIALTY_ODONTOLOGY,
        'ConsultaPracticasOftalmologia' => self::SPECIALTY_OPHTHALMOLOGY,
        'ConsultaPracticasOftalmologiaEstudios' => self::SPECIALTY_OPHTHALMOLOGY,
        'ConsultasRecetaLentes' => self::SPECIALTY_OPHTHALMOLOGY,
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

    public function specialtyForModel(string $legacyModel): ?string
    {
        return self::MODEL_TO_SPECIALTY[$legacyModel] ?? null;
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
