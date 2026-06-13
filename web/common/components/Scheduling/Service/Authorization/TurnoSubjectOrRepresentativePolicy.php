<?php

namespace common\components\Scheduling\Service\Authorization;

use common\components\Core\Permission\Domain\DomainOperationContext;
use common\components\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Core\Permission\Domain\DomainOperationPolicyInterface;
use common\components\Person\Representation\Enum\RepresentationPermission;
use common\components\Person\Representation\Service\PersonRepresentationAccessService;
use common\models\Turno;

/**
 * Paciente titular o representante con permiso de agenda sobre el turno.
 */
final class TurnoSubjectOrRepresentativePolicy implements DomainOperationPolicyInterface
{
    public function assert(DomainOperationContext $ctx, $resource): void
    {
        if (!$resource instanceof Turno) {
            throw new \InvalidArgumentException('Se requiere un Turno.');
        }

        $actor = $ctx->idPersona;
        if ($actor <= 0) {
            throw new DomainOperationForbiddenException('Sesión sin persona.');
        }

        $subject = (int) $resource->id_persona;
        if ($subject <= 0) {
            throw new DomainOperationForbiddenException('Turno sin paciente.');
        }
        if ($subject === $actor) {
            return;
        }

        $access = new PersonRepresentationAccessService();
        if (!$access->canAct($actor, $subject, RepresentationPermission::SCHEDULING_TURNO)) {
            throw new DomainOperationForbiddenException('No tenés permiso para operar por este paciente.');
        }
    }
}
