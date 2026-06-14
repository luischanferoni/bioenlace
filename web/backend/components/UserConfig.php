<?php

namespace backend\components;

use Yii;

use yii\web\User;
use yii\web\ForbiddenHttpException;

use common\models\Persona;
use common\components\Core\Permission\BioenlaceAccessChecker;

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
	public $loginUrl = ['/auth/login'];

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

	public function getIdProfesionalEfectorServicio()
	{
		return null;
	}

    /**
     * Compatibilidad con helpers de frontend: efector en sesión.
     */
    public function setIdEfector($idEfector)
    {
        Yii::$app->session->set('idEfector', $idEfector);
    }

    public function getIdEfector()
    {
        return Yii::$app->session->get('idEfector');
    }

	/**
	 * @inheritdoc
	 */
	protected function afterLogin($identity, $cookieBased, $duration)
	{
		if (!$identity->superadmin && !\common\models\User::hasRole('_x_efector_AdminSisse')) {
			throw new ForbiddenHttpException();
		}

		parent::afterLogin($identity, $cookieBased, $duration);
		BioenlaceAccessChecker::refreshForIdentity($identity);
	}
}
