<?php

namespace frontend\controllers\userManagement;

use Yii;
use yii\web\Controller;

/**
 * Redirecciones legacy `user-management/auth/*` → rutas Bioenlace {@see \frontend\controllers\AuthController}.
 */
class AuthController extends Controller
{
    public function actions(): array
    {
        return [
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'minLength' => 3,
                'maxLength' => 4,
                'offset' => 5,
            ],
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

    public function actionConfirmEmail()
    {
        return $this->redirect(['/auth/confirm-email']);
    }

    public function actionConfirmEmailReceive($token)
    {
        return $this->redirect(['/auth/confirm-email-receive', 'token' => $token]);
    }

    public function actionRegistration()
    {
        return $this->redirect(['/auth/login']);
    }

    public function actionConfirmRegistrationEmail($token)
    {
        return $this->redirect(['/auth/login']);
    }
}
