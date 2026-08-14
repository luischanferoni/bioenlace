<?php

namespace common\components\Platform\Core\Permission;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Permisos RBAC legacy con capability de reemplazo ({@see legacy-permission-aliases.yaml}).
 */
final class LegacyPermissionAliasIndex
{
    /** @var array<string, array<string, mixed>>|null permission_key → meta */
    private static ?array $cache = null;

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = [];
        $file = ProductMetadataPaths::legacyPermissionAliasesFile();
        if (!is_file($file)) {
            return self::$cache;
        }

        try {
            $parsed = Yaml::parseFile($file);
        } catch (\Throwable $e) {
            return self::$cache;
        }

        if (!is_array($parsed)) {
            return self::$cache;
        }

        foreach ($parsed['deprecated_permissions'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['permission_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            self::$cache[$key] = [
                'permission_key' => $key,
                'replacement_capability' => trim((string) ($row['replacement_capability'] ?? '')),
                'note' => trim((string) ($row['note'] ?? '')),
            ];
        }

        ksort(self::$cache);

        return self::$cache;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $permissionKey): ?array
    {
        $permissionKey = trim($permissionKey);
        if ($permissionKey === '') {
            return null;
        }

        return self::all()[$permissionKey] ?? null;
    }
}
