<?php

namespace frontend\components;

use common\components\Platform\Core\Permission\BioenlaceAccessChecker;
use common\models\User;
use Yii;
use yii\base\Action;
use yii\base\ActionFilter;
use yii\web\ForbiddenHttpException;

/**
 * Rutas web staff (SPA/shell): solo autenticación. RBAC de negocio vive en API v1.
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
            $this->denyAccess();

            return false;
        }

        if (Yii::$app->user->identity === null) {
            Yii::$app->getSession()->destroy();
            $this->denyAccess();

            return false;
        }

        $identity = Yii::$app->user->identity;
        if ($identity !== null && !BioenlaceAccessChecker::isActiveIdentity($identity)) {
            Yii::$app->user->logout();
            Yii::$app->getResponse()->redirect(Yii::$app->getHomeUrl());

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

    protected function denyAccess(): void
    {
        if (Yii::$app->user->isGuest) {
            // loginRequired() arma el redirect a login; no lanzar 403 después o el usuario ve Forbidden.
            Yii::$app->user->loginRequired();

            return;
        }

        throw new ForbiddenHttpException(Yii::t('yii', 'You are not allowed to perform this action.'));
    }
}
