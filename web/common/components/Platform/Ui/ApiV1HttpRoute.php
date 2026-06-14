<?php

namespace common\components\Platform\Ui;

/**
 * Normaliza paths HTTP de API a `/api/v1/...` (evita mezclar permisos `/api/clinical/...` con fetch).
 */
final class ApiV1HttpRoute
{
    public static function normalize(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            $parsed = parse_url($path, PHP_URL_PATH);
            $query = parse_url($path, PHP_URL_QUERY);
            $normalized = self::normalizePath(is_string($parsed) ? $parsed : '/');

            return $query !== false && $query !== null && $query !== ''
                ? $normalized . '?' . $query
                : $normalized;
        }

        return self::normalizePath($path);
    }

    private static function normalizePath(string $path): string
    {
        $p = '/' . ltrim($path, '/');
        if (preg_match('#^/api/v\d+/#', $p) === 1) {
            return $p;
        }
        if (str_starts_with($p, '/api/')) {
            return '/api/v1/' . substr($p, 5);
        }

        return '/api/v1/' . ltrim($p, '/');
    }
}
