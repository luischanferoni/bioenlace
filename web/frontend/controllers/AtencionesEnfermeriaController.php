<?php

namespace frontend\controllers;

use Yii;
use common\models\AtencionesEnfermeria;
//use common\models\busquedas\AtencionesEnfermeriaBusqueda;
use common\models\busquedas\PersonaBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\validators\NumberValidator;
use yii\validators\RequiredValidator;
use yii\validators\StringValidator;
use yii\web\Response;

/**
 * AtencionesEnfermeria implements the CRUD actions for Especialidades model.
 */
class AtencionesEnfermeriaController extends Controller
{
    public function behaviors()
    {
         // control de acceso mediante la extensión
        return [
           /*  'ghost-access' => [
                 'class' => 'webvimark\modules\UserManagement\components\GhostAccessControl',
             ],*/
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }



    /**
     * Displays a single AtencionesEnfermeria model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->renderAjax('/consulta-atenciones-enfermeria/view', [
            'model' => \common\models\Persona::findOne($id),
        ]);
    }

     
    public function actionGenerarReporte()
    {

        if (Yii::$app->request->post()) {
            $mes = Yii::$app->request->post('mes');
            $anio = Yii::$app->request->post('anio');
            return $this->redirect(['reporte', 'mes' => $mes, 'anio' => $anio]);
        } else {
            return $this->render('/consulta-atenciones-enfermeria/_form_reporte');
        }
    }

    public function actionReporte($mes, $anio)
    {
        $this->layout = 'imprimir';
        $fecha_inicio = date("$anio-$mes-01");
        
        $model = new AtencionesEnfermeria();
        return $this->render('/consulta-atenciones-enfermeria/reporte', [
            'resultados' => $model->informeCantidadesMensuales($fecha_inicio),
        ]);
    }   

}
