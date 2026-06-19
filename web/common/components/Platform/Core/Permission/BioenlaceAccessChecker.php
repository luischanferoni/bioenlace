<?php

namespace common\components\Platform\Core\Permission;

use common\components\Platform\Core\Db\BioenlaceDb;
use Yii;
use yii\web\IdentityInterface;

/**
 * Chequeos RBAC vía {@see BioenlaceDbManager} (Yii), sin webvimark User::canRoute.
 */
final class BioenlaceAccessChecker
{
    public static function refreshForIdentity(IdentityInterface $identity): void
    {
        BioenlaceSessionPermissions::refreshForIdentity($identity);
    }

    public static function ensureUpToDate(): void
    {
        BioenlaceDb::ensureConnection();
        BioenlaceSessionPermissions::ensureUpToDate();
    }

    public static function userCanPermissionKey(int $userId, string $permissionKey): bool
    {
        $permissionKey = trim($permissionKey);
        if ($permissionKey === '' || $userId <= 0 || !Yii::$app->has('authManager')) {
            return false;
        }

        if (self::isSuperadminUserId($userId)) {
            return true;
        }

        BioenlaceSessionPermissions::ensureUpToDate();
        if (Yii::$app->has('session')) {
            $perms = Yii::$app->session->get(BioenlaceSessionPermissions::SESSION_PREFIX_PERMISSIONS, []);
            if (is_array($perms) && isset($perms[$permissionKey])) {
                return true;
            }
        }

        return Yii::$app->authManager->checkAccess($userId, $permissionKey);
    }

    public static function userCanApiRoute(int $userId, string $route): bool
    {
        if ($userId <= 0) {
            return false;
        }
        if (self::isSuperadminUserId($userId)) {
            return true;
        }

        foreach (ApiRoutePermissionResolver::candidates($route) as $candidate) {
            if (self::userHasRoute($userId, $candidate)) {
                return true;
            }
        }

        return false;
    }

    public static function userHasRoute(int $userId, string $route): bool
    {
        $route = BioenlaceSessionPermissions::unifyRoute($route);
        if ($userId <= 0) {
            return false;
        }
        if (self::isSuperadminUserId($userId)) {
            return true;
        }

        BioenlaceSessionPermissions::ensureUpToDate();
        if (!Yii::$app->has('session')) {
            return false;
        }
        $routes = Yii::$app->session->get(BioenlaceSessionPermissions::SESSION_PREFIX_ROUTES, []);
        if (!is_array($routes)) {
            return false;
        }

        return isset($routes[$route]);
    }

    public static function isSuperadminUserId(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        if (!Yii::$app->user->isGuest && (int) Yii::$app->user->id === $userId) {
            $identity = Yii::$app->user->identity;
            if ($identity !== null && property_exists($identity, 'superadmin')) {
                return (int) $identity->superadmin === 1;
            }
            if (method_exists(Yii::$app->user, 'getIsSuperadmin')) {
                return (bool) Yii::$app->user->isSuperadmin;
            }
        }

        return false;
    }

    public static function isActiveIdentity(?IdentityInterface $identity): bool
    {
        if ($identity === null) {
            return false;
        }
        if (!property_exists($identity, 'status')) {
            return true;
        }

        return (int) $identity->status === 1;
    }
}
