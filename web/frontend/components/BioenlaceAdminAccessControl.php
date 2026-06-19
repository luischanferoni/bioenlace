<?php

namespace frontend\components;

use common\components\Platform\Core\Permission\BioenlaceAccessChecker;
use Yii;
use yii\base\Action;
use yii\base\ActionFilter;
use yii\web\ForbiddenHttpException;

/**
 * RBAC por ruta para admin, sobre Yii {@see BioenlaceDbManager} (sin webvimark).
 */
class BioenlaceAdminAccessControl extends ActionFilter
{
    public function beforeAction($action): bool
    {
        if (!$action instanceof Action) {
            return true;
        }

        if (in_array($action->id, ['captcha', 'error'], true)) {
            return true;
        }

        $path = trim((string) (Yii::$app->params['path'] ?? ''), '/');
        $route = ($path !== '' ? '/' . $path : '') . '/' . $action->uniqueId;

        if (Yii::$app->user->isGuest) {
            $this->denyAccess();
        }

        if (!Yii::$app->user->isGuest && Yii::$app->user->identity === null) {
            Yii::$app->getSession()->destroy();
            $this->denyAccess();
        }

        $identity = Yii::$app->user->identity;
        if ($identity !== null && !BioenlaceAccessChecker::isActiveIdentity($identity)) {
            Yii::$app->user->logout();
            Yii::$app->getResponse()->redirect(Yii::$app->getHomeUrl());
            return false;
        }

        $userId = (int) Yii::$app->user->id;
        if (BioenlaceAccessChecker::isSuperadminUserId($userId)) {
            return true;
        }

        if (BioenlaceAccessChecker::userHasRoute($userId, $route)) {
            return true;
        }

        if (isset($this->denyCallback)) {
            call_user_func($this->denyCallback, null, $action);
        } else {
            $this->denyAccess();
        }

        return false;
    }

    protected function denyAccess(): void
    {
        if (Yii::$app->user->isGuest) {
            Yii::$app->user->loginRequired();

            return;
        }

        throw new ForbiddenHttpException(Yii::t('yii', 'You are not allowed to perform this action.'));
    }
}
