<?php

namespace common\components\Domain\Organization\DataAccess\Scope;

use common\components\Platform\Core\DataAccess\PermissionContext;
use common\components\Platform\Core\DataAccess\QuerySpec;
use common\components\Platform\Core\DataAccess\ScopeCheckerInterface;
use common\components\Platform\Core\DataAccess\ScopeConstraint;
use common\components\Domain\Organization\Service\Efectores\OrganizationEfectorAccess;
use common\models\ProfesionalEfectorServicio;
use Yii;

/**
 * Acota al efector del PES en sesión operativa; fallback a {@see EfectorSesionScopeChecker}.
 */
final class EfectorSesionViaPesScopeChecker implements ScopeCheckerInterface
{
    public function assertAndResolve(QuerySpec $spec, PermissionContext $ctx): ScopeConstraint
    {
        $idPesRaw = Yii::$app->user->getIdProfesionalEfectorServicio();
        $idPes = $idPesRaw !== null && $idPesRaw !== '' ? (int) $idPesRaw : 0;
        if ($idPes > 0) {
            $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);
            if ($pes !== null) {
                $idEfector = (int) $pes->id_efector;
                OrganizationEfectorAccess::assertCanAccessEfector($idEfector);
                $constraint = new ScopeConstraint();
                $constraint->idEfector = $idEfector;

                return $constraint;
            }
        }

        return (new EfectorSesionScopeChecker())->assertAndResolve($spec, $ctx);
    }
}
