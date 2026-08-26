<?php

namespace common\components\Platform\Assistant\Metadata;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Carga y cachea YAML de metadata del asistente (prompts, routing, copy).
 */
final class AssistantMetadataLoader
{
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    public static function resetCacheForTests(): void
    {
        self::$cache = [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function load(string $absolutePath): array
    {
        if (isset(self::$cache[$absolutePath])) {
            return self::$cache[$absolutePath];
        }

        if (!is_file($absolutePath)) {
            return self::$cache[$absolutePath] = [];
        }

        try {
            $parsed = Yaml::parseFile($absolutePath);
            self::$cache[$absolutePath] = is_array($parsed) ? $parsed : [];
        } catch (\Throwable $e) {
            Yii::warning('AssistantMetadataLoader: ' . $e->getMessage() . ' (' . $absolutePath . ')', __METHOD__);
            self::$cache[$absolutePath] = [];
        }

        return self::$cache[$absolutePath];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function dotString(array $data, string $path, string $default = ''): string
    {
        $value = self::dotGet($data, $path, $default);

        return is_string($value) ? trim($value) : $default;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>
     */
    public static function dotStringList(array $data, string $path): array
    {
        $raw = self::dotGet($data, $path, []);
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = trim($item);
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function dotGet(array $data, string $path, mixed $default = null): mixed
    {
        $node = $data;
        foreach (explode('.', $path) as $segment) {
            if ($segment === '') {
                continue;
            }
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return $default;
            }
            $node = $node[$segment];
        }

        return $node;
    }

    /**
     * @param array<string, string> $vars
     */
    public static function applyPlaceholders(string $text, array $vars): string
    {
        $out = $text;
        foreach ($vars as $key => $value) {
            $out = str_replace('{' . $key . '}', $value, $out);
        }

        return $out;
    }
}
