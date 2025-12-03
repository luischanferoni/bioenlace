<?php

namespace frontend\controllers;

use Yii;
use common\models\ConsultaSuministroMedicamento;
use common\models\ConsultaMedicamentos;
use common\models\busquedas\ConsultaSuministroMedicamentoBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\filters\AccessRule;
use common\models\Setup;
use common\models\FormularioDinamico;

/**
 * InternacionSuministroMedicamentoController implements the CRUD actions for SuministroMedicamento model.
 */
class ConsultaInternacionSuministroMedicamentoController extends Controller
{
    use \frontend\controllers\traits\ConsultaTrait;
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
            ],           
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ]
        ];
    }

    /**
     * Lists all SegNivelInternacionMedicamento models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new ConsultaSuministroMedicamentoBusqueda();
        $get = Yii::$app->request->get();

        if(isset($get['idi'])){
            $id_internacion = $get['idi'];
            $searchModel->id_internacion =  $id_internacion;
        } else {
            return $this->redirect(['/internacion']);
        }

        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'id_internacion'=> $id_internacion
        ]);
    }

    /**
     * Displays a single SuministroMedicamento model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new SuministroMedicamento model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $session = Yii::$app->getSession();
        $paciente = unserialize($session->get('persona'));

        $modelConsulta = Consulta::getModeloConsulta(Yii::$app->request->get('id_consulta'), $paciente, Yii::$app->request->get('parent'), Yii::$app->request->get('parent_id'));
        
        $id_internacion = isset($modelConsulta->parent_id)?$modelConsulta->parent_id:Yii::$app->request->get('parent_id');

        $medicamentosInternacion = ConsultaMedicamentos::getMedicamentos(['id_internacion' => $id_internacion]);

        $modelosConsultaSuministros = $modelConsulta->consultaSuministroMedicamento;
        
        if (Yii::$app->request->post()) {

            $modelosConsultaSuministros = FormularioDinamico::createAndLoadMultiple(ConsultaSuministroMedicamento::classname(), 'id', $modelosConsultaSuministros);
            $transaction = \Yii::$app->db->beginTransaction();

            try {
                if(!empty($modelosConsultaSuministros)){
                    $modelConsulta = $this->guardarConsulta($arrayConfiguracion, $modelConsulta, $paciente);

                    foreach ($modelosConsultaSuministros as $i => $modelConsultaSuministro) {
                    
                        $modelConsultaSuministro->id_consulta = $modelConsulta->id_consulta;
                        if (!$modelConsultaSuministro->save()) {
                            throw new Exception();
                        }
                    }

                } else {
                    $modelConsulta = $this->guardarConsulta($arrayConfiguracion, $modelConsulta, $paciente);

                    \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

                    $urlSiguiente = $modelConsulta->urlSiguiente;
                    if ($modelConsulta->urlSiguiente != 'fin') {
                        $urlSiguiente = $modelConsulta->urlSiguiente . '?id_consulta=' . $modelConsulta->id_consulta;
                    }
                    return [
                        'success' => 'Los suministros No fueron cargados.',
                        'url_siguiente' => $urlSiguiente
                    ];
                }
               /* if(isset($post['med'])){
                    $fecha = Setup::convert($model->fecha);
                    $observacion = $model->observacion;
                    $hora = date('h:i:s', strtotime($model->hora));
                    foreach($post['med'] as $m){
                        $model = new ConsultaSuministroMedicamento();
                        $model->fecha = $fecha;
                        $model->hora  = $hora;      
                        $model->id_consulta =  $modelConsulta->id_consulta;
                        $model->id_internacion_medicamento = $m;
                        $model->observacion = $observacion;
                        if (!$modeloSuministro->save()) {
                            var_dump($modeloSuministro->getErrors());die;
                            throw new Exception();
                        }  
                    }                    
                } else {
                    $modelConsulta = $this->guardarConsulta($arrayConfiguracion, $modelConsulta, $paciente);

                    \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

                    $urlSiguiente = $arrayUrlsConsulta['url_siguiente'];
                    if ($arrayUrlsConsulta['url_siguiente'] != 'fin') {
                        $urlSiguiente = $arrayUrlsConsulta['url_siguiente'] . '?id_consulta=' . $modelConsulta->id_consulta;
                    }
                    return [
                        'success' => 'Los suministros No fueron cargados.',
                        'url_siguiente' => $urlSiguiente
                    ];
                }*/

        } catch (\Exception $th) {
            if ($th->getMessage() != "") {
                Yii::error($th->getMessage());
            }

            $transaction->rollBack();

            return $this->render('../consultas/v2/_form_suministros', [
                'modelConsulta' => $modelConsulta,
                'medicamentosInternacion' => $medicamentosInternacion,
                'modelosConsultaSuministros' => (empty($modelosConsultaSuministros)) ? [new ConsultaSuministroMedicamento] : $modelosConsultaSuministros
            ]);
        }

        $transaction->commit();

            \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

            $urlSiguiente = $modelConsulta->urlSiguiente;
            if ($modelConsulta->urlSiguiente != 'fin') {
                $urlSiguiente = $modelConsulta->urlSiguiente.'?id_consulta='.$modelConsulta->id_consulta;
            }
                       
            return [
                'success' => 'Los suministros fueron cargados exitosamente.', 
                'url_siguiente' => $urlSiguiente
            ];


        }

        return $this->render('../consultas/v2/_form_suministros', [
            'modelConsulta' => $modelConsulta,
            'medicamentosInternacion' => $medicamentosInternacion,
            'modelosConsultaSuministros' => (empty($modelosConsultaSuministros)) ? [new ConsultaSuministroMedicamento] : $modelosConsultaSuministros
        ]);
           
        
       
    }

 

    /**
     * Finds the SegNivelInternacionMedicamento model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return SegNivelInternacionMedicamento the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = ConsultaSuministroMedicamento::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }


}
