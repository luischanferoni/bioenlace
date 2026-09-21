<?php

namespace common\components\Domain\Clinical\Capture\Domain\RowContract;

/**
 * Helpers compartidos para contratos de fila extraída (sin Yii).
 */
final class ExtractedRowFields
{
    public static function shortModelo(string $modelo): string
    {
        $modelo = trim($modelo);
        if ($modelo === '' || !str_contains($modelo, '\\')) {
            return $modelo;
        }
        $pos = strrpos($modelo, '\\');

        return $pos === false ? $modelo : substr($modelo, $pos + 1);
    }

    public static function matchesModelo(string $modelo, string ...$names): bool
    {
        $short = self::shortModelo($modelo);
        foreach ($names as $name) {
            if ($short === $name || $modelo === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function stringRow($row): ?string
    {
        if (!is_string($row)) {
            return null;
        }
        $t = trim($row);

        return $t !== '' ? $t : null;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     */
    public static function firstNonEmpty(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            $v = trim((string) $row[$key]);
            if ($v !== '') {
                return $v;
            }
        }
        $want = [];
        foreach ($keys as $key) {
            $want[self::foldKey($key)] = true;
        }
        foreach ($row as $k => $v) {
            if (!is_string($k) || !isset($want[self::foldKey($k)])) {
                continue;
            }
            $s = trim((string) $v);
            if ($s !== '') {
                return $s;
            }
        }

        return null;
    }

    public static function foldKey(string $key): string
    {
        $folded = strtr(mb_strtolower(trim($key), 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/', '', $folded) ?? $folded;
    }
}
