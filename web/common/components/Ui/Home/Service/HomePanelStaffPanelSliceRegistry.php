<?php

namespace common\components\Ui\Home\Service;

use common\components\Clinical\Home\InpatientHomePanelSliceResolver;
use common\components\Core\Product\ProductRegistryConfig;

/**
 * Resolvers de variantes staff del panel home (dominio → slice del manifiesto YAML).
 */
final class HomePanelStaffPanelSliceRegistry
{
    /** @var list<class-string<HomePanelStaffPanelSliceResolverInterface>> */
    private const RESOLVERS = [
        InpatientHomePanelSliceResolver::class,
    ];

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
        foreach (self::resolverClasses() as $class) {
            if (!is_subclass_of($class, HomePanelStaffPanelSliceResolverInterface::class)) {
                continue;
            }
            self::$instances[] = new $class();
        }

        return self::$instances;
    }

    /** @return list<class-string<HomePanelStaffPanelSliceResolverInterface>> */
    private static function resolverClasses(): array
    {
        $fromConfig = ProductRegistryConfig::section('homePanelStaffPanelSliceResolvers');
        $classes = [];
        foreach ($fromConfig as $class) {
            if (is_string($class) && $class !== '') {
                $classes[] = $class;
            }
        }

        if ($classes !== []) {
            return $classes;
        }

        return self::RESOLVERS;
    }

    public static function resetForTests(): void
    {
        self::$instances = null;
        ProductRegistryConfig::resetForTests();
    }
}
