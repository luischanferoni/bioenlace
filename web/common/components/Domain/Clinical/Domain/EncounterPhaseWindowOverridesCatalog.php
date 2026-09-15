<?php

namespace common\components\Domain\Clinical\Domain;

/**
 * Catálogo de dominio (ex Clinical/metadata/encounter_phase_window_overrides.yaml).
 */
final class EncounterPhaseWindowOverridesCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 1,
        'rules' => [
        ],
    ];
    }
}
