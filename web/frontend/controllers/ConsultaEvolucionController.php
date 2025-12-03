<?php

namespace frontend\controllers;

use frontend\controllers\DefaultController;

use common\models\ConsultaDerivaciones;
use Yii;
use yii\web\Controller;

use frontend\components\SisseHtmlHelpers;

use common\models\Consulta;
use common\models\Turno;
use common\models\ConsultaEvolucion;
use common\models\FormularioDinamico;
use yii\base\Exception;

/**
 * ConsultasController implements the CRUD actions for Consulta model.
 */
class ConsultaEvolucionController extends DefaultController
{
    use \frontend\controllers\traits\ConsultaTrait;

    public $urlAnterior;
    public $urlActual;
    public $urlSiguiente;

    public $modelConsulta;


    public function createCore($modelConsulta)
    {
        $modeloEvolucion = $modelConsulta->consultaEvolucion ? $modelConsulta->consultaEvolucion: new ConsultaEvolucion;
       
        if (Yii::$app->request->post()) {
            $transaction = \Yii::$app->db->beginTransaction();

            try {                
                
                $modelConsulta->save();

                $modeloEvolucion->load(Yii::$app->request->post());   

                $modeloEvolucion->id_consulta = $modelConsulta->id_consulta;
                $modeloEvolucion->id_persona = $modelConsulta->paciente->id_persona;


                if (!$modeloEvolucion->save()) {
                    //var_dump($modeloEvolucion->errors);die;
                    throw new Exception();
                }                   
        
                $transaction->commit();
            
            } catch (\Exception $th) {
                if ($th->getMessage() != "") {
                    Yii::error($th->getMessage());
                }

                $transaction->rollBack();
                
                return $this->renderAjax('../consultas/v2/_form_evolucion', [
                    'modelConsulta' => $modelConsulta,
                    'modeloEvolucion' => $modeloEvolucion
                ]);
            }

            \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

            $urlSiguiente = $modelConsulta->urlSiguiente;
                      
            return [
                'success' => true,
                'msg' => 'La evolución fue cargada exitosamente.', 
                'url_siguiente' => $urlSiguiente
            ];
        }

        return $this->renderAjax('../consultas/v2/_form_evolucion', [
            'modelConsulta' => $modelConsulta,
            'modeloEvolucion' => $modeloEvolucion
        ]);
    }

}
