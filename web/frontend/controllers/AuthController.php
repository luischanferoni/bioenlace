<?php

namespace frontend\controllers;

use common\models\LoginForm;
use Yii;
use yii\web\Controller;

/**
 * Login y logout web (staff/paciente). RBAC post-login vía {@see \frontend\components\UserConfig}.
 */
class AuthController extends Controller
{
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
}
