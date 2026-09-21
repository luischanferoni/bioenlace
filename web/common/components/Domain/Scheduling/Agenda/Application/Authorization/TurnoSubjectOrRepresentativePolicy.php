<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Authorization;

use common\components\Platform\Core\Permission\Domain\DomainOperationContext;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Platform\Core\Permission\Domain\DomainOperationPolicyInterface;
use common\components\Domain\Person\Representation\Domain\RepresentationPermission;
use common\components\Domain\Person\Representation\Application\Service\PersonRepresentationSubjectService;
use common\models\Scheduling\Turno;
use yii\web\ForbiddenHttpException;

/**
 * Paciente titular, representante o ventanilla con permiso de agenda sobre el turno.
 */
final class TurnoSubjectOrRepresentativePolicy implements DomainOperationPolicyInterface
{
    public function assert(DomainOperationContext $ctx, $resource): void
    {
        if (!$resource instanceof Turno) {
            throw new \InvalidArgumentException('Se requiere un Turno.');
        }

        try {
            (new PersonRepresentationSubjectService())->assertCanAct(
                (int) $resource->id_persona,
                RepresentationPermission::SCHEDULING_TURNO
            );
        } catch (ForbiddenHttpException $e) {
            throw new DomainOperationForbiddenException($e->getMessage());
        }
    }
}
