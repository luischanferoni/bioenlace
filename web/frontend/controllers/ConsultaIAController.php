<?php

namespace frontend\controllers;

use frontend\controllers\DefaultController;

use Yii;
use yii\web\Controller;

use yii\base\Exception;

use frontend\components\SisseHtmlHelpers;

use common\models\Consulta;
use common\models\Turno;
use common\models\ConsultaIa;

class ConsultaIAController extends DefaultController
{
    //use \frontend\controllers\traits\ConsultaTrait;


    public function createCore($modelConsulta)
    {
        $modeloIA = $modelConsulta->ia;

        if (!$modeloIA) {
            $modeloIA = new ConsultaIa();
        }

        /*-----------------------------------*/
        if (Yii::$app->request->post()) {
            $modeloIA->load(Yii::$app->request->post());

            $transaction = \Yii::$app->db->beginTransaction();

            try {

                $modelConsulta->save();

                $transaction->commit();

            } catch (\Exception $th) {
                if ($th->getMessage() != "") {
                    var_dump($th->getMessage());
                    die;
                    Yii::error($th->getMessage());
                }

                $transaction->rollBack();

                return $this->renderAjax('../consultas/v2/_form_ia', [
                    'modelConsulta' => $modelConsulta,
                    'modelIA' => $modeloIA
                ]);
            }
        }

        return $this->renderAjax('../consultas/v2/_form_ia', [
            'modelConsulta' => $modelConsulta,
            'modelIA' => $modeloIA
        ]);        
    }

}