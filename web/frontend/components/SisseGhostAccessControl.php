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

		// Impersonate desde el backend: sesión "admin" es otra cookie que la del frontend; el usuario llega como guest.
		// El admin escribe runtime/impersonation/a.txt; solo entonces se permite esta acción sin sesión previa.
		if (self::hasPendingImpersonationTicket($action)) {
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

	/**
	 * Ticket colocado por backend UserController::actionImpersonate (archivo en runtime del frontend).
	 */
	private static function hasPendingImpersonationTicket(Action $action): bool
	{
		if ($action->uniqueId !== 'site/impersonate') {
			return false;
		}
		$path = Yii::getAlias('@runtime') . '/impersonation/a.txt';
		if (!is_file($path)) {
			return false;
		}
		$raw = @file_get_contents($path);

		return is_string($raw) && trim($raw) !== '';
	}
}