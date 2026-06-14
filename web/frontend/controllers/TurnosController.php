<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use yii\filters\VerbFilter;

use common\models\busquedas\TurnoLibreBusqueda;
use common\models\busquedas\TurnoBusqueda;
use common\models\Scheduling\Turno;
use common\models\AgendaFeriados;
use common\models\ProfesionalEfectorServicio;
use common\models\ServiciosEfector;
use common\models\ConsultaDerivaciones;
use common\models\Person\Persona;

/**
 * TurnosController implements the CRUD actions for Turno model.
 */
class TurnosController extends Controller
{

    /** Verbs por acción; la API los usa al mapear. */
    public static $verbs = [
        'index' => ['GET', 'HEAD', 'OPTIONS'],
        'view' => ['GET', 'HEAD', 'OPTIONS'],
        'update' => ['PUT', 'PATCH', 'OPTIONS'],
        'espera' => ['GET', 'HEAD', 'OPTIONS'],
        'como-paciente' => ['GET', 'OPTIONS'],
        'proximo-disponible' => ['GET', 'POST', 'OPTIONS'],
        'reprogramar' => ['GET', 'HEAD', 'OPTIONS'],
    ];

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                ],
            ],
        ];
    }

    /**
     * Listar turnos de un paciente
     * @entity Turnos
     * @tags turno,cita,listar,ver,agenda
     * @keywords listar,ver turnos,citas,agenda
     * @synonyms turno,cita,agenda,reserva
     * @no_intent_catalog
    */
    public function actionIndex()
    {
        $session = Yii::$app->getSession();
        $session_persona = unserialize($session['persona']);
        $idPersonaEnSesion = (isset($session_persona->id_persona)) ? $session_persona->id_persona : null;
        $id = Yii::$app->request->get('id') ? Yii::$app->request->get('id') : null;
        if ($idPersonaEnSesion == null and $id == null) {
            return $this->redirect(['personas/buscar-persona']);
        }

        #$id = Yii::$app->request->get('id');
        $id_servicio_practica = Yii::$app->request->get('id_servicio');

        if (isset($id) && $idPersonaEnSesion != $id) {
            $model_persona = Persona::findOne($id);
            $model_persona->establecerEstadoPaciente();
            $session_persona = $model_persona;
            $session = Yii::$app->getSession();
            $session->set('persona', serialize($model_persona));
            $idPersonaEnSesion = $id;
        }
        if ($id_servicio_practica):
            $session = Yii::$app->getSession();
            $session->set('id_servicio_practica', $id_servicio_practica);
        else:
            $session = Yii::$app->getSession();
            $session->set('id_servicio_practica', null);
        endif;

        if (!$session_persona) {
            $model_persona = new Persona();
            return $this->redirect(['personas/buscar-persona']);
        }

        $idEfector = Yii::$app->user->getIdEfector();

        $serviciosXEfector = ServiciosEfector::profesionalPorServiciosAgendaPorEfector($idEfector, $id_servicio_practica);
        $idsServiciosSinDerivacion = yii\helpers\ArrayHelper::getColumn($serviciosXEfector['SIN_DERIVACION'], 'id_servicio');
        $idsServiciosConDerivacion = yii\helpers\ArrayHelper::getColumn($serviciosXEfector['CON_DERIVACION'], 'id_servicio');
        $idsServicios = array_merge($idsServiciosSinDerivacion, $idsServiciosConDerivacion);

        //$cps = new ConsultaDerivaciones();
        //$referencias = $cps->porReferencia($id_efector);
        $referencias = ConsultaDerivaciones::getDerivacionesActivasPorPacientePorServiciosPorEfector($idPersonaEnSesion, $idsServicios, $idEfector);
        //var_dump($referencias);die;
        $persona = new Persona();
        $ultimoHC = $persona->obtenerUltimoNHistoriaClinica();

        $feriados = AgendaFeriados::getFeriados();

        return $this->render('index', [
            'persona' => $session_persona,
            'serviciosXEfector' => $serviciosXEfector,
            'referencias' => $referencias,
            'ultimoHC' => intval($ultimoHC) + 1,
            'feriados' => $feriados
        ]);
    }

    /**
     * Lista de espera del día (staff / pantalla recepción).
     *
     * @no_intent_catalog
     */
    public function actionEspera()
    {
        $fecha = Yii::$app->request->get('fecha', date('Y-m-d'));
        $pesId = Yii::$app->request->get('pes');
        $profesional = '';
        $staffContextId = (int) Yii::$app->user->getIdProfesionalEfectorServicio();

        if ($pesId !== null && $pesId !== '') {
            $pes = ProfesionalEfectorServicio::findOne((int) $pesId);
            if ($pes !== null) {
                $profesional = $pes;
                $staffContextId = (int) $pes->id;
            }
        } elseif ($staffContextId > 0) {
            $pes = ProfesionalEfectorServicio::findOne($staffContextId);
            if ($pes !== null) {
                $profesional = $pes;
            }
        }

        $turnos = $staffContextId > 0
            ? Turno::getTurnosPorContextoProfesionalPorFecha($fecha, $staffContextId)
            : [];

        return $this->render('espera', [
            'turnos' => $turnos,
            'fecha' => $fecha,
            'profesional' => $profesional,
        ]);
    }

    /**
     * Displays a single Turno model.
     * @param string $id
     * @return mixed
     * @no_intent_catalog
    */
    public function actionView($id_persona)
    {
        return $this->render('view', [
            'model' => \common\models\Persona::findOne($id_persona),
        ]);
    }

    // Nota: ocupación por día (calendario staff) → GET /api/v1/turnos/calendario-ocupacion-dia.

    /**
     * Se lo llama desde el index
     *
     * Recibe `id_profesional_efector_servicio` (PES) e `id_servicio`.
     * @no_intent_catalog
    */
    public function actionCalendario()
    {
        $this->layout = 'blanco';

        $session = Yii::$app->getSession();
        $session_paciente = unserialize($session['persona']);

        $id_profesional_efector_servicio = Yii::$app->request->get('id_profesional_efector_servicio');

        $id_servicio = Yii::$app->request->get('id_servicio');

        if ($id_servicio == "" || $id_servicio == false) {
            throw new BadRequestHttpException('Parametros servicio faltante');
        }

        return $this->renderAjax('turnos_calendario_profesional', [
            'id_servicio' => $id_servicio,
            'id_profesional_efector_servicio' => $id_profesional_efector_servicio,
            'persona' => $session_paciente,
        ]);
    }

    // actionUpdate eliminado: la actualización de turnos vive en la API v1 (TurnosController::actionActualizarTurno).

    /**
     * UI separada de reprogramación (lista turnos futuros + enlace a API desde app/SPA).
     * @no_intent_catalog
    */
    public function actionReprogramar()
    {
        $session = Yii::$app->getSession();
        $session_persona = @unserialize($session['persona']);
        if (!$session_persona || !isset($session_persona->id_persona)) {
            return $this->redirect(['personas/buscar-persona']);
        }
        $idEfector = Yii::$app->user->getIdEfector();
        $turnos = Turno::findActive()
            ->where(['id_persona' => $session_persona->id_persona, 'id_efector' => $idEfector, 'estado' => Turno::ESTADO_PENDIENTE])
            ->andWhere(['>=', 'fecha', date('Y-m-d')])
            ->orderBy(['fecha' => SORT_ASC, 'hora' => SORT_ASC])
            ->all();

        return $this->render('reprogramar', [
            'persona' => $session_persona,
            'turnos' => $turnos,
        ]);
    }

    /**
     * Finds the Turno model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return Turno the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Turno::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('La página solicitada no existe');
        }
    }

    /**
     * @no_intent_catalog
    */
    public function actionList()
    {
        $tfecha = Yii::$app->request->get('TurnoBusqueda') ? Yii::$app->request->get('TurnoBusqueda')['fecha'] : date("Y-m-d") . ' - ' . date("Y-m-d");
        $idStaffContext = (int) (Yii::$app->user->getIdProfesionalEfectorServicio() ?: 0);
        $servicioAsignado = Yii::$app->request->get('TurnoBusqueda') ? Yii::$app->request->get('TurnoBusqueda')['id_servicio_asignado'] : NULL;
        $idPesFiltroList = Yii::$app->request->get('TurnoBusqueda')['id_profesional_efector_servicio'] ?? null;

        $searchModel = new TurnoBusqueda();
        if ($servicioAsignado != null) {
            $searchModel->id_servicio_asignado = $servicioAsignado;
        }
        if ($idPesFiltroList != null) {
            $searchModel->id_profesional_efector_servicio = $idPesFiltroList;
        }
        $dataProvider = $searchModel->searchAllTurnos($tfecha, $idStaffContext);
        return $this->render('list', [
            'turnos' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Metodo de acceso libre para mostrar turnos pendiente por DNI
     * @no_intent_catalog
    */
    public function actionListTurnos()
    {
        $this->layout = 'publico/turnos';

        $fecha_hoy = date('Y-m-d');

        $searchModel = new TurnoLibreBusqueda();
        $searchModel->fecha_hoy = $fecha_hoy;
        $dataProvider = $searchModel->search(Yii::$app->request->post());

        return $this->render('list-turnos', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
        ]);
    }
}
