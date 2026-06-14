<?php

namespace common\components\Core\Product;

use Yii;

/**
 * Acceso unificado a {@see product-registries.php} (handlers de dominio cableados a motores genéricos).
 */
final class ProductRegistryConfig
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /** @var array<string, mixed>|null */
    private static ?array $runtimeOverrides = null;

    /**
     * @param array<string, mixed> $overrides
     */
    public static function registerOverrides(array $overrides): void
    {
        self::$runtimeOverrides = array_merge(self::$runtimeOverrides ?? [], $overrides);
        self::$cache = null;
    }

    public static function resetForTests(): void
    {
        self::$cache = null;
        self::$runtimeOverrides = null;
    }

    /**
     * @return array<string, array{class-string, string}|class-string|list<class-string>>
     */
    public static function section(string $key): array
    {
        $all = self::load();
        $section = $all[$key] ?? [];

        return is_array($section) ? $section : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $fromParams = [];
        if (class_exists(Yii::class) && Yii::$app->has('params')) {
            $raw = Yii::$app->params['productRegistries'] ?? null;
            if (is_array($raw)) {
                $fromParams = $raw;
            }
        }

        if ($fromParams === []) {
            $file = dirname(__DIR__, 3) . '/config/product-registries.php';
            if (is_file($file)) {
                $loaded = require $file;
                $fromParams = is_array($loaded) ? $loaded : [];
            }
        }

        if (self::$runtimeOverrides !== null) {
            $fromParams = array_merge($fromParams, self::$runtimeOverrides);
        }

        self::$cache = $fromParams;

        return self::$cache;
    }
}
