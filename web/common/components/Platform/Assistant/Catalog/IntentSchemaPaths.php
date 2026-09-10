<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Rutas y resolución de manifiestos YAML de intents.
 *
 * Layout: `metadata/bioenlace/<dominio|platform>/intents/{create|read|update|delete}/…`.
 * El dominio es la carpeta de primer nivel; la categoría CRUD es el primer segmento bajo `intents/`.
 * No hay mapa intent→dominio a mano: mover el YAML cambia el dominio.
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
     * Raíz de metadata del producto (antes era `assistant/intents`).
     * Preferir {@see discoverYamlFiles()} / {@see intentRoots()}.
     */
    public static function baseDir(): string
    {
        return ProductMetadataPaths::baseDir();
    }

    /**
     * Carpetas `…/<dominio>/intents` presentes bajo la metadata del producto.
     *
     * @return list<string> rutas absolutas
     */
    public static function intentRoots(): array
    {
        $base = realpath(ProductMetadataPaths::baseDir());
        if ($base === false || !is_dir($base)) {
            return [];
        }

        $roots = [];
        foreach (glob($base . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $domainDir) {
            $intents = $domainDir . DIRECTORY_SEPARATOR . 'intents';
            if (is_dir($intents)) {
                $roots[] = $intents;
            }
        }
        sort($roots);

        return $roots;
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
     * Dominio (o `platform`) del intent, leído de la carpeta bajo metadata.
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
        $base = realpath(ProductMetadataPaths::baseDir());
        if ($base === false) {
            return null;
        }
        $resolved = realpath($absolutePath);
        if ($resolved === false) {
            $resolved = $absolutePath;
        }
        $baseNorm = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $base), DIRECTORY_SEPARATOR);
        $pathNorm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolved);
        $prefix = $baseNorm . DIRECTORY_SEPARATOR;
        if (stripos($pathNorm, $prefix) !== 0) {
            return null;
        }
        $relative = substr($pathNorm, strlen($prefix));
        $first = explode(DIRECTORY_SEPARATOR, $relative)[0] ?? '';

        return $first !== '' ? strtolower($first) : null;
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
