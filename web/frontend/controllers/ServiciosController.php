<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\Json;

use common\models\Servicio;
use common\models\busquedas\ServicioBusqueda;

/**
 * ServiciosController implements the CRUD actions for Servicio model.
 */
class ServiciosController extends Controller
{
    public function behaviors()
    {
        //control de acceso mediante la extension
        return [
            'ghost-access'=> [
                'class' => 'frontend\components\SisseGhostAccessControl',
                'except' => ['search']
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    public function actionSearch($q = null) 
    {
        $out = ['results' => ['id' => '', 'text' => '']];
        if (is_null($q)) {
            return $out;
        }

        $data = Servicio::search($q);

        echo Json::encode(['results' => array_values($data)]);
    }
}
