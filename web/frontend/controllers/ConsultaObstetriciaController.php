<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;

use frontend\filters\SisseActionFilter;
use frontend\components\SisseHtmlHelpers;

use common\models\Consulta;
use common\models\ConsultaObstetricia;
use common\models\FormularioDinamico;

/**
 * ConsultasController implements the CRUD actions for Consulta model.
 */
class ConsultaObstetriciaController extends Controller
{
    public function behaviors()
    {
         //control de acceso mediante la extensión
        return [
            'ghost-access' => [
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

    public function actionCreate()
    {
        $session = Yii::$app->getSession();
        $paciente = unserialize($session->get('persona'));
        
        $modelConsulta = Consulta::getModeloConsulta(Yii::$app->request->get('id_consulta'), $paciente, Yii::$app->request->get('parent'), Yii::$app->request->get('parent_id'));
        //var_dump($modelConsulta); die;
        $modeloEmbarazo = isset($modelConsulta->consultaObstetricia)?$modelConsulta->consultaObstetricia: new ConsultaObstetricia;       

        if (Yii::$app->request->post()) {
            $modelConsulta = $this->guardarConsulta($arrayConfiguracion, $modelConsulta, $paciente);

            $modeloEmbarazo->load(Yii::$app->request->post());             
                $modeloEmbarazo->id_consulta = $modelConsulta->id_consulta;
                $modeloEmbarazo->id_persona = $paciente->id_persona;
                $modeloEmbarazo->save();                
           
           
            
            
            \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

            $urlSiguiente = $modelConsulta->urlSiguiente;
            if ($modelConsulta->urlSiguiente != 'fin') {
                $urlSiguiente = $modelConsulta->urlSiguiente.'?id_consulta='.$modelConsulta->id_consulta;
            }            
            return [
                'success' => 'Los Datos obstetricos fueron cargados exitosamente.', 
                'url_siguiente' => $urlSiguiente
            ];
        }

        return $this->render('../consultas/v2/_form_obstetricia', [
            'modelConsulta' => $modelConsulta,
            'modeloEmbarazo' => $modeloEmbarazo,
        ]);
    }

}
