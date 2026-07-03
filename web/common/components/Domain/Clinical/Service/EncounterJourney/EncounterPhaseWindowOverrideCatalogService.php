<?php

namespace common\components\Domain\Clinical\Service\EncounterJourney;

use Symfony\Component\Yaml\Yaml;

/**
 * Overrides declarativos de ventanas por efector/servicio.
 */
final class EncounterPhaseWindowOverrideCatalogService
{
    private const CATALOG_FILE = 'encounter_phase_window_overrides.yaml';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null Parcial de fase (open_offset, close_offset, notifications, …)
     */
    public function phaseOverride(string $phaseId, array $context): ?array
    {
        $phaseId = trim($phaseId);
        $best = null;
        $bestScore = -1;

        foreach ($this->rules() as $rule) {
            $match = $rule['match'] ?? null;
            if (!is_array($match)) {
                continue;
            }
            $score = $this->matchScore($match, $context);
            if ($score < 0) {
                continue;
            }
            $phases = $rule['phases'] ?? null;
            if (!is_array($phases) || !isset($phases[$phaseId]) || !is_array($phases[$phaseId])) {
                continue;
            }
            if ($score >= $bestScore) {
                $bestScore = $score;
                $best = $phases[$phaseId];
            }
        }

        return $best;
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rules(): array
    {
        $raw = self::load()['rules'] ?? [];

        return is_array($raw) ? array_values(array_filter($raw, 'is_array')) : [];
    }

    /**
     * @param array<string, mixed> $match
     * @param array<string, mixed> $context
     */
    private function matchScore(array $match, array $context): int
    {
        $score = 0;
        if (array_key_exists('id_efector', $match)) {
            $expected = (int) $match['id_efector'];
            $actual = (int) ($context['id_efector'] ?? 0);
            if ($expected <= 0 || $actual !== $expected) {
                return -1;
            }
            $score++;
        }
        if (array_key_exists('id_servicio', $match)) {
            $expected = (int) $match['id_servicio'];
            $actual = (int) ($context['id_servicio_asignado'] ?? 0);
            if ($expected <= 0 || $actual !== $expected) {
                return -1;
            }
            $score++;
        }

        return $score > 0 ? $score : -1;
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
            self::$cache = ['version' => 1, 'rules' => []];

            return self::$cache;
        }
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \RuntimeException('Catálogo encounter_phase_window_overrides inválido.');
        }
        self::$cache = $data;

        return self::$cache;
    }
}
