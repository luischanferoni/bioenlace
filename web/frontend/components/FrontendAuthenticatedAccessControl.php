<?php

namespace frontend\components;

use common\components\Platform\Core\Permission\BioenlaceAccessChecker;
use Yii;
use yii\base\Action;
use yii\base\ActionFilter;
use yii\web\ForbiddenHttpException;

/**
 * Rutas web staff (SPA/shell): solo autenticación. RBAC de negocio vive en API v1.
 *
 * Guest → redirect a login (sin página de error con menú).
 */
class FrontendAuthenticatedAccessControl extends ActionFilter
{
    /** @var list<string> uniqueId sin RBAC (público o ticket especial) */
    private const FREE_ACTIONS = [
        'auth/login',
        'auth/password-recovery',
        'auth/password-recovery-receive',
        'auth/activate-account',
        'auth/activate-account-receive',
        'auth/confirm-email-receive',
        'auth/captcha',
        'site/captcha',
        'site/error',
        'site/robots-txt',
        'site/impersonate',
        'site/demo-entrar',
    ];

    public function beforeAction($action): bool
    {
        if (!$action instanceof Action) {
            return true;
        }

        if (in_array($action->uniqueId, self::FREE_ACTIONS, true)) {
            return true;
        }
        if ($action->id === 'captcha') {
            return true;
        }
        if ($action->uniqueId === 'site/impersonate' && self::hasPendingImpersonationTicket()) {
            return true;
        }

        if (Yii::$app->user->isGuest) {
            $this->redirectGuestToLogin();

            return false;
        }

        $identity = Yii::$app->user->identity;
        if ($identity === null) {
            Yii::$app->user->logout(false);
            $this->redirectGuestToLogin();

            return false;
        }

        if (!BioenlaceAccessChecker::isActiveIdentity($identity)) {
            Yii::$app->user->logout(false);
            $this->redirectGuestToLogin();

            return false;
        }

        BioenlaceAccessChecker::ensureUpToDate();
        WebApiJwtSessionService::ensureValidTokenInSession();

        return true;
    }

    private static function hasPendingImpersonationTicket(): bool
    {
        $path = Yii::getAlias('@runtime') . '/impersonation/a.txt';
        if (!is_file($path)) {
            return false;
        }
        $raw = @file_get_contents($path);

        return is_string($raw) && trim($raw) !== '';
    }

    /**
     * Redirect 302 a login. No ForbiddenHttpException: evita site/error con layout + menú.
     */
    private function redirectGuestToLogin(): void
    {
        $user = Yii::$app->user;
        $request = Yii::$app->request;
        $response = Yii::$app->response;

        if (!$request->getIsAjax()) {
            try {
                $user->setReturnUrl($request->getAbsoluteUrl());
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $loginUrl = $user->loginUrl;
        $response->redirect($loginUrl);
    }
}
