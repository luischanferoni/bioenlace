<?php

namespace backend\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;

//use webvimark\modules\UserManagement\UserManagementModule;
use webvimark\modules\UserManagement\models\User;

use common\models\LoginForm;
use common\models\Efector;
use common\models\ContactForm;
use common\models\Rrhh;
use common\models\RrhhEfector;
use common\models\RrhhServicio;
use common\models\Persona;

class SiteController extends Controller
{
    public function behaviors()
    {
        return [
            'ghost-access'=> [
                'class' => 'webvimark\modules\UserManagement\components\GhostAccessControl',
            ],
        ];
    }

    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
          /*  'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],*/
        ];
    }

    public function actionIndex()
    {
		if (Yii::$app->user->isGuest) {
			Yii::$app->user->loginRequired();
		}
               
        return $this->render('index');
    }

    public function actionError()
    {
        $exception = Yii::$app->errorHandler->exception;

        return $this->render('error', ['exception' => $exception]);
    }  
}
