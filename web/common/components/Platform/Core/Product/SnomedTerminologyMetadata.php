<?php

namespace common\components\Platform\Core\Product;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Metadata unificada SNOMED ({@see ProductMetadataPaths::snomedTerminologyFile()}).
 */
final class SnomedTerminologyMetadata
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return self::loadConfig();
    }

    public static function eclForRef(string $ref): ?string
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        $def = self::loadConfig()['ecl_definitions'][$ref] ?? null;
        if (!is_array($def)) {
            return null;
        }

        $ecl = trim((string) ($def['ecl'] ?? ''));

        return $ecl !== '' ? $ecl : null;
    }

    /**
     * Resuelve ECL inline o vía `ecl_ref`.
     *
     * @param array<string, mixed> $node
     */
    public static function resolveEcl(array $node): ?string
    {
        if (isset($node['ecl_ref'])) {
            return self::eclForRef((string) $node['ecl_ref']);
        }

        if (isset($node['ecl'])) {
            $ecl = trim((string) $node['ecl']);

            return $ecl !== '' ? $ecl : null;
        }

        return null;
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
            'ecl_definitions' => [],
            'semantic_matching' => [],
            'codification' => [],
            'search' => [],
        ];

        $path = ProductMetadataPaths::snomedTerminologyFile();
        if (!is_file($path)) {
            return self::$config;
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('SnomedTerminologyMetadata: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$config;
        }

        if (!is_array($data)) {
            return self::$config;
        }

        foreach (['ecl_definitions', 'semantic_matching', 'codification', 'search'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                self::$config[$key] = $data[$key];
            }
        }

        return self::$config;
    }
}
