<?php

namespace common\components\Platform\Ui;

use common\components\Platform\Core\Product\ProductRegistryConfig;
use common\components\Platform\Core\Product\UiScreenParamsMetadata;

final class UiScreenParamsExpanderRegistry
{
    /** @var array<string, class-string<UiScreenParamsExpanderInterface>>|null */
    private static ?array $byKey = null;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function expand(string $entity, string $action, array $params): array
    {
        foreach (self::providersByKey() as $key => $class) {
            if (!UiScreenParamsMetadata::matchesProvider($key, $entity, $action)) {
                continue;
            }
            $params = $class::expand($entity, $action, $params);
        }

        return $params;
    }

    /**
     * @return array<string, class-string<UiScreenParamsExpanderInterface>>
     */
    private static function providersByKey(): array
    {
        if (self::$byKey !== null) {
            return self::$byKey;
        }

        self::$byKey = [];
        foreach (ProductRegistryConfig::section('uiScreenParamsExpanders') as $class) {
            if (!is_string($class) || $class === ''
                || !is_subclass_of($class, UiScreenParamsExpanderInterface::class)) {
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

    public static function resetForTests(): void
    {
        self::$byKey = null;
    }
}
