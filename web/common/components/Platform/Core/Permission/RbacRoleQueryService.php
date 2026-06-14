<?php

namespace common\components\Platform\Core\Permission;

use common\models\rbac\AuthRole;
use Yii;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\rbac\Role;

/**
 * Consultas de roles RBAC sin webvimark {@see \webvimark\modules\UserManagement\models\rbacDB\Role}.
 */
final class RbacRoleQueryService
{
    /**
     * @return array<string, Role>
     */
    public static function getUserRoles(int $userId): array
    {
        if ($userId <= 0 || !Yii::$app->has('authManager')) {
            return [];
        }

        return Yii::$app->authManager->getRolesByUser($userId);
    }

    /**
     * Roles PES / efector disponibles para asignar.
     *
     * @return list<AuthRole>
     */
    public static function getAvailableRoles(bool $showAll = false, bool $asArray = false)
    {
        $auth = Yii::$app->authManager;
        $prefixes = [];
        if (isset($auth->rolesEspeciales) && is_array($auth->rolesEspeciales)) {
            $prefixes = $auth->rolesEspeciales;
        }
        if ($prefixes === []) {
            return $asArray ? [] : [];
        }

        $query = AuthRole::find();
        if (Yii::$app->user->isSuperadmin || $showAll) {
            $or = ['or'];
            foreach ($prefixes as $prefix) {
                $or[] = ['like', 'name', (string) $prefix, false];
            }
            $query->andWhere($or);
        } else {
            $sessionRoles = Yii::$app->session->get(BioenlaceSessionPermissions::SESSION_PREFIX_ROLES, []);
            if (!is_array($sessionRoles) || $sessionRoles === []) {
                return $asArray ? [] : [];
            }
            $query->andWhere(['name' => $sessionRoles]);
        }

        $result = $query->all();

        return $asArray ? ArrayHelper::map($result, 'name', 'name') : $result;
    }
}
