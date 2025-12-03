<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\httpclient\Client;
use yii\filters\VerbFilter;
use yii\helpers\Json;

use webvimark\modules\UserManagement\models\User;

use common\models\Servicio;
use common\models\busquedas\ServicioBusqueda;

/**
 * RondasController implements the CRUD actions for Servicio model.
 */
class RondasController extends Controller
{
    public function getHostFormsAPI()
    {
        return Yii::$app->params['hostFormsAPI'];
    }
        
    public function behaviors()
    {
        //control de acceso mediante la extension
        return [
            'ghost-access'=> [
                'class' => 'frontend\components\SisseGhostAccessControl',
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    public function actionResultados() 
    {


    }
}
