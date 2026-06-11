<?php

namespace frontend\controllers\userManagement;

use Yii;
use webvimark\modules\UserManagement\controllers\AuthController as WebvimarkAuthController;

/**
 * Auth webvimark frontend: logout → login (no homeUrl legacy site/index).
 */
class AuthController extends WebvimarkAuthController
{
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->redirect(Yii::$app->user->loginUrl);
    }
}
