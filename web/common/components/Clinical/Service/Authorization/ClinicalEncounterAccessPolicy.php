<?php

namespace common\components\Clinical\Service\Authorization;

use common\components\Clinical\Service\EncounterAccessService;
use common\components\Core\Permission\Domain\DomainOperationContext;
use common\components\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Core\Permission\Domain\DomainOperationPolicyInterface;
use common\models\Clinical\Encounter;

/**
 * Paciente titular, representante (permiso opcional) o staff vía PES del encounter.
 */
final class ClinicalEncounterAccessPolicy implements DomainOperationPolicyInterface
{
    public function assert(DomainOperationContext $ctx, $resource): void
    {
        if (!$resource instanceof Encounter) {
            throw new \InvalidArgumentException('Se requiere un Encounter.');
        }

        $perm = trim((string) ($ctx->params['representation_permission'] ?? ''));
        $representationPermission = $perm !== '' ? $perm : null;

        if (!EncounterAccessService::userCanAccessEncounterApi($resource, $representationPermission)) {
            throw new DomainOperationForbiddenException('No tiene permiso para acceder a este encounter.');
        }
    }
}
