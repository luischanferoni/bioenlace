<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\BadRequestHttpException;
use yii\filters\VerbFilter;

use common\models\AgendaFeriados;
use common\models\ProfesionalEfectorServicio;
use common\models\ServiciosEfector;
use common\models\ConsultaDerivaciones;
use common\models\Person\Persona;
use common\models\Scheduling\Turno;

/**
 * Turnos staff: asignación por calendario (index + modal API), lista de espera y detalle por paciente.
 */
class TurnosController extends Controller
{
    /** Verbs por acción; la API los usa al mapear. */
    public static $verbs = [
        'index' => ['GET', 'HEAD', 'OPTIONS'],
        'view' => ['GET', 'HEAD', 'OPTIONS'],
        'espera' => ['GET', 'HEAD', 'OPTIONS'],
    ];

    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [],
            ],
        ];
    }

    /**
     * Listar turnos de un paciente (calendario staff embebido vía API).
     *
     * @no_intent_catalog
     */
    public function actionIndex()
    {
        $session = Yii::$app->getSession();
        $session_persona = unserialize($session['persona']);
        $idPersonaEnSesion = (isset($session_persona->id_persona)) ? $session_persona->id_persona : null;
        $id = Yii::$app->request->get('id') ? Yii::$app->request->get('id') : null;
        if ($idPersonaEnSesion == null and $id == null) {
            return $this->redirect(['personas/registrar-paciente']);
        }

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
            return $this->redirect(['personas/registrar-paciente']);
        }

        $idEfector = Yii::$app->user->getIdEfector();

        $serviciosXEfector = ServiciosEfector::profesionalPorServiciosAgendaPorEfector($idEfector, $id_servicio_practica);
        $idsServiciosSinDerivacion = yii\helpers\ArrayHelper::getColumn($serviciosXEfector['SIN_DERIVACION'], 'id_servicio');
        $idsServiciosConDerivacion = yii\helpers\ArrayHelper::getColumn($serviciosXEfector['CON_DERIVACION'], 'id_servicio');
        $idsServicios = array_merge($idsServiciosSinDerivacion, $idsServiciosConDerivacion);

        $referencias = ConsultaDerivaciones::getDerivacionesActivasPorPacientePorServiciosPorEfector($idPersonaEnSesion, $idsServicios, $idEfector);
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
     * Turnos otorgados al paciente en sesión.
     *
     * @no_intent_catalog
     */
    public function actionView($id_persona)
    {
        return $this->render('view', [
            'model' => Persona::findOne($id_persona),
        ]);
    }
}
