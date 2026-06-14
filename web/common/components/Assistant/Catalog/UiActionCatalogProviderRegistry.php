<?php

namespace common\components\Assistant\Catalog;

use common\components\Core\Product\ProductRegistryConfig;

/**
 * Registro de providers de catálogo UI. Clases en {@see common/config/product-registries.php}.
 */
final class UiActionCatalogProviderRegistry
{
    /**
     * @return list<class-string<UiActionCatalogProviderInterface>>
     */
    public static function allProviderClasses(): array
    {
        $classes = [];
        foreach (ProductRegistryConfig::section('uiActionCatalogProviders') as $class) {
            if (is_string($class) && $class !== '' && is_subclass_of($class, UiActionCatalogProviderInterface::class)) {
                $classes[] = $class;
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function discoverAllFromProviders(): array
    {
        $out = [];
        foreach (self::allProviderClasses() as $class) {
            foreach ($class::discoverAll() as $def) {
                if (is_array($def)) {
                    $out[] = $def;
                }
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forUserFromProviders(int $userId): array
    {
        $out = [];
        foreach (self::allProviderClasses() as $class) {
            foreach ($class::forUser($userId) as $def) {
                if (is_array($def)) {
                    $out[] = $def;
                }
            }
        }

        return $out;
    }

    public static function httpRouteForActionId(string $actionId): string
    {
        $actionId = trim($actionId);
        if ($actionId === '') {
            return '';
        }

        foreach (self::allProviderClasses() as $class) {
            if (!method_exists($class, 'httpRouteForActionId')) {
                continue;
            }
            $route = trim((string) $class::httpRouteForActionId($actionId));
            if ($route !== '') {
                return $route;
            }
        }

        return '';
    }

    public static function resetForTests(): void
    {
        ProductRegistryConfig::resetForTests();
    }
}
