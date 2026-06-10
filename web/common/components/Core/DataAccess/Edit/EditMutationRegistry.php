<?php

namespace common\components\Core\DataAccess\Edit;

use common\components\Core\DataAccess\Edit\Handler\PersonIdentidadBasicaEditMutationHandler;

/**
 * Handlers de mutación por grupo de atributos.
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
        $this->handlers = $handlers ?? [
            new PersonIdentidadBasicaEditMutationHandler(),
        ];
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
