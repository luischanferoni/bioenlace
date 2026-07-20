<?php

namespace common\components\Platform\Core\Permission;

use Yii;
use yii\db\Query;
use yii\rbac\Item;

/**
 * Autorización unificada para intents del asistente: listado de atajos y ejecución.
 *
 * Clave assignable: siempre {@see intent_id}.
 * Si el manifiesto declara `rbac_route` y el permiso del intent **aún no** está en
 * `auth_item`, también se permite por ruta (ventana de deploy antes del sync).
 * Si el permiso del intent **ya existe**, hace falta el grant explícito: evita que
 * una `rbac_route` compartida (p. ej. propio vs staff) habilite atajos de terceros.
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
