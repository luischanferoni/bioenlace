<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\VarDumper;
use yii\web\UnauthorizedHttpException;

use frontend\filters\SisseActionFilter;
use common\models\busquedas\RrhhEfectorBusqueda;
use common\models\RrhhEfector;
use common\models\RrhhServicio;
use common\models\RrhhLaboral;
use common\models\Agenda_rrhh;
use common\models\Persona;
use common\models\Servicio;
use common\models\ProfesionalSalud;
use common\models\FormularioDinamico;
use common\models\ServiciosEfector;

/**
 * RrhhEfectorController implements the CRUD actions for RrhhEfector model.
 */
class RrhhEfectorController extends Controller
{
    public $model;

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
                'except' => ['servicios-por-rrhh', 'profesionales-por-servicio-efector']
            ],
           'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                    'reactivar' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all RrhhEfector models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new RrhhEfectorBusqueda();

        if (!Yii::$app->user->getIdEfector()) {
            $searchModel->scenario = RrhhEfectorBusqueda::EFECTOR_SEARCH;
        }

        $searchModel->id_efector = Yii::$app->user->getIdEfector() ? Yii::$app->user->getIdEfector() : null;

        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single RrhhEfector model.
     * @param integer $id_rr_hh
     * @param integer $id_efector
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id_rr_hh)
    {
        $model = $this->findModel($id_rr_hh);

        if ($model->id_efector !== Yii::$app->user->getIdEfector()) {
            throw new UnauthorizedHttpException("No tiene autorizacion sobre este RRHH");
        }

        if (!is_null($model->deleted_at)) {
            return $this->render('view_reactivar', [
                'model' => $model,
            ]);
        }

        return $this->render('view', [
            'model' => $model,
        ]);
    }

    /**
     * Creates a new RrhhEfector model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($id_persona)
    {
        #$persona = Yii::$app->session['persona'];
        #$persona =  unserialize($persona);
        $persona = Persona::findOne($id_persona);
        if(!$persona) {
            throw new NotFoundHttpException('The person does not exist.');
        }

        $profesionalSalud = ProfesionalSalud::find()->where(['id_persona' => $id_persona]);

        // Esta variable va a ser para traer los servicios que podria 
        // brindar un personal de salud, va en true si de sisa hay resultados
        $conServiciosParaSalud = false;
       
        // El siguiente bloque comentado se aplica apenas tengamos el acceso a sisa 
        // para traer los datos de los profesionales de la salud
        
        /* if (!$profesionalSalud || $profesionalSalud->codigo_sisa == null) {
            $nombreCompleto = $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON);
            list($apellidoCompleto, $nombreCompleto) = explode(", ", $nombreCompleto);
            $profesionalSissa = Yii::$app->sisa->getProfesionalesDeSantiago($apellidoCompleto, $nombreCompleto, "", $persona->documento);
           
            if ($profesionalSissa->ok) {
                $conServiciosParaSalud = true;
                // var_dump($profesionalSissa);die;
                if (!$profesionalSalud) {
                    $profesionalSalud = new ProfesinalSalud();
                }
                $profesionalSalud->id_persona = $persona->id_persona;
                $profesionalSalud->codigo_sisa = $profesionalSissa->codigo;
                $profesionalSalud->matriculas_sisa = json_encode($profesionalSissa->matriculas);
                $profesionalSalud->save();
            }
        }*/

        // Lo siguiente es para tener un solo link en la vista, el create. 
        // En caso de que la persona ya este asignada a este efector redirigimos al update
        $rrhhEfector = RrhhEfector::find()->where(
                                [
                                    'id_efector' => Yii::$app->user->getIdEfector(), 
                                    'id_persona' => $id_persona
                                ]
                                )->one();
        
        if ($rrhhEfector != null) {

            if (!is_null($rrhhEfector->deleted_at)) {
            
                return $this->render('view_reactivar', [
                    'model' => $rrhhEfector,
                ]);
            }
            
            return $this->redirect(['rrhh-efector/update', 'id_rr_hh' => $rrhhEfector->id_rr_hh]);            
        }
        $rrhhEfector = new RrhhEfector();
        $modelosRrhhServicios = [new RrhhServicio()];
        $modelosRrhhCondicionesLaborales = [new RrhhLaboral()];
        $modelosAgendas = [new Agenda_rrhh()];

        if (Yii::$app->request->post()) {

            $modelosRrhhServicios = FormularioDinamico::createAndLoadMultiple(RrhhServicio::classname());
            $modelosAgendas = FormularioDinamico::createAndLoadMultiple(Agenda_rrhh::classname());
            $modelosRrhhCondicionesLaborales = FormularioDinamico::createAndLoadMultiple(RrhhLaboral::classname());
            
            $serviciosQuery = Servicio::find()
                ->select('servicios.id_servicio')
                ->innerJoin('servicios_efector', 
                    'servicios_efector.id_servicio = servicios.id_servicio AND servicios_efector.id_efector = '.Yii::$app->user->getIdEfector())
                ->where(['servicios.acepta_turnos' => 'NO'])
                ->asArray()
                ->all();
            $idsServiciosNoAceptaTurnos = ArrayHelper::getColumn($serviciosQuery, 'id_servicio');

            foreach($modelosRrhhServicios as $index => $modeloRrhhServicio) {
                if (in_array($modeloRrhhServicio->id_servicio, array_values($idsServiciosNoAceptaTurnos))) {
                    $modelosAgendas[$index]->formas_atencion = Agenda_rrhh::FORMA_ATENCION_SIN_ATENCION;
                }
            }

            // 1. Asociamos la persona al efector
            
            $rrhhEfector->id_efector = (int)Yii::$app->user->getIdEfector();
            $rrhhEfector->id_persona = $id_persona;

            $valid = $rrhhEfector->validate();
            $valid = FormularioDinamico::validateMultiple(
                    $modelosRrhhServicios, ['id_servicio']) && $valid;
            $valid = FormularioDinamico::validateMultiple(
                    $modelosAgendas, [
                        'cupo_pacientes',
                        'formas_atencion',
                        'lunes_2', 'martes_2', 'miercoles_2', 'jueves_2',
                        'viernes_2', 'sabado_2', 'domingo_2'
                    ]) && $valid;
            $valid = FormularioDinamico::validateMultiple(
                    $modelosRrhhCondicionesLaborales, [
                        'id_condicion_laboral',
                        'fecha_inicio',
                        'fecha_fin']) && $valid;
            $agenda_valid = Agenda_rrhh::validarGrupodeAgendas($modelosAgendas);
            if(!$agenda_valid) {
                $modelosRrhhServicios[0]->addError(
                    'id_servicio',
                    'La agenda tiene días repetidos para múltiples servicios.'
                );
            }
            $valid = $agenda_valid && $valid;
            if ($valid) {
                $transaction = \Yii::$app->db->beginTransaction();
                try {
                    if(!$rrhhEfector->save(false)) {
                        //$msg = 'Error al guardar entidad RRHHEfector.';
                        throw new Exception();
                    }
                    // 2. Para lo anterior, asociamos los servicios que
                    //  brinda en dicho efector
                    foreach ($modelosRrhhServicios as $i => $modeloRrhhServicio) {
                        $modeloRrhhServicio->id_rr_hh = $rrhhEfector->id_rr_hh;
                        if (!$modeloRrhhServicio->save()) {
                            //$msg = 'Error al guardar entidad RrhhServicio: '.$i;
                            throw new Exception();
                        }

                        // 3.1 Para cada servicio, creamos la agenda correspondinte                        
                        $modelosAgendas[$i]->id_rrhh_servicio_asignado = $modeloRrhhServicio->id;
                        if (!$modelosAgendas[$i]->save()) {
                           // $msg = 'Error al guardar entidad AgendaRRHH: '.$i;
                            throw new Exception();
                        }                        
                    }

                    // 4. Para cada rrhh_efector es necesario indicar la/las condiciones laborales
                    foreach ($modelosRrhhCondicionesLaborales as $i => $modeloRrhhCondicionLaboral) {
                        $modeloRrhhCondicionLaboral->id_rr_hh = $rrhhEfector->id_rr_hh;
                        if (!$modeloRrhhCondicionLaboral->save()) {
                           // $msg = 'Error al guardar entidad RrhhCondicionLaboral: '.$i;
                            throw new Exception();
                        }
                    }

                    $transaction->commit();
                    
                    return $this->redirect(['personas/view', 'id' => $id_persona]);
                
                } catch (Exception $e) {
                    $transaction->rollBack();
                    
                    if(count($modelosRrhhServicios) > 0) {
                       /* $modelosRrhhServicios[0]->addError(
                        'id_servicio',
                        $e->getMessage()
                        );*/
                    }
                    
                    // Para que muestre bien el formato de la fecha luego de un error
                    foreach ($modelosRrhhCondicionesLaborales as $i => $modeloRrhhCondicionLaboral) {
                        if (strpos($modeloRrhhCondicionLaboral->fecha_inicio, "-")) {
                            $modeloRrhhCondicionLaboral->fecha_inicio = Yii::$app->formatter->asDate($modeloRrhhCondicionLaboral->fecha_inicio, Yii::$app->formatter->dateFormat);
                        }

                        if (strpos($modeloRrhhCondicionLaboral->fecha_fin, "-")) {
                            $modeloRrhhCondicionLaboral->fecha_fin = Yii::$app->formatter->asDate($modeloRrhhCondicionLaboral->fecha_fin, Yii::$app->formatter->dateFormat);
                        }
                    }
                }
            }
        }

        return $this->render('create', [
            'modeloRrhhEfector' => $rrhhEfector,
            'modelosRrhhServicios' => $modelosRrhhServicios,
            'modelosRrhhCondicionesLaborales' => $modelosRrhhCondicionesLaborales,
            'modelosAgendas' => $modelosAgendas,
            'conServiciosParaSalud' => $conServiciosParaSalud
        ]);
    }

    /**
     * Updates an existing RrhhEfector model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id_rr_hh
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id_rr_hh)
    {
        $rrhhEfector = $this->findModel($id_rr_hh);
        if ($rrhhEfector->id_efector !== Yii::$app->user->getIdEfector()) {
            throw new UnauthorizedHttpException("No tiene autorizacion sobre este RRHH");
        }

        if (!is_null($rrhhEfector->deleted_at)) {
        
            return $this->render('view_reactivar', [
                'model' => $rrhhEfector,
            ]);
        }            

        $conServiciosParaSalud = false;

        // Cargo todos los modelos, loop para cada rrhhServicio, una o mas agendas

        $modelosRrhhServicios = [];
        $modelosAgendas = [];
        
        if ($rrhhEfector->rrhhServicio) {
            $rrhhServicios = $rrhhEfector->rrhhServicio;            
            
            foreach ($rrhhServicios as $modeloRrhhServicio) {
                // Admin Efector, el rrhh tiene el servicio de admin efector
                // asignado desde el backend, pero no pertenece al listado de servicio del efector
                // el listado de servicios que el admin efector dice que el efector ofrece                
                if ($modeloRrhhServicio->id_servicio == 62) {continue;}

                $modelosRrhhServicios[] = $modeloRrhhServicio;

                if (isset($modeloRrhhServicio->agenda)) {
                    $modelosAgendas[] = $modeloRrhhServicio->agenda;
                } else {
                    $modelosAgendas[] = new Agenda_rrhh();
                }
            }
        }

        if (count($modelosAgendas) == 0) {
            $modelosAgendas[] = new Agenda_rrhh();
        }

        if (count($modelosRrhhServicios) == 0){
            $modelosRrhhServicios = [new RrhhServicio()];
        }

        // Cargo los modelos de condiciones laborales

        if ($rrhhEfector->rrhhLaboral) {
            $modelosRrhhCondicionesLaborales = $rrhhEfector->rrhhLaboral;
        } else {
            $modelosRrhhCondicionesLaborales = [new RrhhLaboral()];
        }

        if (Yii::$app->request->post()) {

            $serviciosQuery = Servicio::find()
                ->select('servicios.id_servicio')
                ->innerJoin('servicios_efector', 
                    'servicios_efector.id_servicio = servicios.id_servicio AND servicios_efector.id_efector = '.Yii::$app->user->getIdEfector())
                ->where(['servicios.acepta_turnos' => 'NO'])
                ->asArray()
                ->all();
            $idsServiciosNoAceptaTurnos = ArrayHelper::getColumn($serviciosQuery, 'id_servicio');

            $idsViejosRrhhServicios = ArrayHelper::getColumn($modelosRrhhServicios, 'id');
            $idsViejosRrhhCondicionesLaborales = ArrayHelper::getColumn($modelosRrhhCondicionesLaborales, 'id');
            
            $modelosRrhhServicios = FormularioDinamico::createAndLoadMultiple(
                    RrhhServicio::classname(), 'id', $modelosRrhhServicios);
            $modelosAgendas = FormularioDinamico::createAndLoadMultiple(
                    Agenda_rrhh::classname(), 'id_agenda_rrhh', $modelosAgendas);
            $modelosRrhhCondicionesLaborales = FormularioDinamico::createAndLoadMultiple(
                    RrhhLaboral::classname(), 'id', $modelosRrhhCondicionesLaborales);
                    
            foreach($modelosRrhhServicios as $index => $modeloRrhhServicio) {
                if (in_array($modeloRrhhServicio->id_servicio, array_values($idsServiciosNoAceptaTurnos))) {
                    $modelosAgendas[$index]->formas_atencion = Agenda_rrhh::FORMA_ATENCION_SIN_ATENCION;
                }
            }

            //$valid = $rrhhEfector->validate();
            $valid = FormularioDinamico::validateMultiple(
                    $modelosRrhhServicios/*$servicios_avalidar*/, ['id_servicio']);
            $valid = FormularioDinamico::validateMultiple(
                    $modelosAgendas, [
                        'cupo_pacientes',
                        'formas_atencion',
                        'lunes_2', 'martes_2', 'miercoles_2', 'jueves_2',
                        'viernes_2', 'sabado_2', 'domingo_2'
                    ]) && $valid;
            $valid = FormularioDinamico::validateMultiple(
                    $modelosRrhhCondicionesLaborales, [
                        'id_condicion_laboral',
                        'fecha_inicio',
                        'fecha_fin']) && $valid;
            $agenda_valid = Agenda_rrhh::validarGrupodeAgendas($modelosAgendas);
            if(!$agenda_valid) {
                $modelosRrhhServicios[0]->addError(
                    'id_servicio',
                    'La agenda tiene días repetidos para múltiples servicios.'
                );
            }
            $valid = $agenda_valid && $valid;
            if($valid) {
                $transaction = \Yii::$app->db->beginTransaction();
                try {
                    //$modelosRrhhServicios termina siendo un combinado de objetos para crear y actualizar
                    $idsAgendasEliminar = [];

                    // 1. Los RRHH_Servicios
                    foreach ($modelosRrhhServicios as $i => $modeloRrhhServicio) {
                        $modeloRrhhServicio->id_rr_hh = $rrhhEfector->id_rr_hh;
                        if (!$modeloRrhhServicio->save()) {
                            $msg = 'Error al guardar entidad RrhhServicios: '.$i;
                            throw new Exception($msg);
                        }

                        // Si el servicio acepta turnos, se guarda la agenda, 
                        // con $i puedo acceder a la agenda que corresponde al servicio
                        $modelosAgendas[$i]->id_rrhh_servicio_asignado = $modeloRrhhServicio->id;
                        if (!$modelosAgendas[$i]->save()) {
                            //$msg = 'Error al guardar entidad Agenda_rrhh: '.$i;
                            throw new Exception();
                        }
                    }

                    // 2. Las condiciones laborales
                    foreach ($modelosRrhhCondicionesLaborales as $i => $modeloRrhhCondicionLaboral) {
                        $modeloRrhhCondicionLaboral->id_rr_hh = $rrhhEfector->id_rr_hh;
                        if (!$modeloRrhhCondicionLaboral->save()) {
                          // $msg = 'Error al guardar entidad RrhhCondicionLaboral: '.$i;
                           throw new Exception();
                        }
                    }

                    /* Se eliminan los servicios quitados  */
                    $idsaGuardarRrhhServicios = ArrayHelper::getColumn($modelosRrhhServicios, 'id');
                    $idsRrhhServiciosaEliminar = array_diff($idsViejosRrhhServicios, $idsaGuardarRrhhServicios);
                    if (count($idsRrhhServiciosaEliminar) > 0) {
                        // soft delete
                        RrhhServicio::deleteAll(['id' => $idsRrhhServiciosaEliminar]);
                        // soft delete
                        Agenda_rrhh::deleteAll(['id_rrhh_servicio_asignado' => $idsRrhhServiciosaEliminar]);
                    }

                    $idsaGuardarRrhhCondicionesLaborales = ArrayHelper::getColumn($modelosRrhhCondicionesLaborales, 'id');
                    $idsRrhhCondicionesLaboralesaEliminar = array_diff($idsViejosRrhhCondicionesLaborales, $idsaGuardarRrhhCondicionesLaborales);
                    if (count($idsRrhhCondicionesLaboralesaEliminar) > 0) {
                        // soft delete
                        RrhhLaboral::deleteAll(['id' => $idsRrhhCondicionesLaboralesaEliminar]);
                    }
                    /* -- */

                    $transaction->commit();
                    return $this->redirect(['rrhh-efector/index', 'id' => $rrhhEfector->id_persona]);

                } catch (Exception $e) {
                    $transaction->rollBack();
                    
                    if(count($modelosRrhhServicios) > 0) {
                      /*  $modelosRrhhServicios[0]->addError(
                        'id_servicio',
                        $e->getMessage()
                        );*/
                    }
                    foreach ($modelosRrhhCondicionesLaborales as $i => $modeloRrhhCondicionLaboral) {
                        if (strpos($modeloRrhhCondicionLaboral->fecha_inicio, "-")) {
                            $modeloRrhhCondicionLaboral->fecha_inicio = Yii::$app->formatter->asDate($modeloRrhhCondicionLaboral->fecha_inicio, Yii::$app->formatter->dateFormat);
                        }

                        if (strpos($modeloRrhhCondicionLaboral->fecha_fin, "-")) {
                            $modeloRrhhCondicionLaboral->fecha_fin = Yii::$app->formatter->asDate($modeloRrhhCondicionLaboral->fecha_fin, Yii::$app->formatter->dateFormat);
                        }
                    }
                }
            }
        }

        Yii::$app->params['title'] = 'Editar servicios de recurso humano "'.$rrhhEfector->persona->getNombrecompleto(Persona::FORMATO_NOMBRE_A_N_D).'"';

        return $this->render('update', [
            'modeloRrhhEfector' => $rrhhEfector,
            'modelosRrhhServicios' => $modelosRrhhServicios,
            'modelosRrhhCondicionesLaborales' => $modelosRrhhCondicionesLaborales,
            'modelosAgendas' => $modelosAgendas??[new Agenda_rrhh()],
            'conServiciosParaSalud' => $conServiciosParaSalud
        ]);
    }

    /**
     * Deletes an existing RrhhEfector model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id_rr_hh     
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id_rr_hh)
    {
        $model = $this->findModel($id_rr_hh);
        if ($model->id_efector !== Yii::$app->user->getIdEfector()) {
            throw new UnauthorizedHttpException("No tiene autorizacion sobre este RRHH");
        }

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            if (!$model->delete()) {
                throw new Exception(json_encode($model->getErrorSummary()));
            } 

            foreach ($model->rrhhServicio as $servicio) {
                if (!$servicio->delete()) {
                    throw new Exception(json_encode($servicio->getErrorSummary()));
                }                
            }

            $transaction->commit();
        } catch (Exception $e) {                    
            $transaction->rollBack();
            $error = json_decode($e->getMessage());

            return $this->asJson(['error' => true, 'msg' => $error]);
        }

        return $this->asJson(['error' => false, 'msg' => 'Quitado correctamente']);
    }

    /**
     * Vuelve deleted_at y deleted_by a null al rrhh y todos sus servicios
     */
    public function actionReactivar($id_rr_hh)
    {
        $model = $this->findModel($id_rr_hh);
        if ($model->id_efector !== Yii::$app->user->getIdEfector()) {
            throw new UnauthorizedHttpException("No tiene autorizacion sobre este RRHH");
        }

        if (is_null($model->deleted_at)) {
            return $this->asJson(['error' => true, 'msg' => 'El Recurso Humano ya se encuentra activo']);
        }

        $transaction = \Yii::$app->db->beginTransaction();
        try {

            $model->restore();

            foreach ($model->rrhhServiciosEliminados as $servicio) {                
                $servicio->restore();
            }

            $transaction->commit();
        } catch (Exception $e) {                    
            $transaction->rollBack();
            $error = json_decode($e->getMessage());

            return $this->asJson(['error' => true, 'msg' => $error]);
        }

        return $this->asJson(['error' => false, 'msg' => 'Recurso Humano activado correctamente']);
    }

    public function actionReactivarRrhhservicio($id_rr_hh_servicio)
    {
        $rrhhServicio = RrhhServicio::findOne(['id' => $id_rr_hh_servicio]);
        
        if ($rrhhServicio == null) {
            throw new NotFoundHttpException('El servicio para este RRHH no se encontró.');
        }

        if ($rrhhServicio->rrhhEfector->id_efector !== Yii::$app->user->getIdEfector()) {
            throw new UnauthorizedHttpException("No tiene autorizacion sobre este RRHH");
        }

        $rrhhServicio->restore();

        return $this->asJson(['error' => false, 'msg' => 'Servicio del RRHH activado correctamente']);
    }

    /**
     * Finds the RrhhEfector model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id_rr_hh
     * @param integer $id_efector
     * @return RrhhEfector the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id_rr_hh)
    {
        if (($model = RrhhEfector::findOne(['id_rr_hh' => $id_rr_hh])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionServiciosPorRrhh()
    {
        $idEfector = Yii::$app->request->post('idEfector');

        $rrhhEfector = RrhhEfector::find()
                    ->where([
                            'id_efector' => $idEfector, 
                            'id_persona' => Yii::$app->user->getIdPersona()
                            ])
                    ->one();

        $html = "";
        foreach ($rrhhEfector->rrhhServicio as $rrhhServicio) {

            $servicioEfector = ServiciosEfector::findActive()
            ->where([
                'id_efector' => $idEfector, 
                'id_servicio' => $rrhhServicio->id_servicio
                ])
            ->one();
            //Controla que el servicio esté activo en el efector o sea admin de efector
            if((isset($servicioEfector) && is_null($servicioEfector->deleted_at)) || $rrhhServicio->servicio->nombre == 'ADMINISTRAR EFECTOR')
            {
                $html .= '<input type="radio" name="servicio" class="btn-check" 
                            id="btn-check-servicio-'.$rrhhServicio->id_servicio.'" value="'.$rrhhServicio->id_servicio.'">
                    <label class="btn btn-soft-primary p-5" for="btn-check-servicio-'.$rrhhServicio->id_servicio.'">
                    <h3>'.$rrhhServicio->servicio->nombre.'</h3>
                    </label>';
            }
        }

        return $html;
        //$rrhhServicios = ArrayHelper::map($rrhh_efector->rrhhServicio, 'id_servicio', 'servicio.nombre');
        //Yii::$app->user->setServicios($rrhhServicios);

        //\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        //return ['error' => false, 'msg' => $rrhhServicios];
    }

    public function actionRrhhAutocomplete($q = null) {

        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        if (!is_null($q)) {
            $data = \common\models\RrhhEfector::obtenerProfesionalesParches($q);

            $out['results'] = array_values($data);
        }

        return $out;
    } 

    public function actionProfesionalesPorServicioEfector()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = [];

        if (isset($_POST['depdrop_parents'])) {
            $parents = $_POST['depdrop_parents'];
            if ($parents != null) {
                $idEfector = $parents[0];
                $idServicio = $parents[1];                
    
                $profesionales = RrhhEfector::obtenerMedicosPorServicioEfector($idEfector, $idServicio);
                $arrayEfectores = ArrayHelper::map($profesionales,'id_rr_hh', 'datos');

                foreach($arrayEfectores as $key => $value){
                    $out[] = ['id' => $key, 'name' => $value];
                }
              
                return ['output' => $out, 'selected' => ''];
            }
        }
        return ['output' => '', 'selected' => ''];
    }
}
