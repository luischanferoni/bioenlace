<?php

namespace common\components\Platform\Core\Permission;

use Yii;
use yii\db\Query;
use yii\rbac\Item;

/**
 * Autorización unificada para intents del asistente: listado de atajos y ejecución.
 *
 * Clave assignable: siempre {@see intent_id}.
 *
 * - **Atajos** ({@see userHasIntentGrant}): solo grant explícito del intent en RBAC.
 * - **Ejecución / catálogo NL** ({@see userCanExecuteIntent}): grant del intent; si el permiso
 *   aún no está en `auth_item`, también se permite por `rbac_route` (ventana de deploy).
 *   Si el permiso del intent **ya existe**, hace falta el grant explícito.
 */
final class IntentAccessService
{
    /**
     * Atajos UI: solo intents con grant rol → intent_id (sin promoción por ruta API compartida).
     */
    public static function userHasIntentGrant(int $userId, string $intentId): bool
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
        if (BioenlaceAccessChecker::userCanPermissionKey($userId, $permissionKey)) {
            return true;
        }

        // Permiso del intent ya materializado → no promover por ruta compartida.
        if (self::permissionKeyExistsInAuth($permissionKey)) {
            return false;
        }

        $meta = IntentManifestIndex::get($intentId);
        if ($meta === null) {
            return false;
        }
        $route = trim((string) ($meta['rbac_route'] ?? ''));
        if ($route === '') {
            return false;
        }

        $route = BioenlaceSessionPermissions::unifyRoute($route);
        if (BioenlaceAccessChecker::userCanApiRoute($userId, $route)) {
            return true;
        }

        // Fallback directo a Yii RBAC (por si la sesión aún no materializó el mapa de rutas).
        if (Yii::$app->has('authManager') && Yii::$app->authManager->checkAccess($userId, $route)) {
            return true;
        }

        return false;
    }

    private static function permissionKeyExistsInAuth(string $permissionKey): bool
    {
        $permissionKey = trim($permissionKey);
        if ($permissionKey === '') {
            return false;
        }

        try {
            return (new Query())
                ->from('{{%auth_item}}')
                ->where(['name' => $permissionKey, 'type' => Item::TYPE_PERMISSION])
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
