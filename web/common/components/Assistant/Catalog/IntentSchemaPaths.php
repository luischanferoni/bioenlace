<?php

namespace common\components\Assistant\Catalog;

/**
 * Rutas y resolución de manifiestos YAML de intents (`schemas/intents/`).
 *
 * Soporta layout plano (`<intent_id>.yaml`) y subcarpetas CRUD (`create/`, `read/`, `update/`, `delete/`).
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

    public static function baseDir(): string
    {
        return dirname(__DIR__) . '/SubIntentEngine/schemas/intents';
    }

    /**
     * @return list<string> rutas absolutas a YAML de intents (excluye README u otros)
     */
    public static function discoverYamlFiles(): array
    {
        $base = realpath(self::baseDir());
        if ($base === false || !is_dir($base)) {
            return [];
        }

        $files = glob($base . DIRECTORY_SEPARATOR . '*.yaml') ?: [];
        foreach (self::CATEGORIES as $category) {
            $subdir = $base . DIRECTORY_SEPARATOR . $category;
            if (!is_dir($subdir)) {
                continue;
            }
            $nested = glob($subdir . DIRECTORY_SEPARATOR . '*.yaml') ?: [];
            $files = array_merge($files, $nested);
        }

        sort($files);

        return array_values(array_filter($files, static fn (string $p): bool => is_file($p)));
    }

    /**
     * Resuelve la ruta absoluta del YAML para un intent_id.
     */
    public static function resolveFileForIntentId(string $intentId): ?string
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return null;
        }

        $index = self::buildIndex();
        if (isset($index[$intentId])) {
            return $index[$intentId];
        }

        return null;
    }

    /**
     * Categoría CRUD inferida desde la carpeta del archivo, o null si está en la raíz.
     */
    public static function categoryForIntentId(string $intentId): ?string
    {
        $path = self::resolveFileForIntentId($intentId);
        if ($path === null) {
            return null;
        }

        return self::categoryFromPath($path);
    }

    public static function categoryFromPath(string $absolutePath): ?string
    {
        $base = realpath(self::baseDir());
        if ($base === false) {
            return null;
        }
        $dir = dirname(realpath($absolutePath) ?: $absolutePath);
        $category = basename($dir);
        if ($dir === $base || !in_array($category, self::CATEGORIES, true)) {
            return null;
        }

        return $category;
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
