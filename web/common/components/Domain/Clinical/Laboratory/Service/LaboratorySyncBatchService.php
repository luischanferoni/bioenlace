<?php

namespace common\components\Domain\Clinical\Laboratory\Service;

use common\models\Person\Persona;

/**
 * Sincronización pull por lotes (cron / consola).
 */
final class LaboratorySyncBatchService
{
    private LaboratoryIngestService $ingest;

    public function __construct(?LaboratoryIngestService $ingest = null)
    {
        $this->ingest = $ingest ?? new LaboratoryIngestService();
    }

    /**
     * @return array{
     *   processed: int,
     *   imported_total: int,
     *   skipped_total: int,
     *   errors: list<string>,
     *   personas: list<array{id_persona: int, imported: int, skipped: int, errors: list<string>}>
     * }
     */
    public function syncBatch(int $limit, int $offset, ?string $connectorKey = null, bool $onlyWithAppUser = true): array
    {
        $limit = max(1, min($limit, 500));
        $offset = max(0, $offset);

        $query = Persona::find()
            ->andWhere(['deleted_at' => null])
            ->andWhere(['not', ['documento' => null]])
            ->andWhere(['<>', 'documento', ''])
            ->orderBy(['id_persona' => SORT_ASC])
            ->limit($limit)
            ->offset($offset);

        if ($onlyWithAppUser) {
            $query->andWhere(['not', ['id_user' => null]])->andWhere(['>', 'id_user', 0]);
        }

        /** @var Persona[] $personas */
        $personas = $query->all();

        $summary = [
            'processed' => 0,
            'imported_total' => 0,
            'skipped_total' => 0,
            'errors' => [],
            'personas' => [],
        ];

        foreach ($personas as $persona) {
            $idPersona = (int) $persona->id_persona;
            $summary['processed']++;

            try {
                $result = $this->ingest->syncForPersona($idPersona, $connectorKey);
            } catch (\Throwable $e) {
                $summary['errors'][] = "persona {$idPersona}: " . $e->getMessage();
                $summary['personas'][] = [
                    'id_persona' => $idPersona,
                    'imported' => 0,
                    'skipped' => 0,
                    'errors' => [$e->getMessage()],
                ];
                continue;
            }

            $imported = (int) ($result['imported'] ?? 0);
            $skipped = (int) ($result['skipped'] ?? 0);
            $rowErrors = is_array($result['errors'] ?? null) ? array_map('strval', $result['errors']) : [];

            $summary['imported_total'] += $imported;
            $summary['skipped_total'] += $skipped;
            foreach ($rowErrors as $err) {
                $summary['errors'][] = "persona {$idPersona}: {$err}";
            }

            $summary['personas'][] = [
                'id_persona' => $idPersona,
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $rowErrors,
            ];
        }

        return $summary;
    }
}
