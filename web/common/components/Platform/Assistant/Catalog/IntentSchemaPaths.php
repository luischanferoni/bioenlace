<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Rutas y resolución de manifiestos YAML de intents.
 *
 * Layout canónico: `components/Domain/<BC>/Application/Flows/intents/…`
 *                 y `components/Platform/Assistant/Application/Flows/intents/…`.
 *
 * @see web/docs/decisions/ddd-bounded-contexts-capas-y-metadata.md
 */
final class IntentSchemaPaths
{
    public const CATEGORY_CREATE = 'create';
    public const CATEGORY_READ = 'read';
    public const CATEGORY_UPDATE = 'update';
    public const CATEGORY_DELETE = 'delete';

    /** @var list<string> */
    public const CATEGORIES = [
        self::CATEGORY_CREATE,
        self::CATEGORY_READ,
        self::CATEGORY_UPDATE,
        self::CATEGORY_DELETE,
    ];

    /** @var array<string, string>|null intent_id => absolute path */
    private static ?array $index = null;

    /**
     * Carpetas `…/intents` colocalizadas (DDD).
     *
     * @return list<string> rutas absolutas
     */
    public static function intentRoots(): array
    {
        return ProductMetadataPaths::colocatedIntentRoots();
    }

    /**
     * @return list<string> rutas absolutas a YAML de intents
     */
    public static function discoverYamlFiles(): array
    {
        $files = [];
        foreach (self::intentRoots() as $root) {
            $files = array_merge($files, glob($root . DIRECTORY_SEPARATOR . '*.yaml') ?: []);
            foreach (self::CATEGORIES as $category) {
                $subdir = $root . DIRECTORY_SEPARATOR . $category;
                if (!is_dir($subdir)) {
                    continue;
                }
                $files = array_merge($files, self::collectYamlRecursive($subdir));
            }
        }

        sort($files);

        return array_values(array_filter($files, static fn (string $p): bool => is_file($p)));
    }

    /**
     * @return list<string>
     */
    private static function collectYamlRecursive(string $dir): array
    {
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.yaml') ?: [];
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $child) {
            $files = array_merge($files, self::collectYamlRecursive($child));
        }

        return $files;
    }

    public static function resolveFileForIntentId(string $intentId): ?string
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return null;
        }

        return self::buildIndex()[$intentId] ?? null;
    }

    public static function categoryForIntentId(string $intentId): ?string
    {
        $path = self::resolveFileForIntentId($intentId);
        if ($path === null) {
            return null;
        }

        return self::categoryFromPath($path);
    }

    /**
     * Dominio (o `platform`) del intent.
     */
    public static function domainForIntentId(string $intentId): ?string
    {
        $path = self::resolveFileForIntentId($intentId);
        if ($path === null) {
            return null;
        }

        return self::domainFromPath($path);
    }

    public static function domainFromPath(string $absolutePath): ?string
    {
        $resolved = realpath($absolutePath);
        if ($resolved === false) {
            $resolved = $absolutePath;
        }
        $pathNorm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolved);

        return self::domainFromColocatedPath($pathNorm);
    }

    private static function domainFromColocatedPath(string $pathNorm): ?string
    {
        $platformMarker = DIRECTORY_SEPARATOR . 'Platform' . DIRECTORY_SEPARATOR . 'Assistant'
            . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR . 'Flows'
            . DIRECTORY_SEPARATOR . 'intents';
        if (stripos($pathNorm, $platformMarker) !== false) {
            return 'platform';
        }

        $domainRoot = realpath(ProductMetadataPaths::componentsDomainRoot());
        if ($domainRoot === false) {
            return null;
        }
        $domainRootNorm = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $domainRoot), DIRECTORY_SEPARATOR);
        $prefix = $domainRootNorm . DIRECTORY_SEPARATOR;
        if (stripos($pathNorm, $prefix) !== 0) {
            return null;
        }
        $relative = substr($pathNorm, strlen($prefix));
        $parts = explode(DIRECTORY_SEPARATOR, $relative);
        $bc = $parts[0] ?? '';
        if ($bc === '') {
            return null;
        }
        // Esperado: <BC>/Application/Flows/intents/…
        if (count($parts) < 4
            || strcasecmp($parts[1] ?? '', 'Application') !== 0
            || strcasecmp($parts[2] ?? '', 'Flows') !== 0
            || strcasecmp($parts[3] ?? '', 'intents') !== 0
        ) {
            return null;
        }

        return strtolower($bc);
    }

    public static function categoryFromPath(string $absolutePath): ?string
    {
        $resolved = realpath($absolutePath);
        if ($resolved === false) {
            $resolved = $absolutePath;
        }
        $pathNorm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolved);
        $marker = DIRECTORY_SEPARATOR . 'intents' . DIRECTORY_SEPARATOR;
        $pos = stripos($pathNorm, $marker);
        if ($pos === false) {
            return null;
        }
        $after = substr($pathNorm, $pos + strlen($marker));
        $first = explode(DIRECTORY_SEPARATOR, $after)[0] ?? '';
        if ($first === '' || !in_array($first, self::CATEGORIES, true)) {
            return null;
        }

        return $first;
    }

    /**
     * @return array<string, string> intent_id => absolute path
     */
    public static function buildIndex(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }

        self::$index = [];
        foreach (self::discoverYamlFiles() as $path) {
            $intentId = self::intentIdFromPath($path);
            if ($intentId === '') {
                continue;
            }
            if (!isset(self::$index[$intentId])) {
                self::$index[$intentId] = $path;
                continue;
            }
            $existingCategory = self::categoryFromPath(self::$index[$intentId]);
            $newCategory = self::categoryFromPath($path);
            if ($existingCategory === null && $newCategory !== null) {
                self::$index[$intentId] = $path;
            }
        }

        return self::$index;
    }

    public static function resetIndexCache(): void
    {
        self::$index = null;
    }

    public static function intentIdFromPath(string $absolutePath): string
    {
        return basename($absolutePath, '.yaml');
    }
}
