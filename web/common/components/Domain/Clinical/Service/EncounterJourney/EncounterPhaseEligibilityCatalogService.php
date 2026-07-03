<?php

namespace common\components\Domain\Clinical\Service\EncounterJourney;

use Symfony\Component\Yaml\Yaml;

/**
 * Catálogo declarativo de elegibilidad por fase ({@see metadata/encounter_phase_eligibility.yaml}).
 */
final class EncounterPhaseEligibilityCatalogService
{
    private const CATALOG_FILE = 'encounter_phase_eligibility.yaml';

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
        $path = dirname(__DIR__, 2) . '/metadata/' . self::CATALOG_FILE;
        if (!is_file($path)) {
            throw new \RuntimeException('Catálogo encounter_phase_eligibility no encontrado: ' . $path);
        }
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \RuntimeException('Catálogo encounter_phase_eligibility inválido.');
        }
        self::$cache = $data;

        return self::$cache;
    }
}
