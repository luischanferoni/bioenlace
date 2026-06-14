<?php

namespace common\components\Platform\Ui\Home\Service;

use common\components\Platform\Core\Product\ProductRegistryConfig;

/**
 * Resolvers de variantes staff del panel home. Clases en {@see common/config/product-registries.php}.
 */
final class HomePanelStaffPanelSliceRegistry
{
    /** @var list<HomePanelStaffPanelSliceResolverInterface>|null */
    private static ?array $instances = null;

    /**
     * @param array<string, mixed> $staffPanelDef
     * @return array<string, mixed>|null null si ningún resolver aplica
     */
    public static function resolve(string $encounterClass, array $staffPanelDef): ?array
    {
        foreach (self::instances() as $resolver) {
            if ($resolver->applies($encounterClass)) {
                return $resolver->resolve($staffPanelDef);
            }
        }

        return null;
    }

    /** @return list<HomePanelStaffPanelSliceResolverInterface> */
    private static function instances(): array
    {
        if (self::$instances !== null) {
            return self::$instances;
        }

        self::$instances = [];
        foreach (ProductRegistryConfig::section('homePanelStaffPanelSliceResolvers') as $class) {
            if (!is_string($class) || $class === '' || !is_subclass_of($class, HomePanelStaffPanelSliceResolverInterface::class)) {
                continue;
            }
            self::$instances[] = new $class();
        }

        return self::$instances;
    }

    public static function resetForTests(): void
    {
        self::$instances = null;
        ProductRegistryConfig::resetForTests();
    }
}
