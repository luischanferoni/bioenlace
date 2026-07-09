<?php

namespace common\components\Domain\Clinical\Text;

use common\components\Domain\Terminology\Snomed\SnowstormClient;
use common\models\Terminology\Snomed\SnomedHallazgos;
use common\models\Terminology\Snomed\SnomedProblemas;
use Yii;
use yii\db\ActiveRecord;

/**
 * Resuelve si un término tiene respaldo en terminología clínica (local o Snowstorm).
 */
final class EncounterCaptureTerminologyLookup
{
    private bool $terminologyServiceUnavailable = false;

    public function wasTerminologyServiceUnavailable(): bool
    {
        return $this->terminologyServiceUnavailable;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function matchesClinicalTerm(string $term, array $config = []): bool
    {
        $this->terminologyServiceUnavailable = false;

        $normalized = mb_strtolower(trim($term));
        if (mb_strlen($normalized) < 3) {
            return false;
        }

        if ($this->matchesLocal($normalized)) {
            return true;
        }

        if (($config['validate_terminology'] ?? true) === false) {
            return false;
        }

        if (($config['snowstorm_fallback'] ?? true) !== true) {
            return false;
        }

        $profiles = $config['snowstorm_profiles'] ?? ['problemas', 'motivos_consulta', 'sintomas'];
        if (!is_array($profiles)) {
            $profiles = ['problemas', 'motivos_consulta', 'sintomas'];
        }

        return $this->matchesSnowstorm($normalized, $profiles);
    }

    private function matchesLocal(string $normalizedTerm): bool
    {
        foreach ([SnomedProblemas::class, SnomedHallazgos::class] as $modelClass) {
            if ($this->termExistsInSnomedTable($modelClass, $normalizedTerm)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $profiles
     */
    private function matchesSnowstorm(string $term, array $profiles): bool
    {
        try {
            $client = new SnowstormClient();
            foreach ($profiles as $profile) {
                if (!is_string($profile) || trim($profile) === '') {
                    continue;
                }
                $results = $client->searchByProfile(trim($profile), $term, 1);
                if ($this->snowstormResponseIndicatesServiceFailure($results)) {
                    $this->terminologyServiceUnavailable = true;

                    return false;
                }
                if ($this->snowstormHasHits($results)) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            $this->terminologyServiceUnavailable = true;
            Yii::warning(
                'EncounterCaptureTerminologyLookup: Snowstorm no disponible: ' . $e->getMessage(),
                __METHOD__
            );
        }

        return false;
    }

    private function snowstormResponseIndicatesServiceFailure(mixed $results): bool
    {
        if (!is_array($results)) {
            return true;
        }

        foreach (['error', 'message', 'detail'] as $key) {
            $value = trim((string) ($results[$key] ?? ''));
            if ($value !== '' && preg_match('/auth|token|unauthorized|forbidden|missing/i', $value)) {
                return true;
            }
        }

        return false;
    }

    private function snowstormHasHits(mixed $results): bool
    {
        if (!is_array($results) || $results === []) {
            return false;
        }

        if (isset($results['items']) && is_array($results['items']) && $results['items'] !== []) {
            return true;
        }

        foreach ($results as $row) {
            if (is_array($row) && (($row['id'] ?? null) !== null || trim((string) ($row['text'] ?? '')) !== '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param class-string<ActiveRecord> $modelClass
     */
    private function termExistsInSnomedTable(string $modelClass, string $normalizedTerm): bool
    {
        return $modelClass::find()
            ->where([
                'or',
                ['=', 'term', $normalizedTerm],
                ['like', 'term', $normalizedTerm . '%', false],
                ['like', 'term', '% ' . $normalizedTerm . '%', false],
            ])
            ->limit(1)
            ->exists();
    }
}
