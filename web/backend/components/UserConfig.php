<?php

namespace backend\components;

use Yii;

use yii\web\User;
use yii\web\ForbiddenHttpException;

use webvimark\modules\UserManagement\models\User as WebvimarkUser;
use webvimark\modules\UserManagement\components\AuthHelper;
use webvimark\modules\UserManagement\models\rbacDB\Route;

use common\models\Persona;

/**
 * Class UserConfig
 * @package webvimark\modules\UserManagement\components
 */
class UserConfig extends User
{
	/**
	 * @inheritdoc
	 */
	public $identityClass = 'webvimark\modules\UserManagement\models\User';

	/**
	 * @inheritdoc
	 */
	public $enableAutoLogin = true;

	/**
	 * @inheritdoc
	 */
	public $cookieLifetime = 2592000;

	/**
	 * @inheritdoc
	 */
	public $loginUrl = ['/user-management/auth/login'];

	const IDENTITY_ID_KEY = 'mainIdentityId';
	const ADMIN_PERMISSION = 'admin';

	public function getIsImpersonated()
	{
    	return !is_null(Yii::$app->session->get(self::IDENTITY_ID_KEY));
	}
 
	public function setMainIdentityId($userId)
	{
  	  Yii::$app->sesion->set(self::IDENTITY_ID_KEY, $userId);
	}
 
	public function getMainIdentityId()
	{
    	$mainIdentityId = Yii::$app->session->get(self::IDENTITY_ID_KEY);
    	return !empty($mainIdentityId) ? $mainIdentityId : $this->getId();
	}

	/**
	 * Allows to call Yii::$app->user->isSuperadmin
	 *
	 * @return bool
	 */
	public function getIsSuperadmin()
	{
		return @Yii::$app->user->identity->superadmin == 1;
	}

	/**
	 * @return string
	 */
	public function getUsername()
	{
		return @Yii::$app->user->identity->username;
	}

	public function getIdRecursoHumano()
	{
		return null;
	}

	/**
	 * @inheritdoc
	 */
	protected function afterLogin($identity, $cookieBased, $duration)
	{
		if (!$identity->superadmin && !WebvimarkUser::hasRole("_x_efector_AdminSisse")) {
			throw new ForbiddenHttpException();
		}

		parent::afterLogin($identity, $cookieBased, $duration);
	}
}
