<?php

namespace common\components\Domain\Clinical\Encounter\Application\Service;

/**
 * Compara los modelos configurados y sus resultados de persistencia con el snapshot final.
 */
final class EncounterCapturePersistenceCheck
{
    private const MODEL_COUNTERS = [
        'EncounterReason' => 'reasons',
        'ConsultaMedicamentos' => 'medication_requests',
        'ConsultaIndicaciones' => 'service_requests',
        'ConsultaPracticas' => 'service_requests',
        'EncounterReferralRequest' => 'service_requests',
    ];

    /**
     * @param list<array<string, mixed>> $categories
     * @param array<string, array<string, mixed>> $modelStats
     * @param array<string, mixed> $persisted
     * @return list<string> Titles from the active encounter configuration.
     */
    public function incompleteCategories(
        array $categories,
        array $modelStats,
        array $persisted
    ): array {
        $incomplete = [];
        foreach ($categories as $category) {
            $model = trim((string) ($category['modelo'] ?? ''));
            $counter = self::MODEL_COUNTERS[$model] ?? null;
            $stats = $modelStats[$model] ?? null;
            if ($counter === null || !is_array($stats)) {
                continue;
            }
            if (($stats['payload'] ?? false) !== true || (int) ($stats['rows'] ?? 0) <= 0) {
                continue;
            }

            $persistedCount = $counter === 'reasons'
                ? (!empty($persisted[$counter]) ? 1 : 0)
                : (int) ($persisted[$counter] ?? 0);
            if ($persistedCount > 0) {
                continue;
            }

            $title = trim((string) ($category['titulo'] ?? ''));
            $incomplete[] = $title !== '' ? $title : $model;
        }

        return array_values(array_unique($incomplete));
    }
}
