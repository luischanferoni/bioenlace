<?php

namespace frontend\components;

use webvimark\modules\UserManagement\components\GhostAccessControl;
use webvimark\modules\UserManagement\models\rbacDB\Route;
use webvimark\modules\UserManagement\models\User;
use yii\base\Action;
use Yii;
use yii\base\ActionFilter;
use yii\web\ForbiddenHttpException;

class SisseGhostAccessControl extends GhostAccessControl
{
    public function beforeAction($action)
	{
		if ( $action->id == 'captcha' )
		{
			return true;
		}

		$route = Yii::$app->params['path'].'/' . $action->uniqueId;

		if ( Route::isFreeAccess($route, $action) )
		{
			return true;
		}

		if ( Yii::$app->user->isGuest )
		{
			$this->denyAccess();
		}

		// If user has been deleted, then destroy session and redirect to home page
		if ( ! Yii::$app->user->isGuest AND Yii::$app->user->identity === null )
		{
			Yii::$app->getSession()->destroy();
			$this->denyAccess();
		}

		// Superadmin owns everyone
		if ( Yii::$app->user->isSuperadmin )
		{
			return true;
		}

		if ( Yii::$app->user->identity AND Yii::$app->user->identity->status != User::STATUS_ACTIVE)
		{
			Yii::$app->user->logout();
			Yii::$app->getResponse()->redirect(Yii::$app->getHomeUrl());
		}

		// Log para debugging: verificar qué ruta se está verificando
		$allowedRoutes = Yii::$app->session->get(\webvimark\modules\UserManagement\components\AuthHelper::SESSION_PREFIX_ROUTES, []);
		$unifiedRoute = \webvimark\modules\UserManagement\components\AuthHelper::unifyRoute($route);
		Yii::info("SisseGhostAccessControl: Verificando ruta - Original: {$route}, Unificada: {$unifiedRoute}, AllowedRoutes: " . json_encode($allowedRoutes), 'access-control');

		if ( User::canRoute($route) )
		{
			return true;
		}

		if ( isset($this->denyCallback) )
		{
			call_user_func($this->denyCallback, null, $action);
		}
		else
		{
			$this->denyAccess();
		}

		return false;
	}
}