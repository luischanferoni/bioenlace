<?php

namespace common\components\Core\DataAccess\Edit;

use common\components\Core\DataAccess\PermissionContext;

/**
 * Persistencia de un grupo de atributos editable (scalar_group).
 */
interface EditMutationHandlerInterface
{
    public function supports(string $attributeGroup): bool;

    /**
     * @param array<string, string> $changes campo => valor nuevo
     * @param array<string, int|string> $subjectContext
     * @return list<array{field: string, label: string, before: string, after: string}>
     */
    public function apply(
        string $attributeGroup,
        array $changes,
        array $subjectContext,
        PermissionContext $ctx
    ): array;
}
