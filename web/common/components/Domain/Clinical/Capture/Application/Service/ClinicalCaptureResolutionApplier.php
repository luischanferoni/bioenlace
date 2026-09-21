<?php

namespace common\components\Domain\Clinical\Capture\Application\Service;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureIssueFactory;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureResolution;
use common\components\Domain\Clinical\Capture\Domain\Port\ClinicalCaptureRowContractRegistry;
use common\components\Domain\Clinical\Capture\Infrastructure\Persistence\YiiModelClinicalCaptureRowContractRegistry;

/**
 * Aplica resoluciones del profesional sobre datosExtraidos (mapa categoría → filas).
 *
 * @see ClinicalCaptureIssueFactory
 * @see ClinicalCaptureResolution
 */
final class ClinicalCaptureResolutionApplier
{
    private ClinicalCaptureRowContractRegistry $rowContracts;

    public function __construct(?ClinicalCaptureRowContractRegistry $rowContracts = null)
    {
        $this->rowContracts = $rowContracts ?? new YiiModelClinicalCaptureRowContractRegistry();
    }

    /**
     * @param array<string, mixed> $extraidos
     * @param array<string, mixed> $resolutions mapa issue_id → value
     * @param list<array<string, mixed>> $categorias
     * @return array<string, mixed> datosExtraidos actualizados
     */
    public function apply(array $extraidos, array $resolutions, array $categorias = []): array
    {
        if ($resolutions === []) {
            return $extraidos;
        }

        foreach (ClinicalCaptureResolution::listFromMap($resolutions) as $resolution) {
            $extraidos = $this->applyOne(
                $extraidos,
                $resolution->category(),
                $resolution->index(),
                $resolution->field(),
                $resolution->value(),
                $categorias
            );
        }

        return $extraidos;
    }

    /**
     * Mapa newIndex → originalIndex por categoría, según orden de `Categoría::n` en staged.
     * Tras filtrar, la fila 0 de Medicación puede ser la original `Medicación::1`.
     *
     * @param list<string> $stagedItemIds
     * @return array<string, array<int, int>>
     */
    public function stagedIndexMap(array $stagedItemIds): array
    {
        $byCat = [];
        foreach ($stagedItemIds as $id) {
            if (!is_string($id) && !is_int($id)) {
                continue;
            }
            $id = trim((string) $id);
            if ($id === '' || preg_match('/^(.*)::(\d+)$/u', $id, $m) !== 1) {
                continue;
            }
            $cat = (string) $m[1];
            $byCat[$cat][] = (int) $m[2];
        }
        $map = [];
        foreach ($byCat as $cat => $origIndices) {
            // El filtro itera índices ascendentes; alinear el mapa a ese orden.
            $uniq = array_values(array_unique($origIndices));
            sort($uniq, SORT_NUMERIC);
            foreach ($uniq as $newIdx => $origIdx) {
                $map[$cat][$newIdx] = $origIdx;
            }
        }

        return $map;
    }

    /**
     * Extraídos a persistir / validar: staged vacío explícito = solo nota (sin filas).
     * Sin clave staged (legacy) = se usa el working completo.
     *
     * @param array<string, mixed> $working
     * @param list<string>|null $stagedItemIds null = clave ausente
     * @param list<array<string, mixed>> $categorias
     * @return array<string, mixed>
     */
    public function extraidosForSave(array $working, ?array $stagedItemIds, array $categorias = []): array
    {
        if ($stagedItemIds === null) {
            return $working;
        }
        if ($stagedItemIds === []) {
            return [];
        }

        return $this->filterByStagedItemIds($working, $stagedItemIds, $categorias);
    }

    /**
     * Reescribe incomplete_items / issues a índices originales del capture_review.
     *
     * @param array<string, mixed> $completeness
     * @param array<string, array<int, int>> $indexMap
     * @return array<string, mixed>
     */
    public function remapCompletenessToOriginalIndices(array $completeness, array $indexMap): array
    {
        if ($indexMap === []) {
            return $completeness;
        }

        $incomplete = [];
        foreach ($completeness['incomplete_items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $cat = (string) ($item['category'] ?? '');
            $newIdx = (int) ($item['index'] ?? 0);
            if ($cat !== '' && isset($indexMap[$cat][$newIdx])) {
                $item['index'] = $indexMap[$cat][$newIdx];
            }
            $incomplete[] = $item;
        }
        $completeness['incomplete_items'] = $incomplete;

        $issues = [];
        foreach ($completeness['issues'] ?? [] as $issue) {
            if (!is_array($issue)) {
                continue;
            }
            $id = (string) ($issue['id'] ?? '');
            $parsed = ClinicalCaptureIssueFactory::parseIssueId($id);
            if ($parsed !== null) {
                $cat = $parsed['category'];
                $newIdx = $parsed['index'];
                if (isset($indexMap[$cat][$newIdx])) {
                    $orig = $indexMap[$cat][$newIdx];
                    $issue['id'] = ClinicalCaptureIssueFactory::issueId(
                        $cat,
                        $orig,
                        $parsed['field']
                    );
                }
            }
            $issues[] = $issue;
        }
        $completeness['issues'] = $issues;

        if ($incomplete !== [] || ($completeness['missing_categories'] ?? []) !== []) {
            // Mensaje con índices ya remapeados en labels (el label no usa índice).
            $completeness['message'] = $completeness['message'] ?? '';
        }

        return $completeness;
    }

    /**
     * Conserva solo filas referenciadas por staged_item_ids (`Categoría::índice`).
     * Los índices son los del análisis completo (antes de reindexar).
     *
     * @param array<string, mixed> $extraidos
     * @param list<string> $stagedItemIds
     * @param list<array<string, mixed>> $categorias
     * @return array<string, mixed>
     */
    public function filterByStagedItemIds(
        array $extraidos,
        array $stagedItemIds,
        array $categorias = []
    ): array {
        $keep = [];
        foreach ($stagedItemIds as $id) {
            if (!is_string($id) && !is_int($id)) {
                continue;
            }
            $id = trim((string) $id);
            if ($id === '' || preg_match('/^(.*)::(\d+)$/u', $id, $m) !== 1) {
                continue;
            }
            $cat = (string) $m[1];
            $idx = (int) $m[2];
            if (!isset($keep[$cat])) {
                $keep[$cat] = [];
            }
            $keep[$cat][$idx] = true;
        }
        if ($keep === []) {
            return [];
        }

        $out = [];
        if (isset($extraidos['Error'])) {
            $out['Error'] = $extraidos['Error'];
        }

        foreach ($keep as $category => $indices) {
            $key = $this->resolveExtraidosKey($extraidos, $category, $categorias);
            if ($key === null) {
                continue;
            }
            $rows = $this->normalizeRows($extraidos[$key] ?? null);
            $filtered = [];
            foreach ($rows as $i => $row) {
                if (isset($indices[(int) $i])) {
                    $filtered[] = $row;
                }
            }
            if ($filtered !== []) {
                $out[$key] = $filtered;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $extraidos
     * @param list<array<string, mixed>> $categorias
     * @return array<string, mixed>
     */
    private function applyOne(
        array $extraidos,
        string $category,
        int $index,
        string $field,
        mixed $value,
        array $categorias
    ): array {
        $key = $this->resolveExtraidosKey($extraidos, $category, $categorias);
        if ($key === null) {
            return $extraidos;
        }

        $raw = $extraidos[$key] ?? null;
        $rows = $this->normalizeRows($raw);
        if (!isset($rows[$index])) {
            return $extraidos;
        }

        $row = $rows[$index];
        if (is_string($row)) {
            $row = [$field => $value, 'texto' => $row];
        } elseif (!is_array($row)) {
            $row = [$field => $value];
        } else {
            $row[$field] = $value;
        }

        $modelo = $this->modeloKeyForCategory($category, $categorias);
        if ($modelo !== null) {
            $applied = $this->rowContracts->applyResolution($modelo, $row, $field, $value);
            if ($applied !== null) {
                $row = $applied;
            }
        }

        $rows[$index] = $row;
        $extraidos[$key] = array_values($rows);

        return $extraidos;
    }

    /**
     * @param array<string, mixed> $extraidos
     * @param list<array<string, mixed>> $categorias
     */
    private function resolveExtraidosKey(array $extraidos, string $category, array $categorias): ?string
    {
        if (array_key_exists($category, $extraidos)) {
            return $category;
        }
        $modelo = $this->modeloKeyForCategory($category, $categorias);
        if ($modelo !== null) {
            $short = $this->shortModeloName($modelo);
            if ($short !== '' && array_key_exists($short, $extraidos)) {
                return $short;
            }
        }
        $want = $this->fold($category);
        foreach ($extraidos as $k => $_) {
            if (is_string($k) && $this->fold($k) === $want) {
                return $k;
            }
        }

        return null;
    }

    /**
     * Clave `modelo` de la categoría (short name o FQCN), sin resolver clase.
     *
     * @param list<array<string, mixed>> $categorias
     */
    private function modeloKeyForCategory(string $category, array $categorias): ?string
    {
        $want = $this->fold($category);
        foreach ($categorias as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $title = trim((string) ($cat['titulo'] ?? ''));
            if ($title !== '' && $this->fold($title) === $want) {
                $modelo = trim((string) ($cat['modelo'] ?? ''));

                return $modelo !== '' ? $modelo : null;
            }
        }

        return null;
    }

    /**
     * @param mixed $raw
     * @return list<mixed>
     */
    private function normalizeRows($raw): array
    {
        if ($raw === null) {
            return [];
        }
        if (is_string($raw)) {
            return trim($raw) !== '' ? [trim($raw)] : [];
        }
        if (!is_array($raw)) {
            return [];
        }
        if ($raw === []) {
            return [];
        }
        if ($this->isAssocMap($raw)) {
            return [$raw];
        }

        return array_values($raw);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isAssocMap(array $row): bool
    {
        $i = 0;
        foreach (array_keys($row) as $k) {
            if ($k !== $i) {
                return true;
            }
            $i++;
        }

        return false;
    }

    private function shortModeloName(string $modelo): string
    {
        $pos = strrpos($modelo, '\\');

        return $pos === false ? $modelo : substr($modelo, $pos + 1);
    }

    private function fold(string $key): string
    {
        $folded = strtr(mb_strtolower(trim($key), 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/', '', $folded) ?? $folded;
    }
}
