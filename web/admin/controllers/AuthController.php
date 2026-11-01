<?php

namespace admin\controllers;

use admin\components\UserConfig;
use common\controllers\WebAuthController;
use common\models\Platform\LoginForm;
use Yii;

/**
 * Login/logout admin con vistas y sesión propias; reutiliza la lógica común de autenticación.
 */
class AuthController extends WebAuthController
{
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $this->layout = 'loginLayout';
        
        $model = new LoginForm();

        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->redirect($this->getReturnUrl());
        }

        $model->password = '';

        return $this->render('//site/login', ['model' => $model]);
    }

    protected function isValidReturnUrl(string $url): bool
    {
        return UserConfig::isValidReturnUrl($url);
    }
}
