<?php

namespace common\components\Platform\Core\Permission;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Índice de capabilities UI nativa ({@see ProductMetadataPaths::capabilitiesDir()}).
 */
final class CapabilityManifestIndex
{
    /** @var array<string, array<string, mixed>>|null capability_id → meta */
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
        $dir = ProductMetadataPaths::capabilitiesDir();
        if (!is_dir($dir)) {
            return self::$cache;
        }

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.yaml') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }
            try {
                $parsed = Yaml::parseFile($path);
            } catch (\Throwable $e) {
                continue;
            }
            if (!is_array($parsed)) {
                continue;
            }
            $rows = $parsed['capabilities'] ?? [];
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = trim((string) ($row['capability_id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                self::$cache[$id] = self::normalizeRow($row);
            }
        }

        ksort(self::$cache);

        return self::$cache;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $capabilityId): ?array
    {
        $capabilityId = trim($capabilityId);
        if ($capabilityId === '') {
            return null;
        }

        return self::all()[$capabilityId] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function routesForCapability(string $capabilityId): array
    {
        $meta = self::get($capabilityId);
        if ($meta === null) {
            return [];
        }

        $routes = [];
        foreach ($meta['routes'] ?? [] as $route) {
            if (!is_string($route)) {
                continue;
            }
            $route = '/' . ltrim(trim($route), '/');
            if ($route !== '/') {
                $routes[] = $route;
            }
        }

        return array_values(array_unique($routes));
    }

    /**
     * @return list<string>
     */
    public static function defaultRolesForCapability(string $capabilityId): array
    {
        $meta = self::get($capabilityId);
        if ($meta === null) {
            return [];
        }

        $roles = [];
        foreach ($meta['default_roles'] ?? [] as $role) {
            if (!is_string($role)) {
                continue;
            }
            $role = trim($role);
            if ($role !== '') {
                $roles[] = $role;
            }
        }

        return array_values(array_unique($roles));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normalizeRow(array $row): array
    {
        $routes = [];
        foreach ($row['routes'] ?? [] as $route) {
            if (!is_string($route)) {
                continue;
            }
            $route = '/' . ltrim(trim($route), '/');
            if ($route !== '/') {
                $routes[] = $route;
            }
        }

        $defaultRoles = [];
        foreach ($row['default_roles'] ?? [] as $role) {
            if (is_string($role) && trim($role) !== '') {
                $defaultRoles[] = trim($role);
            }
        }

        $relatedIntents = [];
        foreach ($row['related_intents'] ?? [] as $intentId) {
            if (is_string($intentId) && trim($intentId) !== '') {
                $relatedIntents[] = trim($intentId);
            }
        }

        return [
            'capability_id' => trim((string) ($row['capability_id'] ?? '')),
            'description' => trim((string) ($row['description'] ?? '')),
            'assignable' => (bool) ($row['assignable'] ?? true),
            'routes' => array_values(array_unique($routes)),
            'default_roles' => array_values(array_unique($defaultRoles)),
            'related_intents' => array_values(array_unique($relatedIntents)),
        ];
    }
}
