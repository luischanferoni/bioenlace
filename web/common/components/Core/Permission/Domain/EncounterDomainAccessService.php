<?php

namespace common\components\Core\Permission\Domain;

use common\models\Clinical\Encounter;

/**
 * Acceso a encounter vía políticas de dominio (servicios transversales, media, asistente).
 */
final class EncounterDomainAccessService
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
