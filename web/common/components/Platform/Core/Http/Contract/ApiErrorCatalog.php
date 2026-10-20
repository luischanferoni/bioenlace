<?php

namespace common\components\Platform\Core\Http\Contract;

use Symfony\Component\Yaml\Yaml;

/**
 * Loader único del catálogo `api-errors.yaml`. Es la única fuente de textos de error de la API.
 */
final class ApiErrorCatalog
{
    public const FALLBACK_CODE = 'internal_error';

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $entries = null;

    public static function has(string $code): bool
    {
        return isset(self::all()[$code]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$entries === null) {
            $parsed = Yaml::parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'api-errors.yaml');
            self::$entries = is_array($parsed['errors'] ?? null) ? $parsed['errors'] : [];
        }

        return self::$entries;
    }

    /**
     * @param array<string, scalar> $params
     * @return array{code: string, message: string, intent: string, retryable: bool, http_status: int}
     */
    public static function resolve(string $code, array $params = []): array
    {
        $entry = self::all()[$code] ?? null;
        if ($entry === null) {
            $code = self::FALLBACK_CODE;
            $entry = self::all()[$code];
        }

        // `default_detail` cubre el marcador {detail} cuando el servicio no aporta un detalle propio.
        $replace = ['{detail}' => (string) ($entry['default_detail'] ?? '')];
        foreach ($params as $key => $value) {
            if ($key === 'detail' && trim((string) $value) === '') {
                continue;
            }
            $replace['{' . $key . '}'] = (string) $value;
        }

        return [
            'code' => $code,
            'message' => strtr((string) $entry['message'], $replace),
            'intent' => (string) ($entry['intent'] ?? 'error'),
            'retryable' => (bool) ($entry['retryable'] ?? false),
            'http_status' => (int) ($entry['http_status'] ?? 400),
        ];
    }
}
