<?php

namespace common\components\Core\DataAccess;

/**
 * Control previo de scope antes de ejecutar una consulta.
 */
interface ScopeCheckerInterface
{
    /**
     * @throws \InvalidArgumentException si el scope no se cumple
     */
    public function assertAndResolve(QuerySpec $spec, PermissionContext $ctx): ScopeConstraint;
}
