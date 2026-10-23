<?php

namespace common\components\Platform\Ai\Prompt;

/**
 * Resuelve claves de prompt (`clinical.word_correction`) a archivos YAML.
 *
 * Platform no conoce rutas de Domain: el mapa clave → archivo vive en `common/config/prompts.php`
 * (raíz de composición) y puede ampliarse con {@see register()}.
 */
final class PromptRegistry
{
    /** @var array<string, string>|null */
    private static ?array $map = null;

    public static function path(string $key): string
    {
        $map = self::map();
        if (!isset($map[$key])) {
            throw new \LogicException("Prompt no registrado: {$key}");
        }

        return $map[$key];
    }

    public static function register(string $key, string $file): void
    {
        $map = self::map();
        $map[$key] = $file;
        self::$map = $map;
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        return self::map();
    }

    public static function render(string $key, array $vars = []): string
    {
        return YamlPrompt::render(self::path($key), $vars);
    }

    public static function text(string $key, string $textKey): string
    {
        return YamlPrompt::text(self::path($key), $textKey);
    }

    /** Solo para tests. */
    public static function reset(): void
    {
        self::$map = null;
    }

    /** @return array<string, string> */
    private static function map(): array
    {
        if (self::$map === null) {
            /** @var array<string, string> $loaded */
            $loaded = require dirname(__DIR__, 4) . '/config/prompts.php';
            self::$map = $loaded;
        }

        return self::$map;
    }
}
