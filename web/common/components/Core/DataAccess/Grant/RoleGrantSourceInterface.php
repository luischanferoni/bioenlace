<?php

namespace common\components\Core\DataAccess\Grant;

/**
 * Fuente de grants rol → grupo de atributos.
 *
 * @phpstan-type GrantShape array{operations: list<string>, scope_checker?: string|null}
 */
interface RoleGrantSourceInterface
{
    /**
     * @return GrantShape|null
     */
    public function getGrant(string $roleName, string $entityGroupKey): ?array;
}
