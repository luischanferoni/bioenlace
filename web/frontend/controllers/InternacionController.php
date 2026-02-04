<?php

namespace frontend\controllers;

use Yii;
use yii\base\Exception;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\helpers\ArrayHelper;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\filters\AccessRule;

use common\models\SegNivelInternacion;
use common\models\SegNivelInternacionRepository;
use common\models\busquedas\SegNivelInternacionBusqueda;
use common\models\InfraestructuraPiso;
use common\models\InfraestructuraCama;
use common\models\Persona;
use common\models\Setup;
use common\models\CoberturaMedica;
use common\models\Efector;
use common\models\Servicio;

use frontend\filters\SisseActionFilter;
use frontend\controllers\MpiApiController;
use common\models\Telefono;
use frontend\components\CPacienteHistorial;
use frontend\components\PacienteHistorial;
use common\models\ConsultasConfiguracion;
use common\models\Consulta;
use common\models\RrhhServicio;
use webvimark\modules\UserManagement\models\User;
use common\models\DiagnosticoConsultaRepository as DCRepo;

/**
 * InternacionController implements the CRUD actions for SegNivelInternacion model.
 */
class InternacionController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
            ],
            'access' => [
                'class' => SisseActionFilter::className(),
                'only' => ['index', 'espera'],
                'filtrosExtra' => [SisseActionFilter::FILTRO_PACIENTE],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all SegNivelInternacion models.
     * @return mixed
     */
    public function actionIndex()
    {

        $persona = Yii::$app->session['persona'];
        $persona =  unserialize($persona);
        $idPersonaEnSesion = (isset($persona->id_persona)) ? $persona->id_persona : null;

        $id = Yii::$app->request->get('id');

        if (isset($id) && $idPersonaEnSesion != $id) {
            $model_persona = persona::findOne(Yii::$app->request->get('id'));
            $model_persona->establecerEstadoPaciente();
            $persona = $model_persona;
            $session = Yii::$app->getSession();
            $session->set('persona', serialize($model_persona));
        }

        $pacienteInternado = false;

        if (isset($persona->id_persona) && SegNivelInternacion::personaInternada($persona->id_persona)) {

            $pacienteInternado = true;
        }

        $pisos = new InfraestructuraPiso();
        $efector = Yii::$app->user->getIdEfector();


        $pisos_efector = $pisos->pisosPorEfector($efector);
        return $this->render('index', [
            'pisos_efector' => $pisos_efector,
            'pacienteInternado' => $pacienteInternado
        ]);
    }

    /**
     * Rondas all SegNivelInternacion models.
     * @return mixed
     */
    public function actionRonda()
    {
        $persona = Yii::$app->session['persona'];
        $persona =  unserialize($persona);
        $pacienteInternado = false;

        if (isset($persona->id_persona) && SegNivelInternacion::personaInternada($persona->id_persona)) {

            $pacienteInternado = true;
        }

        $pisos = new InfraestructuraPiso();
        $efector = Yii::$app->user->getIdEfector();


        $pisos_efector = $pisos->pisosPorEfector($efector);
        return $this->render('ronda', [
            'pisos_efector' => $pisos_efector

        ]);
    }

    public function formatearDatosProfesional($modeloRrhh)
    {
        $array_profesiones = [];
        if (isset($modeloRrhh->persona->profesionalSalud)) {
            foreach ($modeloRrhh->persona->profesionalSalud as $profesional) {
                if (isset($profesional->especialidad)) {
                    $array_profesiones[$profesional->profesion->nombre][] = $profesional->especialidad->nombre;
                } else {
                    $array_profesiones[$profesional->profesion->nombre] = [];
                }
            }
        }
        return $array_profesiones;
    }

    /**
     * Displays a single SegNivelInternacion model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        $persona = Yii::$app->session['persona'];
        $persona =  unserialize($persona);
        $idPersona = (isset($persona->id_persona)) ? $persona->id_persona : null;

        $model = $this->findModel($id);

        $atenciones = $model->atenciones;
        $balances_list = SegNivelInternacionRepository::getBalancesHidricos($model);
        $regimenes_list = SegNivelInternacionRepository::getRegimenes($model);
        $diagnosticos = DCRepo::getDiagnosticosPersonaIMP($model);
        
        foreach ($atenciones as $key => $atencion) {
            # agrupar evoluciones
            if ($atencion->consultaEvolucion) {
                $evoluciones[] = $atencion->consultaEvolucion;
            }

            # agrupar SINTOMAS
            if ($atencion->consultaSintomas) {
                $sintomas[] = $atencion->consultaSintomas;
            }

            # agrupar DIAGNOSTICOS
            #if($atencion->diagnosticoConsultas){
            #    $diagnosticos[] = $atencion->diagnosticoConsultas;
            #}

            # agrupar Medicamentos
            if ($atencion->consultaMedicamentos) {
                $medicamentos[] = $atencion->consultaMedicamentos;
            }

            # agrupar PRACTICAS REALIZADAS
            if ($atencion->consultaPracticas) {
                $practicas[] = $atencion->consultaPracticas;
            }

            # agrupar ATENCIONES ENFERMERIA
            if ($atencion->atencionEnfermeria) {
                $atencionEnfermeria[] = $atencion->atencionEnfermeria;
            }

            # agrupar PRACTICAS DE OFTALMOLOGIA
            if ($atencion->oftalmologias) {
                $oftalmologia[] = $atencion->oftalmologias;
            }
        }

        if ($idPersona != $model->id_persona) {
            $model_persona = Persona::findOne($model->id_persona);
            //$model_persona->establecerEstadoPaciente();

            $session = Yii::$app->getSession();
            $session->set('persona', serialize($model_persona));
        }

        $paciente = $model->paciente;

        $model_rrhh = new RrhhServicio();
        $model_rrhh = $model_rrhh->findOne($model->id_rrhh);
        $datosProfesional = $this->formatearDatosProfesional($model_rrhh);

        $puedeAtender = false;
        $servicioProfesionalSesion = Yii::$app->user->getServicioActual();

        if(Servicio::puedeAtender($servicioProfesionalSesion)){
            $puedeAtender = true;
        }


        $urlSiguiente = Consulta::armarUrlAConsultaDesdeParent(Consulta::PARENT_INTERNACION, $model->id, '', $model->id_persona);

        return $this->render('view', [
            'model' => $model,
            'model_rrhh' => $model_rrhh,
            'datosProfesional' => $datosProfesional,
            'evoluciones' => empty($evoluciones) ? [] : $evoluciones,
            'sintomas' => empty($sintomas) ? [] : $sintomas,
            'diagnosticos' => empty($diagnosticos) ? [] : $diagnosticos,
            'medicamentos' => empty($medicamentos) ? [] : $medicamentos,
            'practicas' => empty($practicas) ? [] : $practicas,
            'atencionEnfermeria' => empty($atencionEnfermeria) ? [] : $atencionEnfermeria,
            'oftalmologias' => empty($oftalmologia) ? [] : $oftalmologia,
            'balances_list' => $balances_list,
            'regimenes_list' => $regimenes_list,
            'type' =>  Yii::$app->getRequest()->getQueryParam('type'),
            'urlSiguiente' => $urlSiguiente,
            'puedeAtender' => $puedeAtender
        ]);
    }



    /*
    private function crearUrlParaAtencion($paciente)
    {
        $idconfiguracion = 0;
        $paso = 0;               
        
        if (Yii::$app->request->get('id_consulta') != "" && Yii::$app->request->get('id_consulta') != null) {
            $modelConsulta = Consulta::findOne($idConsulta);
            $idconfiguracion = $modelConsulta->id_configuracion;
            $paso = $modelConsulta->paso_completado + 1;
            list($urlAnterior, $urlActual, $urlSiguiente) = ConsultasConfiguracion::getUrlPorIdConfiguracion($idconfiguracion, $paso);

            if ($urlActual == null) {
                Yii::error('Configuracion de consulta no encontrada: id_configuracion('.$id_configuracion.'), id_consulta('.$id_consulta.')');
                return 'error';
            }
            return $urlActual.'?id_consulta='.$modelConsulta->id_consulta;
        }
        
        $idServicioRrhh = Yii::$app->request->get('id_servicio_rr_hh');
        if ($idServicioRrhh == "" || $idServicioRrhh == null) {
            $idServicioRrhh = 0;
        }

        $encounterClass = ConsultasConfiguracion::ENCOUNTER_CLASS_IMP;
         

        list($idConfiguracion, $urlAnterior, $urlActual, $urlSiguiente, $parametrosExtra) = Consulta::calcularUrl($paciente, $idServicioRrhh, $encounterClass);
        if ($urlActual == null) {
            Yii::error('Configuracion de consulta no encontrada: id_servicio('.$idServicioRrhh.'), encounter_class('.$encounterClass.')');
            return 'error';
        }
        
        return $urlActual == null ? null : $urlActual.$parametrosExtra;
    }
*/
    /**
     * Creates a new SegNivelInternacion model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $persona = Yii::$app->session['persona'];
        $persona =  unserialize($persona);


        //Solicitamos coberturas activas para el paciente seleccionado.
        $coberturas_api = [];
        $cobertura_medica_key = sprintf("cobertura_medica_%s", $persona->id_persona);
        // Yii::$app->session->remove($cobertura_medica_key); // Para debug
        if (! Yii::$app->session->has($cobertura_medica_key)) {
            $mpi = new MpiApiController;
            $sexo_map = ['m' => 0, 'f' => 1];
            $persona_sexo = ArrayHelper::getValue($sexo_map, strtolower($persona->sexo), 0);
            $coberturas_api = $mpi->get_cobertura_social($persona->documento, $persona_sexo);
            Yii::$app->session->set($cobertura_medica_key, $coberturas_api);
        } else {
            $coberturas_api = Yii::$app->session->get($cobertura_medica_key);
        }
        $filtro_cobertura = Null;
        $coberturas_count = count($coberturas_api);
        $cobertura_default = Null;
        if ($coberturas_count > 0) {
            $filtro_cobertura = ArrayHelper::getColumn($coberturas_api, 'codigo');
            if ($coberturas_count == 1)
                $cobertura_default = $filtro_cobertura[0];
        }
        $coberturas = CoberturaMedica::getCoberturasForSelect($filtro_cobertura);
        $coberturas = ArrayHelper::map($coberturas, 'codigo', 'nombre');

        $model = new SegNivelInternacion();
        $model->fecha_inicio = date("d/m/Y");
        $model_cama = new InfraestructuraCama();
        $telefono = new Telefono();
        $get = Yii::$app->request->get();

        if (isset($get['id'])) {
            $model_cama = $model_cama->findOne($get['id']);
            $model->id_cama = $model_cama->id;
        }

        if ($cobertura_default !== null) {
            $model->obra_social = $cobertura_default;
        }

        if ($model->load(Yii::$app->request->post())) {

            $telefono->load(Yii::$app->request->post());

            $model->datos_contacto_tel = '';

            if ($model->ingresa_con == 'familiar' || $model->ingresa_con == 'otro' || $model->ingresa_con == 'policia') {

                $telefono->scenario = Telefono::VALIDAR_TELEFONO;
                $validarTelefono = $telefono->validate();

                if ($validarTelefono) {
                    $model->datos_contacto_tel = $telefono->prefijo . $telefono->codArea . $telefono->numTelefono;
                }
            }

            $model->scenario = SegNivelInternacion::INGRESO_PACIENTE;
            $model_cama->estado = 'ocupada';

            $validar = $model->validate();
            $validar = $validar && $model_cama->validate();


            if ($validar) {
                $transaction = \Yii::$app->db->beginTransaction();
                try {
                    if ($flag = $model->save()) {

                        if (!($flag = $model_cama->save())) {
                            $transaction->rollBack();
                            exit();
                        }
                        SegNivelInternacionRepository::doAgregarHistoriaCama(
                            $model
                        );
                        if ($flag) {
                            $transaction->commit();

                            return $this->redirect(['view', 'id' => $model->id]);
                        }
                    }
                } catch (Exception $e) {
                    // var_dump($e->getMessage());die;
                    $model->addError('*', "Error de BD, comuniquese con los administradores de SISSE");
                    $transaction->rollBack();
                }
            }
        }

        $efectores = Efector::find()->all();

        return $this->render('create', [
            'model' => $model,
            'model_cama' => $model_cama,
            'persona' => $persona,
            'telefono' => $telefono,
            'coberturas' => $coberturas,
            'efectores' => $efectores
        ]);
    }

    /**
     * Updates an existing SegNivelInternacion model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        /*
        if (!$model->load(Yii::$app->request->post())) {
            return $this->renderAjax('update', [
                'model' => $model,
            ]);
        }
        */

        $model->fecha_fin = date("d/m/Y");

        if ($this->request->isPost) {
            $model->load($this->request->post());
            $model->scenario = SegNivelInternacion::EGRESO_PACIENTE;
            $validar = $model->validate();
            if ($validar) {
                try {
                    SegNivelInternacionRepository::doExternacion($model);
                    return $this->redirect(['porpersona', 'idpersona' => $model->id_persona]);
                } catch (Exception $e) {
                    $model->addError('hora_fin', 'Ocurrió un error inesperado');
                }
            }
        }
        $context = [
            'model' => $model,
            'modal_id' => '',
        ];
        if ($this->request->isAjax) {
            $context['modal_id'] = '#modal_internacion_alta';
            return $this->renderAjax('update', $context);
        }
        return $this->render('update', $context);
    }

    /**
     * Deletes an existing SegNivelInternacion model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the SegNivelInternacion model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return SegNivelInternacion the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = SegNivelInternacion::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    /**
     * Lists all SegNivelInternacion models.
     * @return mixed
     */
    public function actionFinalizadas()
    {
        $searchModel = new SegNivelInternacionBusqueda();
        $dataProvider = $searchModel->searchFinalizadas(Yii::$app->request->queryParams);
        $efector = Yii::$app->user->getIdEfector();

        return $this->render('finalizadas', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider
        ]);
    }

    /**
     * Lists SegNivelInternacion por persona models.
     * @return mixed
     */
    public function actionPorpersona($idpersona)
    {
        if (!$idpersona) return $this->redirect(['/personas/view', 'id' => $idpersona]);
        $model_persona = Persona::findOne($idpersona);
        if (!$model_persona)  return $this->redirect(['/personas/view', 'id' => $idpersona]);
        $searchModel = new SegNivelInternacionBusqueda();
        $dataProvider = $searchModel->searchPorPersona(Yii::$app->request->queryParams, $idpersona);
        $efector = Yii::$app->user->getIdEfector();

        return $this->render('porpersona', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'persona' => $model_persona
        ]);
    }


    public function actionMostrarDatosAcompaniante($id_internacion)
    {
        $model = $this->findModel($id_internacion);

        return $this->renderAjax('_datosAcompaniante', [
            'model' => $model
        ]);
    }

    public function actionListado()
    {
        $persona = Yii::$app->session['persona'];
        $persona =  unserialize($persona);

        $pacienteInternado = false;

        $pisos = new InfraestructuraPiso();
        $idEfector = Yii::$app->user->getIdEfector();

        $pisos_efector = $pisos->pisosPorEfector($idEfector);

        return $this->render('listado', [
            'pisos_efector' => $pisos_efector,
        ]);
    }
}
