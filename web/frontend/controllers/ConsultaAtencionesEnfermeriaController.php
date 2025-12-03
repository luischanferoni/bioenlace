<?php

namespace frontend\controllers;

use frontend\controllers\DefaultController;

use Yii;
use yii\web\Controller;
use yii\base\Exception;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\validators\NumberValidator;
use yii\validators\RequiredValidator;
use yii\validators\StringValidator;
use yii\web\Response;
use yii\helpers\ArrayHelper;

use common\models\Consulta;
use common\models\ConsultaAtencionesEnfermeria;
use common\models\ConsultasConfiguracion;
use common\models\Persona;
use common\models\busquedas\PersonaBusqueda;
use common\models\Turno;
use common\models\ConsultaDerivaciones;
use common\models\ConsultaBalanceHidrico;
use common\models\ConsultaPracticasOftalmologia;
use common\models\FormularioDinamico;


use frontend\filters\SisseActionFilter;
use frontend\filters\SisseConsultaFilter;


class ConsultaAtencionesEnfermeriaController extends DefaultController
{
    use \frontend\controllers\traits\ConsultaTrait;

    public $urlAnterior;
    public $urlActual;
    public $urlSiguiente;

    public $modelConsulta;

    public function behaviors()
    {
        // control de acceso mediante la extensión
        return [

            'ghost-access' => [
                 'class' => 'frontend\components\SisseGhostAccessControl',
             ],
            'access' => [
                'class' => SisseActionFilter::className(),
                'only' => ['index', 'espera'],
                'filtrosExtra' => [SisseActionFilter::FILTRO_PACIENTE, SisseActionFilter::FILTRO_RECURSO_HUMANO],
            ],
          /* 'consulta-access' => [
                'class' => SisseConsultaFilter::className(),
                'only' => ['create'],
            ],       */
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Lists all Especialidades models.
     * @return mixed
     */
    public function actionIndex()
    {
        /*$searchModel = new AtencionesEnfermeriaBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);*/
        $searchModel = new PersonaBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single AtencionesEnfermeria model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => \common\models\Persona::findOne($id),
        ]);
    }


    public function createCore($modelConsulta)
    {

        $id_efector = UserRequest::requireUserParam('idEfector');

        if($id_efector == 174 && (UserRequest::requireUserParam('encounterClass') == Consulta::ENCOUNTER_CLASS_AMB)){ //HOSPITAL DEMARIA
            return self::createOftalmologia($modelConsulta);
        }else{
            return self::create($modelConsulta);
        }
    }


    /**
     * Creates a new AtencionesEnfermeria model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function create($modelConsulta)
    {

        $modelAtencionEnfermeria = $modelConsulta->atencionEnfermeria;

        if (!$modelAtencionEnfermeria) {
            $modelAtencionEnfermeria = new ConsultaAtencionesEnfermeria();
        }
        
        $modelAtencionEnfermeria->fecha_creacion = date("d/m/Y");
        $modelAtencionEnfermeria->id_rr_hh = Yii::$app->user->getIdRecursoHumano();        

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();

            $observaciones = Yii::$app->request->post('observaciones');
            $fecha_creacion = isset($post['ConsultaAtencionesEnfermeria']['fecha_creacion']) ? $post['ConsultaAtencionesEnfermeria']['fecha_creacion'] : ''; //Yii::$app->request->post('fecha_creacion');
            $hora_creacion = $post['ConsultaAtencionesEnfermeria']['hora_creacion'];

            unset($post['_csrf-frontend']);
            unset($post['observaciones']);
            unset($post['ConsultaAtencionesEnfermeria']);
            unset($post['hour']);
            unset($post['minute']);

            foreach ($post as $campo => $valor) {
                if (($valor=='')) {
                    unset($post[$campo]);
                } else {

                    if (is_array($valor)) {
                        foreach ($valor as $campo1 => $valor1) {
                            if (($valor1 == '')) {
                                unset($post[$campo][$campo1]);
                            }
                        }

                        if (count($post[$campo]) == 0) {
                            unset($post[$campo]);
                        }
                    }
                }
            }

            if (count($post) == 0) {
                $post = '';
            }

            $codificado = json_encode($post);

            $modelAtencionEnfermeria->datos = $codificado;
            $modelAtencionEnfermeria->observaciones = $observaciones;
            $modelAtencionEnfermeria->fecha_creacion = $fecha_creacion;
            $modelAtencionEnfermeria->hora_creacion = $hora_creacion;

            $transaction = \Yii::$app->db->beginTransaction();

            try {
                
                $modelConsulta->save();

                $modelAtencionEnfermeria->id_persona = $modelConsulta->id_persona;
                $modelAtencionEnfermeria->id_consulta = $modelConsulta->id_consulta;
                if (!$modelAtencionEnfermeria->save()) {                    
                    throw new Exception();
                    //var_dump($modelAtencionEnfermeria->getErrors());die;
                }

                $transaction->commit();
                
            } catch (\Exception $th) {
                if ($th->getMessage() != "") {
                    Yii::error($th->getMessage());
                } 

                $transaction->rollBack();
                
                return $this->renderAjax('_form', [
                    'modelConsulta' => $modelConsulta,
                    'model' => $modelAtencionEnfermeria
                ]);
            }

            return [
                'success' => true, 
                'msg' => 'La atención de enfermería fue cargada exitosamente.',
                'url_siguiente' => $modelConsulta->urlSiguiente
            ];
        }

        return $this->renderAjax('_form', [
            'modelConsulta' => $modelConsulta,
            'model' => $modelAtencionEnfermeria
        ]);

    }

       /**
     * Creates a new ConsultaPracticasOftalmologia model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function createOftalmologia($modelConsulta, $form_steps=true)
    {
        $oftalmologia_ids = [];
        $oftalmologias = $modelConsulta->oftalmologias;        

        if(!$oftalmologias) {
            $oftalmologias = [new ConsultaPracticasOftalmologia()];
        } else {
            $oftalmologia_ids = ArrayHelper::getColumn($oftalmologias, 'id');
        }

        if (Yii::$app->request->post()) {
            

            $oftalmologias = FormularioDinamico::createAndLoadMultiple(
                ConsultaPracticasOftalmologia::classname(),
                'id',
                $oftalmologias);

                $transaction = \Yii::$app->db->beginTransaction();
                try {
                    $modelConsulta->save();

                    foreach ($oftalmologias as $i => $oftalmologia) {
                        if(Yii::$app->request->get('no-copera') == 'on'):
                            $oftalmologia->resultado = 'no-copera';
                        endif;
                        $oftalmologia->id_consulta = $modelConsulta->id_consulta;
                        $oftalmologia->codigo = '16830007';
                        if (!$oftalmologia->save()) {
                            $msg = 'Error al guardar entidad ConsultaPracticasOftalmologia: '.$i;
                            throw new Exception($msg);
                        }
                    }
                    $oftalmologia_ids_guardar = ArrayHelper::getColumn($oftalmologias, 'id');
                    $oftalmologia_ids_eliminar = array_diff($oftalmologia_ids, $oftalmologia_ids_guardar);
                    if (count($oftalmologia_ids_eliminar) > 0) {
                        // hard delete, hardDeleteGrupo verifica que $oftalmologia_ids_eliminar no sea vacio
                        ConsultaPracticasOftalmologia::hardDeleteGrupo($modelConsulta->id_consulta, $oftalmologia_ids_eliminar);                        
                    }
                    
                    $transaction->commit();
                    return [
                        'success' => true,
                        'msg' => 'Los resultados fueron cargados exitosamente.',
                        'url_siguiente' => $modelConsulta->urlSiguiente.'?id_consulta='.$modelConsulta->id_consulta
                    ];

                } catch (Exception $e) {
                    $transaction->rollBack();
                }
            }


        $context = [
            'modelConsulta' => $modelConsulta,
            'oftalmologias' => $oftalmologias,
            'form_steps' => $form_steps,
        ];
        return $this->renderAjax('/consulta-practicas-oftalmologia-enfermeria/create', $context);

    }

    /**
     * Updates an existing Especialidades model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id_especialidad]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    public function actionGenerarReporte()
    {

        if (Yii::$app->request->post()) {
            $mes = Yii::$app->request->post('mes');
            $anio = Yii::$app->request->post('anio');
            return $this->redirect(['reporte', 'mes' => $mes, 'anio' => $anio]);
        } else {
            return $this->render('_form_reporte');
        }
    }

    public function actionReporte($mes, $anio)
    {
        $this->layout = 'imprimir';
        $fecha_inicio = date("$anio-$mes-01");
        $model = new AtencionesEnfermeria();
        return $this->render('reporte', [
            'resultados' => $model->informeCantidadesMensuales($fecha_inicio),
        ]);
    }

    /**
     * Deletes an existing Especialidades model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $atencion_enfermeria = $this->findModel($id);
        $atencion_enfermeria->delete();

        return $this->redirect(['view', 'id' => $atencion_enfermeria->persona->id_persona]);
    }

    /**
     * Finds the Especialidades model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Especialidades the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = AtencionesEnfermeria::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

  /*  private function crearUrlParaAtencion($paciente)
    {
        $idconfiguracion = 0;
        $paso = 0;               
        
        if (Yii::$app->request->get('id_consulta') != "" && Yii::$app->request->get('id_consulta') != null) {
            $modelConsulta = Consulta::findOne($idConsulta);
            $idconfiguracion = $modelConsulta->id_configuracion;
            $paso = $modelConsulta->paso_completado + 1;
            list($urlAnterior, $urlActual, $urlSiguiente) = ConsultasConfiguracion::getUrlPorIdConfiguracion($idconfiguracion, $paso);

            return [$modelConsulta, $urlAnterior, $urlSiguiente];
        }
        
        $idServicioRrhh = Yii::$app->request->get('id_servicio_rr_hh');
        if ($idServicioRrhh == "" || $idServicioRrhh == null) {
            $idServicioRrhh = 0;
        }

        $encounterClass = Yii::$app->request->get('encounter_class');
        if ($encounterClass == "" || $encounterClass == null) {        
            $encounterClass = '';
        }

        list($urlAnterior, $urlActual, $urlSiguiente, $parametrosExtra) = Consulta::calcularUrl($paciente, $idServicioRrhh, $encounterClass);
        return [new Consulta(), null, $urlSiguiente];        
    }*/

}
