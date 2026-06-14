<?php

namespace common\components\Platform\Core\Product;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Metadata de fuentes select UI ({@see ProductMetadataPaths::uiSelectOptionSourcesFile()}).
 */
final class UiSelectOptionSourceMetadata
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /**
     * Normaliza alias legacy (p. ej. source=condiciones_laborales → catalog).
     *
     * @param array<string, mixed> $optionConfig
     * @return array{source: string, option_config: array<string, mixed>}|null
     */
    public static function normalizeSource(string $sourceKey, array $optionConfig): ?array
    {
        $sourceKey = trim($sourceKey);
        if ($sourceKey === '') {
            return null;
        }

        $aliases = self::loadConfig()['source_aliases'] ?? [];
        if (is_array($aliases) && isset($aliases[$sourceKey]) && is_array($aliases[$sourceKey])) {
            $alias = $aliases[$sourceKey];
            $target = trim((string) ($alias['source'] ?? ''));
            if ($target === '') {
                return null;
            }
            $merged = $optionConfig;
            foreach ($alias as $k => $v) {
                if ($k === 'source') {
                    continue;
                }
                $merged[$k] = $v;
            }

            return ['source' => $target, 'option_config' => $merged];
        }

        return ['source' => $sourceKey, 'option_config' => $optionConfig];
    }

    public static function providerKeyForSource(string $sourceKey): ?string
    {
        $sourceKey = trim($sourceKey);
        if ($sourceKey === '') {
            return null;
        }

        $map = self::loadConfig()['source_providers'] ?? [];
        if (!is_array($map)) {
            return null;
        }

        $key = $map[$sourceKey] ?? null;

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }

    /**
     * Permite resolver opciones aunque falte el param referenciado por depends_on del campo.
     *
     * @param array<string, mixed> $optionConfig
     */
    public static function allowsMissingDependsOn(string $sourceKey, array $optionConfig): bool
    {
        $sourceKey = trim($sourceKey);
        if ($sourceKey === '') {
            return false;
        }

        $filter = isset($optionConfig['filter']) ? trim((string) $optionConfig['filter']) : '';

        foreach (self::loadConfig()['depends_on_optional'] ?? [] as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            if (trim((string) ($rule['source'] ?? '')) !== $sourceKey) {
                continue;
            }
            $ruleFilter = trim((string) ($rule['filter'] ?? ''));
            if ($ruleFilter === '' || $ruleFilter === $filter) {
                return true;
            }
        }

        return false;
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
            'source_aliases' => [],
            'source_providers' => [],
            'depends_on_optional' => [],
        ];

        $path = ProductMetadataPaths::uiSelectOptionSourcesFile();
        if (!is_file($path)) {
            return self::$config;
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('UiSelectOptionSourceMetadata: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$config;
        }

        if (!is_array($data)) {
            return self::$config;
        }

        foreach (['source_aliases', 'source_providers'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                self::$config[$key] = $data[$key];
            }
        }

        if (isset($data['depends_on_optional']) && is_array($data['depends_on_optional'])) {
            self::$config['depends_on_optional'] = $data['depends_on_optional'];
        }

        return self::$config;
    }

    public static function resetCacheForTests(): void
    {
        self::$config = null;
    }
}
