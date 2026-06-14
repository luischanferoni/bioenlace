<?php

namespace common\components\Platform\Core\Product;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Perfiles de búsqueda SNOMED ({@see ProductMetadataPaths::snomedSearchProfilesFile()}).
 */
final class SnomedSearchProfileMetadata
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    public static function profileKeyForClientMethod(string $methodName): ?string
    {
        $methodName = trim($methodName);
        if ($methodName === '') {
            return null;
        }

        $map = self::loadConfig()['client_methods'] ?? [];
        if (!is_array($map) || !isset($map[$methodName])) {
            return null;
        }

        $key = trim((string) $map[$methodName]);

        return $key !== '' && self::eclForProfile($key) !== null ? $key : null;
    }

    public static function eclForProfile(string $profileKey): ?string
    {
        $profileKey = trim($profileKey);
        if ($profileKey === '') {
            return null;
        }

        $profiles = self::loadConfig()['profiles'] ?? [];
        if (!is_array($profiles) || !isset($profiles[$profileKey]) || !is_array($profiles[$profileKey])) {
            return null;
        }

        $ecl = trim((string) ($profiles[$profileKey]['ecl'] ?? ''));

        return $ecl !== '' ? $ecl : null;
    }

    public static function limitForProfile(string $profileKey): int
    {
        $profiles = self::loadConfig()['profiles'] ?? [];
        if (is_array($profiles) && isset($profiles[$profileKey]) && is_array($profiles[$profileKey])) {
            $limit = $profiles[$profileKey]['limit'] ?? null;
            if (is_numeric($limit)) {
                return max(1, (int) $limit);
            }
        }

        $default = self::loadConfig()['default_limit'] ?? 10;

        return is_numeric($default) ? max(1, (int) $default) : 10;
    }

    public static function returnFormatForProfile(string $profileKey): string
    {
        $profiles = self::loadConfig()['profiles'] ?? [];
        if (!is_array($profiles) || !isset($profiles[$profileKey]) || !is_array($profiles[$profileKey])) {
            return 'concepts';
        }

        $format = trim((string) ($profiles[$profileKey]['return_format'] ?? 'concepts'));

        return $format !== '' ? $format : 'concepts';
    }

    public static function resetCacheForTests(): void
    {
        self::$config = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        self::$config = [
            'default_limit' => 10,
            'profiles' => [],
            'client_methods' => [],
        ];

        $path = ProductMetadataPaths::snomedSearchProfilesFile();
        if (!is_file($path)) {
            return self::$config;
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('SnomedSearchProfileMetadata: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$config;
        }

        if (!is_array($data)) {
            return self::$config;
        }

        foreach (['default_limit', 'profiles', 'client_methods'] as $key) {
            if (array_key_exists($key, $data)) {
                self::$config[$key] = $data[$key];
            }
        }

        return self::$config;
    }
}
