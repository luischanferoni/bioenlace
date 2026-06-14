<?php

namespace common\components\Core\DataAccess\Edit;

use common\components\Core\Product\ProductRegistryConfig;

/**
 * Handlers de mutación por grupo de atributos.
 *
 * Clases en {@see common/config/product-registries.php} (`dataAccessEditMutationHandlers`).
 */
final class EditMutationRegistry
{
    /** @var list<EditMutationHandlerInterface> */
    private array $handlers;

    /**
     * @param list<EditMutationHandlerInterface>|null $handlers
     */
    public function __construct(?array $handlers = null)
    {
        if ($handlers !== null) {
            $this->handlers = $handlers;

            return;
        }

        $this->handlers = [];
        foreach (ProductRegistryConfig::section('dataAccessEditMutationHandlers') as $class) {
            if (!is_string($class) || !is_subclass_of($class, EditMutationHandlerInterface::class)) {
                continue;
            }
            $this->handlers[] = new $class();
        }
    }

    public function getHandler(string $attributeGroup): ?EditMutationHandlerInterface
    {
        $attributeGroup = trim($attributeGroup);
        foreach ($this->handlers as $handler) {
            if ($handler->supports($attributeGroup)) {
                return $handler;
            }
        }

        return null;
    }
}
