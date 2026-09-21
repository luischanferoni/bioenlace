<?php

namespace common\components\Domain\Clinical\Encounter\Application\Service;

use common\components\Domain\Clinical\Encounter\Domain\EncounterPhaseEligibilityCatalog;

/**
 * Catálogo declarativo de elegibilidad por fase ({@see EncounterPhaseEligibilityCatalog}).
 */
final class EncounterPhaseEligibilityCatalogService
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, mixed>|null
     */
    public function phase(string $phaseId): ?array
    {
        $phaseId = trim($phaseId);
        $phases = self::load()['phases'] ?? [];
        if (!is_array($phases) || !isset($phases[$phaseId]) || !is_array($phases[$phaseId])) {
            return null;
        }

        return $phases[$phaseId];
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $data = EncounterPhaseEligibilityCatalog::config();
        if (!is_array($data)) {
            throw new \RuntimeException('Catálogo encounter_phase_eligibility inválido.');
        }
        self::$cache = $data;

        return self::$cache;
    }
}
