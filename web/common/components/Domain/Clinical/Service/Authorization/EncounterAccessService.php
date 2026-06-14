<?php

namespace common\components\Domain\Clinical\Service\Authorization;

use common\components\Platform\Core\Permission\Domain\DomainOperationAuthorizer;
use common\components\Platform\Core\Permission\Domain\DomainOperationContext;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\models\Clinical\Encounter;

/**
 * Acceso a encounter vía políticas de dominio (servicios transversales, media, asistente).
 */
final class EncounterAccessService
{
    /**
     * @throws DomainOperationForbiddenException
     */
    public static function assertAccess(
        Encounter $encounter,
        string $operationKey = 'Encounter.access',
        ?string $representationPermission = null
    ): void {
        (new DomainOperationAuthorizer())->assert(
            $operationKey,
            $encounter,
            DomainOperationContext::fromApplication([
                'representation_permission' => $representationPermission,
            ])
        );
    }

    public static function canAccess(
        Encounter $encounter,
        string $operationKey = 'Encounter.access',
        ?string $representationPermission = null
    ): bool {
        try {
            self::assertAccess($encounter, $operationKey, $representationPermission);

            return true;
        } catch (DomainOperationForbiddenException) {
            return false;
        }
    }
}
