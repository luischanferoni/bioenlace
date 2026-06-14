<?php

namespace common\components\Core\Permission\Domain;

use common\components\Core\Product\ProductRegistryConfig;

/**
 * Mapa estable handler_id → implementación (solo IDs, sin reglas de negocio).
 *
 * Clases en {@see common/config/product-registries.php} (`domainOperationPolicies`).
 */
final class DomainOperationPolicyRegistry
{
    /** @var array<string, DomainOperationPolicyInterface> */
    private static array $instances = [];

    public static function get(string $handlerId): DomainOperationPolicyInterface
    {
        $handlerId = trim($handlerId);
        if ($handlerId === '') {
            throw new \InvalidArgumentException('handler_id de política vacío.');
        }

        $handlers = ProductRegistryConfig::section('domainOperationPolicies');
        if (!isset($handlers[$handlerId])) {
            throw new \InvalidArgumentException('Política de dominio desconocida: ' . $handlerId);
        }

        if (!isset(self::$instances[$handlerId])) {
            $class = $handlers[$handlerId];
            if (!is_string($class) || !is_subclass_of($class, DomainOperationPolicyInterface::class)) {
                throw new \InvalidArgumentException('Implementación inválida para política: ' . $handlerId);
            }
            self::$instances[$handlerId] = new $class();
        }

        return self::$instances[$handlerId];
    }

    /** @return list<string> */
    public static function knownHandlerIds(): array
    {
        return array_keys(ProductRegistryConfig::section('domainOperationPolicies'));
    }

    public static function resetForTests(): void
    {
        self::$instances = [];
        ProductRegistryConfig::resetForTests();
    }
}
