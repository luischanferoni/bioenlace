<?php

namespace common\components\Domain\Clinical\Inpatient\Service\Authorization;

use common\components\Domain\Clinical\Inpatient\Service\InternacionAccessService;
use common\components\Platform\Core\Permission\Domain\DomainOperationContext;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Platform\Core\Permission\Domain\DomainOperationPolicyInterface;
use common\models\SegNivelInternacion;

/**
 * Staff o paciente con acceso clínico a la internación (efector, encounter abierto, titular).
 */
final class ClinicalInternacionStaffAccessPolicy implements DomainOperationPolicyInterface
{
    public function assert(DomainOperationContext $ctx, $resource): void
    {
        if (!$resource instanceof SegNivelInternacion) {
            throw new \InvalidArgumentException('Se requiere una internación (SegNivelInternacion).');
        }

        if (!InternacionAccessService::staffCanAccess($resource)) {
            throw new DomainOperationForbiddenException('No tiene permiso para acceder a esta internación.');
        }
    }
}
