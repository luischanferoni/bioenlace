<?php

namespace common\components\Domain\Organization\DataAccess\Scope;

use common\components\Platform\Core\DataAccess\PermissionContext;
use common\components\Platform\Core\DataAccess\QuerySpec;
use common\components\Platform\Core\DataAccess\ScopeCheckerInterface;
use common\components\Platform\Core\DataAccess\ScopeConstraint;
use common\components\Domain\Organization\Service\Efectores\OrganizationEfectorAccess;
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
