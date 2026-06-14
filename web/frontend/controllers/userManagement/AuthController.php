<?php

namespace frontend\controllers\userManagement;

use Yii;
use webvimark\modules\UserManagement\UserManagementModule;
use webvimark\modules\UserManagement\controllers\AuthController as WebvimarkAuthController;

/**
 * Auth webvimark frontend: logout → login (no homeUrl legacy site/index).
 *
 * controllerMap en user-management apunta acá; $this->module puede no ser UserManagementModule
 * (p. ej. backend monta frontend\Module), por eso captchaOptions se lee del módulo explícito.
 */
class AuthController extends WebvimarkAuthController
{
    /**
     * @return array<string, mixed>
     */
    public function actions()
    {
        $userManagement = Yii::$app->getModule('user-management');
        if (!$userManagement instanceof UserManagementModule) {
            return [
                'captcha' => [
                    'class' => 'yii\captcha\CaptchaAction',
                    'minLength' => 3,
                    'maxLength' => 4,
                    'offset' => 5,
                ],
            ];
        }

        return [
            'captcha' => $userManagement->captchaOptions,
        ];
    }

    public function actionLogin()
    {
        return $this->redirect(array_merge(['/auth/login'], Yii::$app->request->get()));
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->redirect(Yii::$app->user->loginUrl);
    }

    public function actionChangeOwnPassword()
    {
        return $this->redirect(['/auth/change-own-password']);
    }

    public function actionPasswordRecovery()
    {
        return $this->redirect(array_merge(['/auth/password-recovery'], Yii::$app->request->get()));
    }

    public function actionPasswordRecoveryReceive($token)
    {
        return $this->redirect(['/auth/password-recovery-receive', 'token' => $token]);
    }
}
