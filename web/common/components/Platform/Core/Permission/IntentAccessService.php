<?php

namespace common\components\Platform\Core\Permission;

use Yii;

/**
 * Autorización unificada para intents del asistente: listado de atajos y ejecución.
 *
 * Clave assignable: siempre {@see intent_id}.
 * Si el manifiesto declara `rbac_route` y el usuario ya puede esa ruta API, también se permite:
 * el intent es la fachada conversacional de la misma capacidad HTTP (evita catálogo vacío
 * tras deploy de YAML antes de `catalog-permission/sync` / migración de grants).
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
}
