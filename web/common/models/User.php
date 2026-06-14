<?php

namespace common\models;

use common\components\Core\Permission\BioenlaceAccessChecker;
use common\components\Core\Permission\BioenlaceSessionPermissions;
use Yii;
use webvimark\modules\UserManagement\models\User as webvimarkUser;

/**
 * Usuario de aplicación. RBAC vía {@see BioenlaceAccessChecker} / {@see BioenlaceSessionPermissions} (Yii), no webvimark AuthHelper.
 */
class User extends webvimarkUser
{
    /**
     * @param string|array<int|string, string> $route
     */
    public static function canRoute($route, $superAdminAllowed = true): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }

        $userId = (int) Yii::$app->user->id;
        if ($userId <= 0) {
            return false;
        }

        if ($superAdminAllowed && BioenlaceAccessChecker::isSuperadminUserId($userId)) {
            return true;
        }

        BioenlaceSessionPermissions::ensureUpToDate();

        foreach (self::expandRouteCandidates($route) as $candidate) {
            if (BioenlaceAccessChecker::userHasRoute($userId, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Roles asignados vía Yii RBAC ({@see BioenlaceDbManager}) y caché de sesión Bioenlace.
     *
     * @param string|array<int|string, string> $roles
     */
    public static function hasRole($roles, $superAdminAllowed = true): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }

        $userId = (int) Yii::$app->user->id;
        if ($userId <= 0) {
            return false;
        }

        if ($superAdminAllowed && BioenlaceAccessChecker::isSuperadminUserId($userId)) {
            return true;
        }

        $roleList = self::normalizeRoleList($roles);
        if ($roleList === []) {
            return false;
        }

        BioenlaceSessionPermissions::ensureUpToDate();
        if (Yii::$app->has('session')) {
            $sessionRoles = Yii::$app->session->get(BioenlaceSessionPermissions::SESSION_PREFIX_ROLES, []);
            if (is_array($sessionRoles)) {
                foreach ($roleList as $role) {
                    if (in_array($role, $sessionRoles, true)) {
                        return true;
                    }
                }
            }
        }

        if (Yii::$app->has('authManager')) {
            foreach ($roleList as $role) {
                if (Yii::$app->authManager->checkAccess($userId, $role)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string|array<int|string, string> $roles
     * @return list<string>
     */
    private static function normalizeRoleList($roles): array
    {
        $raw = is_array($roles) ? $roles : [$roles];
        $out = [];
        foreach ($raw as $role) {
            if (is_string($role)) {
                $role = trim($role);
                if ($role !== '') {
                    $out[] = $role;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param string|array<int|string, string> $route
     * @return list<string>
     */
    private static function expandRouteCandidates($route): array
    {
        $raw = is_array($route) ? $route : [$route];
        $out = [];
        foreach ($raw as $part) {
            if (is_string($part) && $part !== '') {
                $out[] = self::normalizeRbacRoute($part);
            }
        }
        if ($out === [] && is_array($route) && isset($route[0])) {
            $built = self::normalizeRbacRouteFromArray($route);
            if ($built !== '') {
                $out[] = $built;
            }
        }

        return array_values(array_unique(array_filter($out)));
    }

    /**
     * @param array<int|string, string> $route
     */
    private static function normalizeRbacRouteFromArray(array $route): string
    {
        $parts = [];
        foreach ($route as $k => $v) {
            if ($k === 0 && is_string($v)) {
                $parts[] = trim($v, '/');
            } elseif (is_string($k) && $k !== '0' && is_scalar($v)) {
                $parts[] = $k . '/' . $v;
            }
        }

        return self::normalizeRbacRoute(implode('/', $parts));
    }

    private static function normalizeRbacRoute(string $route): string
    {
        $route = '/' . ltrim(trim($route), '/');
        if (strncmp($route, '/api/', 5) === 0) {
            return BioenlaceSessionPermissions::unifyRoute($route);
        }
        if (preg_match('#^/(frontend|backend)/#', $route) === 1) {
            return $route;
        }

        $prefix = trim((string) (Yii::$app->params['path'] ?? ''), '/');
        if ($prefix !== '') {
            return '/' . $prefix . $route;
        }

        return $route;
    }

    public static function getPorRolPorEfector($rol, $id_efector)
    {
        $query = new yii\db\Query;
        $query->select(['`user`.*'])
                ->from('user')
                ->where('auth_item.name', $rol)
                ->andWhere('auth_item.type', 1)
                ->andWhere('user_efector.id_efector', $id_efector)
                ->leftJoin('user_efector', '`user_efector`.`id_user` = `user`.`id`')
                ->leftJoin('auth_item', '`auth_item`.`name` = `auth_assignment`.`item_name`')
                ->leftJoin('auth_assignment', '`user`.`id` = `auth_assignment`.`user_id`');

        $command = $query->createCommand();
        $data = $command->queryAll();

        return $data;
    }

    public function getNombrepersona($id)
    {
        $consulta_persona = \common\models\Persona::findOne(['id_persona' => $id]);
        $apellido_persona = $consulta_persona->apellido;
        $nombre_persona = $consulta_persona->nombre . ' ' . $consulta_persona->otro_nombre;
        $nombre = $apellido_persona . ', ' . $nombre_persona;

        return $nombre;
    }

    public function actualizarIduserpersona($idpersona, $iduser)
    {
        $actualizar_persona = \common\models\Persona::findOne(['id_persona' => $idpersona]);
        $actualizar_persona->id_user = $iduser;
        $actualizar_persona->scenario = 'scenarioregistrar';
        $actualizar_persona->save();
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        $request = Yii::$app->getRequest();
        if ($request instanceof \yii\web\Request) {
            $idpersona = $request->getQueryParam('id');
            if ($idpersona !== null && $idpersona !== '') {
                self::actualizarIduserpersona($idpersona, $this->id);
            }
        }
    }
}
