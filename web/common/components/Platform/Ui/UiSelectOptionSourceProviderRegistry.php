<?php

namespace common\components\Platform\Ui;

use common\components\Platform\Core\Product\ProductRegistryConfig;
use common\components\Platform\Core\Product\UiSelectOptionSourceMetadata;

final class UiSelectOptionSourceProviderRegistry
{
    /** @var array<string, class-string<UiSelectOptionSourceProviderInterface>>|null */
    private static ?array $byKey = null;

    /** @var array<string, callable>|null */
    private static ?array $runtime = null;

    /**
     * @param array<string, mixed> $optionConfig
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>|null
     */
    public static function resolveNormalized(string $sourceKey, array $optionConfig, array $params): ?array
    {
        $sourceKey = trim($sourceKey);
        if ($sourceKey === '') {
            return null;
        }

        if (isset(self::$runtime[$sourceKey])) {
            $filter = $optionConfig['filter'] ?? null;

            return call_user_func(self::$runtime[$sourceKey], $filter, $params, $optionConfig);
        }

        $providerKey = UiSelectOptionSourceMetadata::providerKeyForSource($sourceKey);
        if ($providerKey === null || !isset(self::providersByKey()[$providerKey])) {
            return null;
        }

        $class = self::providersByKey()[$providerKey];
        $filter = $optionConfig['filter'] ?? null;

        return $class::resolve($sourceKey, $filter, $params, $optionConfig);
    }

    /**
     * @param array<string, mixed> $optionConfig
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>|null
     */
    public static function resolve(string $sourceKey, array $optionConfig, array $params): ?array
    {
        $normalized = UiSelectOptionSourceMetadata::normalizeSource($sourceKey, $optionConfig);
        if ($normalized === null) {
            return null;
        }

        return self::resolveNormalized($normalized['source'], $normalized['option_config'], $params);
    }

    /**
     * @param callable(mixed, array<string, mixed>, array<string, mixed>): array<int, array<string, mixed>> $resolver
     */
    public static function registerRuntime(string $sourceKey, callable $resolver): void
    {
        if (self::$runtime === null) {
            self::$runtime = [];
        }
        self::$runtime[trim($sourceKey)] = $resolver;
    }

    public static function resetForTests(): void
    {
        self::$byKey = null;
        self::$runtime = null;
    }

    /**
     * @return array<string, class-string<UiSelectOptionSourceProviderInterface>>
     */
    private static function providersByKey(): array
    {
        if (self::$byKey !== null) {
            return self::$byKey;
        }

        self::$byKey = [];
        foreach (ProductRegistryConfig::section('uiSelectOptionSourceProviders') as $class) {
            if (!is_string($class) || $class === ''
                || !is_subclass_of($class, UiSelectOptionSourceProviderInterface::class)) {
                continue;
            }
            $key = trim($class::providerKey());
            if ($key === '') {
                continue;
            }
            self::$byKey[$key] = $class;
        }

        return self::$byKey;
    }
}
