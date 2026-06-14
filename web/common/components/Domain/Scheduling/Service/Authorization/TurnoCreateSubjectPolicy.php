<?php

namespace common\components\Domain\Scheduling\Service\Authorization;

use common\components\Platform\Core\Permission\Domain\DomainOperationContext;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Platform\Core\Permission\Domain\DomainOperationPolicyInterface;
use common\components\Domain\Person\Representation\Enum\RepresentationPermission;
use common\components\Domain\Person\Representation\Service\PersonRepresentationAccessService;
use common\components\Domain\Person\Representation\Service\PersonRepresentationSubjectService;

/**
 * Alta de turno: sujeto del turno (yo o representado) con permiso SCHEDULING_TURNO.
 */
final class TurnoCreateSubjectPolicy implements DomainOperationPolicyInterface
{
    public function assert(DomainOperationContext $ctx, $resource): void
    {
        $params = is_array($resource) ? $resource : $ctx->params;
        if (!is_array($params)) {
            throw new \InvalidArgumentException('Se requieren parámetros de alta.');
        }

        $subjectSvc = new PersonRepresentationSubjectService();
        $subject = $subjectSvc->resolveSubjectPersonaId($params);
        $actor = $ctx->idPersona;
        if ($actor <= 0) {
            throw new DomainOperationForbiddenException('Sesión sin persona.');
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
