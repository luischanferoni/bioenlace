<?php

namespace common\components\Platform\Core\DataAccess\Filter;

use common\components\Platform\Core\Product\ProductRegistryConfig;

/**
 * Registro de resolvers de filtros declarados en metadata (`resolver:` en YAML).
 *
 * Clases en {@see common/config/product-registries.php} (`dataAccessFilterResolvers`).
 */
final class FilterValueResolverRegistry
{
    /** @var array<string, FilterValueResolverInterface> */
    private static $instances = [];

    public static function get(string $resolverId): FilterValueResolverInterface
    {
        $resolverId = trim($resolverId);
        if ($resolverId === '') {
            throw new \InvalidArgumentException('resolver de filtro vacío.');
        }

        if (!isset(self::$instances[$resolverId])) {
            self::$instances[$resolverId] = self::build($resolverId);
        }

        return self::$instances[$resolverId];
    }

    private static function build(string $resolverId): FilterValueResolverInterface
    {
        $handlers = ProductRegistryConfig::section('dataAccessFilterResolvers');
        if (!isset($handlers[$resolverId])) {
            throw new \InvalidArgumentException('resolver de filtro desconocido: ' . $resolverId);
        }

        $class = $handlers[$resolverId];
        if (!is_string($class) || !is_subclass_of($class, FilterValueResolverInterface::class)) {
            throw new \InvalidArgumentException('resolver de filtro inválido: ' . $resolverId);
        }

        return new $class();
    }

    public static function resetForTests(): void
    {
        self::$instances = [];
        ProductRegistryConfig::resetForTests();
    }
}
