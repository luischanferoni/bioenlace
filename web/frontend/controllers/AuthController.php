<?php

namespace frontend\controllers;

use common\models\forms\ChangeOwnPasswordForm;
use common\models\forms\ConfirmEmailForm;
use common\models\forms\PasswordRecoveryForm;
use common\models\LoginForm;
use common\models\User;
use Yii;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Autenticación web: login, logout, contraseña y confirmación de e-mail.
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
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $this->layout = '@frontend/views/layouts/loginLayout.php';
        $model = new LoginForm();

        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->redirect(Yii::$app->user->getReturnUrl(Yii::$app->homeUrl));
        }

        $model->password = '';

        return $this->render('@frontend/views/login/login', ['model' => $model]);
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->redirect(Yii::$app->user->loginUrl);
    }

    public function actionChangeOwnPassword()
    {
        if (Yii::$app->user->isGuest) {
            return $this->redirect(['/auth/login']);
        }

        $user = User::findOne((int) Yii::$app->user->id);
        if ($user === null || (int) $user->status !== User::STATUS_ACTIVE) {
            throw new ForbiddenHttpException();
        }

        $model = new ChangeOwnPasswordForm(['user' => $user]);

        if ($model->load(Yii::$app->request->post()) && $model->changePassword()) {
            return $this->render('@frontend/views/login/change-own-password-success');
        }

        return $this->render('@frontend/views/login/change-own-password', ['model' => $model]);
    }

    public function actionPasswordRecovery()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $this->layout = '@frontend/views/layouts/loginLayout.php';
        $model = new PasswordRecoveryForm();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail(false)) {
                return $this->render('@frontend/views/login/password-recovery-success');
            }
            Yii::$app->session->setFlash('error', 'No se pudo enviar el mensaje al e-mail indicado.');
        }

        return $this->render('@frontend/views/login/password-recovery', ['model' => $model]);
    }

    public function actionPasswordRecoveryReceive($token)
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $this->layout = '@frontend/views/layouts/loginLayout.php';
        $user = User::findByConfirmationToken($token);
        if ($user === null) {
            throw new NotFoundHttpException('Token no encontrado o expirado. Solicite restablecer la contraseña nuevamente.');
        }

        $model = new ChangeOwnPasswordForm([
            'scenario' => ChangeOwnPasswordForm::SCENARIO_RESTORE_VIA_EMAIL,
            'user' => $user,
        ]);

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $model->changePassword(false);

            return $this->render('@frontend/views/login/change-own-password-success');
        }

        return $this->render('@frontend/views/login/change-own-password', ['model' => $model]);
    }

    public function actionConfirmEmail()
    {
        if (Yii::$app->user->isGuest) {
            return $this->redirect(['/auth/login']);
        }

        $user = User::findOne((int) Yii::$app->user->id);
        if ($user === null || (int) $user->status !== User::STATUS_ACTIVE) {
            throw new ForbiddenHttpException();
        }

        if ((int) $user->email_confirmed === 1) {
            return $this->render('@frontend/views/login/confirm-email-success', ['user' => $user]);
        }

        $model = new ConfirmEmailForm([
            'email' => $user->email,
            'user' => $user,
        ]);

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail(false)) {
                return $this->refresh();
            }
            Yii::$app->session->setFlash('error', 'No se pudo enviar el mensaje al e-mail indicado.');
        }

        return $this->render('@frontend/views/login/confirm-email', ['model' => $model]);
    }

    public function actionConfirmEmailReceive($token)
    {
        $user = User::findByConfirmationToken($token);
        if ($user === null) {
            throw new NotFoundHttpException('Token no encontrado o expirado.');
        }

        $user->email_confirmed = 1;
        $user->removeConfirmationToken();
        $user->save(false);

        $this->layout = '@frontend/views/layouts/loginLayout.php';

        return $this->render('@frontend/views/login/confirm-email-success', ['user' => $user]);
    }
}
