<?php

namespace common\components\Domain\Clinical\Inpatient\Application\Authorization;

use common\components\Domain\Clinical\Inpatient\Application\InpatientAccessService;
use common\components\Platform\Core\Permission\Domain\DomainOperationContext;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Platform\Core\Permission\Domain\DomainOperationPolicyInterface;
use common\models\Clinical\SegNivelInternacion;

/**
 * Staff o paciente con acceso clínico a la internación (efector, encounter abierto, titular).
 */
final class ClinicalInpatientStaffAccessPolicy implements DomainOperationPolicyInterface
{
    public function assert(DomainOperationContext $ctx, $resource): void
    {
        if (!$resource instanceof SegNivelInternacion) {
            throw new \InvalidArgumentException('Se requiere una internación (SegNivelInternacion).');
        }

        if (!InpatientAccessService::staffCanAccess($resource)) {
            throw new DomainOperationForbiddenException('No tiene permiso para acceder a esta internación.');
        }
    }
}
