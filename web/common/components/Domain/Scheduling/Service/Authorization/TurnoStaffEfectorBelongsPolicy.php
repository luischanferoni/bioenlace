<?php

namespace common\components\Domain\Scheduling\Service\Authorization;

use common\components\Platform\Core\Permission\Domain\DomainOperationContext;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Platform\Core\Permission\Domain\DomainOperationPolicyInterface;
use common\models\Scheduling\Turno;

/**
 * Staff: el turno pertenece al efector de la sesión operativa.
 */
final class TurnoStaffEfectorBelongsPolicy implements DomainOperationPolicyInterface
{
    public function assert(DomainOperationContext $ctx, $resource): void
    {
        if (!$resource instanceof Turno) {
            throw new \InvalidArgumentException('Se requiere un Turno.');
        }

        $idEfector = $ctx->idEfector ?? 0;
        if ($idEfector <= 0) {
            throw new DomainOperationForbiddenException('Se requiere contexto de efector.');
        }
        if ((int) $resource->id_efector !== $idEfector) {
            throw new DomainOperationForbiddenException('No autorizado.');
        }
    }
}
