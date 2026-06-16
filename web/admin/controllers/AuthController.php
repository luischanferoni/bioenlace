<?php

namespace admin\controllers;

use admin\components\UserConfig;
use common\models\LoginForm;
use Yii;

/**
 * Login/logout admin: formulario compartido con frontend; layout y sesión propios del admin.
 */
class AuthController extends \frontend\controllers\AuthController
{
    public function beforeAction($action): bool
    {
        $guestLayoutActions = [
            'login',
            'password-recovery',
            'password-recovery-receive',
        ];
        if (in_array($action->id, $guestLayoutActions, true)) {
            $this->layout = '@admin/views/layouts/loginLayout.php';
        }

        return parent::beforeAction($action);
    }

    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();

        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            $returnUrl = Yii::$app->user->getReturnUrl(Yii::$app->homeUrl);
            if (!UserConfig::isValidReturnUrl($returnUrl)) {
                $returnUrl = Yii::$app->homeUrl;
            }

            return $this->redirect($returnUrl);
        }

        $model->password = '';

        return $this->render('@frontend/views/login/login', ['model' => $model]);
    }
}
