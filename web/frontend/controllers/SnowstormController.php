<?php

namespace frontend\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;


class SnowstormController extends Controller
{
    public function behaviors()
    {
         //control de acceso mediante la extensión
        return [
            'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
            ],
        ];
    }

    public function actionMedicamentos($q = null)
    {        
        $out = ['results' => ['id' => '', 'text' => '']];
        if (is_null($q)) {
            return $out;
        }

        $data = Yii::$app->snowstorm->getMedicamentosGenericos($q);

        return Json::encode(['results' => array_values($data)]);
    }

    public function actionMedicamentosAnmat($q = null)
    {        
        $out = ['results' => ['id' => '', 'text' => '']];
        if (is_null($q)) {
            return $out;
        }

        $data = Yii::$app->snowstorm->getMedicamentosAnmat($q);

        return Json::encode(['results' => array_values($data)]);
    }

    public function actionDiagnosticos($q = null)
    {
        // $id_persona = Yii::$app->getRequest()->getQueryParam('id');
        $out = ['results' => ['id' => '', 'text' => '']];
        if (is_null($q)) {
            return $out;
        }

        $data = Yii::$app->snowstorm->getProblemas($q);

        return Json::encode(['results' => array_values($data)]);
    }

    public function actionPracticas($q = null)
    {
        // $id_persona = Yii::$app->getRequest()->getQueryParam('id');
        $out = ['results' => ['id' => '', 'text' => '']];
        if (is_null($q)) {
            return $out;
        }

        $data = Yii::$app->snowstorm->getPracticas($q);

        return Json::encode(['results' => array_values($data)]);
    }

    public function actionAntecedentespersonales($q = null)
    {
        // $id_persona = Yii::$app->getRequest()->getQueryParam('id');
        $out = ['results' => ['id' => '', 'text' => '']];
        if (is_null($q)) {
            return $out;
        }

        $data = Yii::$app->snowstorm->getAntecedentesPersonales($q);

        return Json::encode(['results' => array_values($data)]);
    }

    public function actionAntecedentesfamiliares($q = null)
    {
        // $id_persona = Yii::$app->getRequest()->getQueryParam('id');
        $out = ['results' => ['id' => '', 'text' => '']];
        if (is_null($q)) {
            return $out;
        }

        $data = Yii::$app->snowstorm->getAntecedentesFamiliares($q);

        return Json::encode(['results' => array_values($data)]);
    }

    public function actionAlergias($q = null)
    {
        $out = ['results' => ['id' => '', 'text' => '']];
        if (is_null($q)) {
            return $out;
        }

        $data = Yii::$app->snowstorm->getAlergias($q);

        return Json::encode(['results' => array_values($data)]);
    }

    public function actionMotivosDeConsulta($q = null)
    {
        $out = ['results' => ['id' => '', 'text' => '']];
        if (is_null($q)) {
            return $out;
        }

        $data = Yii::$app->snowstorm->getMotivosDeConsulta($q);

        return Json::encode(['results' => array_values($data)]);
    }

    public function actionSintomas($q = null)
    {
        $out = ['results' => ['id' => '', 'text' => '']];
        if (is_null($q)) {
            return $out;
        }

        $data = Yii::$app->snowstorm->getSintomas($q);

        return Json::encode(['results' => array_values($data)]);
    }

    public function actionDiagnosticosOdontologia($q = null)
    {
        $out = ['results' => ['id' => '', 'text' => '']];
        if (is_null($q)) {
            return $out;
        }

        $data = Yii::$app->snowstorm->getDiagnosticosOdontologia($q);

        return Json::encode(['results' => array_values($data)]);
    }

    public function actionPracticasOdontologia($q = null)
    {
        $out = ['results' => ['id' => '', 'text' => '']];
        if (is_null($q)) {
            return $out;
        }

        $data = Yii::$app->snowstorm->getPracticasOdontologia($q);

        return Json::encode(['results' => array_values($data)]);
    }    
}