<?php

namespace common\components\Domain\Scheduling\Service\Authorization;

use common\components\Platform\Core\Permission\Domain\DomainOperationContext;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Platform\Core\Permission\Domain\DomainOperationPolicyInterface;
use common\components\Domain\Person\Representation\Enum\RepresentationPermission;
use common\components\Domain\Person\Representation\Service\PersonRepresentationSubjectService;
use yii\web\ForbiddenHttpException;

/**
 * Alta de turno: sujeto del turno (yo, representado o ventanilla) con permiso SCHEDULING_TURNO.
 */
final class TurnoCreateSubjectPolicy implements DomainOperationPolicyInterface
{
    public function assert(DomainOperationContext $ctx, $resource): void
    {
        $params = is_array($resource) ? $resource : $ctx->params;
        if (!is_array($params)) {
            throw new \InvalidArgumentException('Se requieren parámetros de alta.');
        }

        try {
            (new PersonRepresentationSubjectService())->resolveAndAuthorize(
                $params,
                RepresentationPermission::SCHEDULING_TURNO
            );
        } catch (ForbiddenHttpException $e) {
            throw new DomainOperationForbiddenException($e->getMessage());
        }
    }
}
