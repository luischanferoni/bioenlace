<?php

namespace common\components\Domain\Clinical\Capture;

/**
 * Aplica resoluciones del profesional sobre datosExtraidos (mapa categoría → filas).
 *
 * @see ClinicalCaptureIssueFactory
 */
final class ClinicalCaptureResolutionApplier
{
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

        foreach ($resolutions as $issueId => $value) {
            if (!is_string($issueId) || $issueId === '') {
                continue;
            }
            $parsed = ClinicalCaptureIssueFactory::parseIssueId($issueId);
            if ($parsed === null) {
                continue;
            }
            $extraidos = $this->applyOne(
                $extraidos,
                $parsed['category'],
                $parsed['index'],
                $parsed['field'],
                $value,
                $categorias
            );
        }

        return $extraidos;
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

        $modelo = $this->modeloForCategory($category, $categorias);
        if ($modelo !== null && method_exists($modelo, 'applyResolutionToRow')) {
            $row = $modelo::applyResolutionToRow($row, $field, $value);
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
        $modelo = $this->modeloForCategory($category, $categorias);
        if ($modelo !== null) {
            $short = $this->shortClassName($modelo);
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
     * @param list<array<string, mixed>> $categorias
     */
    private function modeloForCategory(string $category, array $categorias): ?string
    {
        $want = $this->fold($category);
        foreach ($categorias as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $title = trim((string) ($cat['titulo'] ?? ''));
            if ($title !== '' && $this->fold($title) === $want) {
                $modelo = trim((string) ($cat['modelo'] ?? ''));
                if ($modelo === '') {
                    return null;
                }
                if (str_contains($modelo, '\\')) {
                    return class_exists($modelo) ? $modelo : null;
                }
                $class = '\\common\\models\\' . $modelo;

                return class_exists($class) ? $class : null;
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

    private function shortClassName(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }

    private function fold(string $key): string
    {
        $folded = strtr(mb_strtolower(trim($key), 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/', '', $folded) ?? $folded;
    }
}
