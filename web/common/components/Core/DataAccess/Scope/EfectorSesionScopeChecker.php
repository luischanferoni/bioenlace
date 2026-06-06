<?php

namespace common\components\Core\DataAccess\Scope;

use common\components\Core\DataAccess\PermissionContext;
use common\components\Core\DataAccess\QuerySpec;
use common\components\Core\DataAccess\ScopeCheckerInterface;
use common\components\Core\DataAccess\ScopeConstraint;
use common\components\Organization\Service\Efectores\OrganizationEfectorAccess;
use Yii;

/**
 * Acota consultas al efector de sesión o a efectores asignados al usuario.
 */
final class EfectorSesionScopeChecker implements ScopeCheckerInterface
{
    public function assertAndResolve(QuerySpec $spec, PermissionContext $ctx): ScopeConstraint
    {
        $idEfector = OrganizationEfectorAccess::resolveIdEfector($spec->requestedIdEfector);
        OrganizationEfectorAccess::assertCanAccessEfector($idEfector);

        $constraint = new ScopeConstraint();
        $constraint->idEfector = $idEfector;

        return $constraint;
    }
}
