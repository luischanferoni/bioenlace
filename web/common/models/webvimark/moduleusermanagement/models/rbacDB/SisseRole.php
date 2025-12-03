<?php

namespace common\models\webvimark\moduleusermanagement\models\rbacDB;

use Exception;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use webvimark\modules\UserManagement\components\AuthHelper;
use Yii;
use yii\helpers\ArrayHelper;
use yii\rbac\DbManager;

class SisseRole extends Role
{

    /**
	 * Return only roles, that are assigned to the current user.
	 * Return all if superadmin
	 * Useful for forms where user can give roles to another users, but we restrict him only with roles he possess
	 *
	 * @param bool $showAll
	 * @param bool $asArray
	 *
	 * @return static[]
	 */
	public static function getAvailableRoles($showAll = false, $asArray = false)
	{
        if (!isset(Yii::$app->authManager->rolesEspeciales) || count(Yii::$app->authManager->rolesEspeciales) == 0) {
            return [];
        }
        foreach (Yii::$app->authManager->rolesEspeciales as $rolEspecial) {
            $rolesEspeciales[] = 'name LIKE "%'.$rolEspecial.'%"';
        }
                
		$condition = (Yii::$app->user->isSuperAdmin OR $showAll) ? implode(' OR ', $rolesEspeciales) : ['name'=>Yii::$app->session->get(AuthHelper::SESSION_PREFIX_ROLES)];

		$result = static::find()->andWhere($condition)->all();

		return $asArray ? ArrayHelper::map($result, 'name', 'name') : $result;
	}

}