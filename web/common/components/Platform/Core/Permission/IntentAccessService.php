<?php

namespace common\components\Platform\Core\Permission;

/**
 * Autorización unificada para intents del asistente: listado de atajos y ejecución.
 *
 * La clave RBAC es siempre el {@see intent_id} (sin fallback a `rbac_route` legacy).
 */
final class IntentAccessService
{
    public static function userCanExecuteIntent(int $userId, string $intentId): bool
    {
        $intentId = trim($intentId);
        if ($intentId === '' || $userId <= 0) {
            return false;
        }

        if (BioenlaceAccessChecker::isSuperadminUserId($userId)) {
            return true;
        }

        $permissionKey = IntentPermissionResolver::resolve($intentId);

        return BioenlaceAccessChecker::userCanPermissionKey($userId, $permissionKey);
    }
}
