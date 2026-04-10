<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\db\Expression;
use yii\web\BadRequestHttpException;
use yii\filters\VerbFilter;

use common\models\busquedas\TurnoLibreBusqueda;
use common\models\busquedas\TurnoBusqueda;
use common\models\Consulta;
use common\models\Turno;
use common\models\Agenda_rrhh;
use common\models\AgendaFeriados;
use common\models\RrhhEfector;
use common\models\RrhhServicio;
use common\models\ServiciosEfector;
use common\models\ConsultaDerivaciones;
use common\models\Persona;
use common\models\User;
use frontend\components\UserRequest;
use common\components\Services\Turnos\TurnoSlotFinder;

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
        'eventos' => ['GET', 'OPTIONS'],
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

        $id_rr_hh = Yii::$app->request->get('id_rr_hh');

        $serviciosXEfector = ServiciosEfector::rrhhPorServiciosAgendaPorEfector($idEfector, $id_servicio_practica);
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

        return $this->render('index2', [
            'persona' => $session_persona,
            'serviciosXEfector' => $serviciosXEfector,
            'referencias' => $referencias,
            'ultimoHC' => intval($ultimoHC) + 1,
            'feriados' => $feriados
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

    // Nota: la creación/cancelación/no-se-presentó/sobreturno viven en la API v1 (TurnosController API).
    // En web, las vistas/JS deben llamar a /api/v1/turnos/... con Authorization header.

    /**
     * Se lo llama desde el index
     *
     * Recibe id_rrhh_servicio_asignado e id_servicio
     * @no_intent_catalog
    */
    public function actionCalendario()
    {
        $this->layout = 'blanco';

        $session = Yii::$app->getSession();
        $session_paciente = unserialize($session['persona']);

        $id_rrhh_servicio_asignado = Yii::$app->request->get('id_rrhh_servicio_asignado');

        $id_servicio = Yii::$app->request->get('id_servicio');

        if ($id_servicio == "" || $id_servicio == false) {
            throw new BadRequestHttpException('Parametros servicio faltante');
        }

        return $this->renderAjax('turnos_rrhh', [
            'id_servicio' => $id_servicio,
            'id_rrhh_servicio_asignado' => $id_rrhh_servicio_asignado,
            'persona' => $session_paciente,
        ]);
    }

    /**
     * Este metodo carga las horas disponibles por día
     * Se lo llama desde js despues de llamar a turnos/calendario
     *
     * Recibe id_rrhh_servicio_asignado e id_servicio
     * @no_intent_catalog
    */
    public function actionEventos()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $request = Yii::$app->request;
        $dia = $request->get('dia') ?: $request->post('dia') ?: date("Y-m-d");
        $id_rrhh_servicio_asignado = (int) ($request->get('id_rrhh_servicio_asignado') ?: $request->post('id_rrhh_servicio_asignado') ?: 0);
        $id_servicio = $request->get('id_servicio') ?: $request->post('id_servicio');
        $id_rr_hh = $request->get('id_rr_hh') ?: $request->post('id_rr_hh');

        if ($id_rrhh_servicio_asignado === 0 && $id_rr_hh && $id_servicio) {
            $resolved = RrhhServicio::obtenerIdRrhhServicio($id_rr_hh, $id_servicio);
            if ($resolved) {
                $id_rrhh_servicio_asignado = (int) $resolved;
            }
        }

        $id_efector = Yii::$app->user->getIdEfector();

        $formatoSlots = ($request->get('formato') ?: $request->post('formato')) === 'slots';

        $turnosQuery = Turno::findActive();
        if ($id_rrhh_servicio_asignado) {
            $turnosQuery->andWhere(['id_rrhh_servicio_asignado' => $id_rrhh_servicio_asignado]);
        } else {
            $turnosQuery->andWhere(['id_efector' => $id_efector])
                ->andWhere(['id_servicio_asignado' => $id_servicio]);
        }

        $turnos = $turnosQuery->andWhere(['fecha' => $dia])
            ->andWhere(['estado' => Turno::ESTADOS_PARA_DESHABILITAR])
            ->orderBy('hora')
            ->all();

        //Feriados
        $feriado = AgendaFeriados::getFeriadosPorFecha($dia);
        $mensajeFeriado = '';

        if ($feriado != null) {
            $mensajeFeriado = '<h5 class="ps-5"><u><strong>No se pueden asignar turnos para un dia feriado.</strong></u></h5>';
        }

        $horasTurnosOcupados = [];
        // este array solamente es para agregar un custom attribute al span de hora
        $pacientesTurnosOcupados = [];
        foreach ($turnos as $turno) {
            $horasTurnosOcupados[$turno->id_turnos] = $turno->hora;
            $pacientesTurnosOcupados[$turno->id_turnos] = $turno->persona->id_persona;
        }

        $nroDiaDeSemana = date('N', strtotime($dia)) - 1;
        $columnasAgenda = ['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'];

        $agregoSegundos = false;

        if ($id_servicio and $id_rrhh_servicio_asignado == 0) {
            // eventos por servicio completo
            // 1. Buscamos a todos los rrhh del efector con el servicio elegido por el usuario
            $rrhhServicios = RrhhServicio::find()
                ->leftJoin('rrhh_efector', 'rrhh_efector.id_rr_hh = rrhh_servicio.id_rr_hh')
                ->andWhere(['rrhh_efector.id_efector' => $id_efector])
                ->andWhere(['rrhh_servicio.id_servicio' => $id_servicio])
                ->all();
            // 2. Las agendas para todos los rrhh
            $agendas = Agenda_rrhh::find()
                ->andWhere(['in', 'id_rrhh_servicio_asignado', Yii\helpers\ArrayHelper::getColumn($rrhhServicios, 'id')])
                ->all();

            $agendaDiaSeleccionado = false;
            $horariosAgenda = [];
            foreach ($agendas as $agenda) {
                if ($agenda->{$columnasAgenda[$nroDiaDeSemana]}) {
                    $agendaDiaSeleccionado = true;
                    $horariosAgenda = array_merge($horariosAgenda, array_map('intval', explode(",", $agenda->{$columnasAgenda[$nroDiaDeSemana]})));
                }
            }

            $minutosXHora = 15;
            $formasAtencion = 'ORDEN_LLEGADA';
            // no hay agenda para el dia seleccionado
            if (!$agendaDiaSeleccionado) {
                $mensajeSinTurnosDisponibles = '<p class="fst-italic ps-5">Sin turnos disponibles.</p>';
                $ret = ['turnos' => ['maniana' => $mensajeSinTurnosDisponibles, 'tarde' => $mensajeSinTurnosDisponibles]];
                if ($formatoSlots) $ret['results'] = [];
                return $ret;
            }

            // quito posibles repetidos por el array_merge
            $horariosAgenda = array_unique($horariosAgenda, SORT_NUMERIC);
            sort($horariosAgenda);

            // si hay agenda (no salta en el return anterior) y si el dia seleccionado no es el dia actual
            $mensajePorOrdendeLlegada = '<p class="ps-5"><u><strong>Los turnos se otorgan por orden de llegada.</strong></u></p>';
            if ($dia !== date("Y-m-d")) {
                $ret = ['turnos' => ['maniana' => $mensajePorOrdendeLlegada, 'tarde' => $mensajePorOrdendeLlegada]];
                if ($formatoSlots) $ret['results'] = [];
                return $ret;
            }

            $cupoPacientes = count($horariosAgenda) * 5;
        } else {
            $agenda = Agenda_rrhh::find()
                ->andWhere(['id_rrhh_servicio_asignado' => $id_rrhh_servicio_asignado])
                ->one();

            $formasAtencion = $agenda->formas_atencion;

            $mensajeSinTurnosDisponibles = '<p class="fst-italic ps-5">Sin turnos disponibles.</p>';
            if (!$agenda->{$columnasAgenda[$nroDiaDeSemana]} || $agenda->{$columnasAgenda[$nroDiaDeSemana]} == '') {
                $ret = ['turnos' => ['maniana' => $mensajeSinTurnosDisponibles, 'tarde' => $mensajeSinTurnosDisponibles]];
                if ($formatoSlots) $ret['results'] = [];
                return $ret;
            }

            // TODO: Bug, por mas que sea por orden de llegada, si no hay ningun servicio (agenda)
            // que atienda para el día elegido no se puede otorgar
            $mensajePorOrdendeLlegada = '<p class="ps-5"><u><strong>Los turnos se otorgan por orden de llegada.</strong></u></p>';
            if ($agenda->formas_atencion == 'ORDEN_LLEGADA' && $dia !== date("Y-m-d")) {
                $ret = ['turnos' => ['maniana' => $mensajePorOrdendeLlegada, 'tarde' => $mensajePorOrdendeLlegada]];
                if ($formatoSlots) $ret['results'] = [];
                return $ret;
            }

            $horariosAgenda = array_map('intval', explode(",", $agenda->{$columnasAgenda[$nroDiaDeSemana]}));

            // cupo pacientes / cantidad de horas por dia

            if (is_null($agenda->cupo_pacientes) || $agenda->cupo_pacientes == 0) {
                $minutosXHora = 15;
            } else {
                $minutosXHora = 60 * count($horariosAgenda) / $agenda->cupo_pacientes;
                $agregoSegundos = $minutosXHora - intval($minutosXHora) < 0.5 ? false : true;
            }

            $cupoPacientes = $agenda->cupo_pacientes > 0 ? $agenda->cupo_pacientes : count($horariosAgenda) * 5;
        }

        $botonesTurnosManiana = [];
        $botonesTurnosTarde = [];
        $slotsDisponibles = [];

        // bandera para saber si todos los turnos ya estan tomados, para el sobreturno
        $todosTomados = true;

        $slots = $this->crearSlots($horariosAgenda, $cupoPacientes, $minutosXHora, $agregoSegundos);

        foreach ($slots as $slot) {

            $break = false;
            $options = ['class' => 'hora btn btn-outline-primary rounded-pill mt-2 me-1 '];

            $hora = $slot;

            // Si esta viendo los turnos del día actual entonces no habilitamos
            // para que seleccione las horas que sean menores a la hora actual
            //ademas deshabilitamos los botones si el dia es feriado.
            $deshabilitado = false;
            if ($dia == date("Y-m-d")) {
                if ($hora <= date("H:i")) {
                    $options['class'] = 'btn btn-outline-secondary rounded-pill mt-2 me-1 ';
                    $deshabilitado = true;
                } else {
                    // El if del orden de llegada va aqui para calcular la hora del siguiente turno
                    if ($formasAtencion == 'ORDEN_LLEGADA') {
                        $break = true;
                    }
                }
            }

            if ($feriado != null) {
                $options['class'] = 'btn btn-outline-secondary rounded-pill mt-2 me-1 ';
                $deshabilitado = true;
            }

            $horario = \DateTime::createFromFormat('H:i', $hora);

            // si para esta hora ya existe un turno asignado, array_search devuelve el key si encuentra
            $id_turno = array_search($hora . ":00", $horasTurnosOcupados);
            if ($id_turno) {
                $break = false;
                $paciente = Persona::findOne($pacientesTurnosOcupados[$id_turno]);
                $turno = Turno::findOne($id_turno);
                $nombrePaciente = $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON);

                $hora = '<del>' . $hora . '</del>';
                $options['id'] = $id_turno;
                $options['id-persona'] = $pacientesTurnosOcupados[$id_turno];
                $options['estado-turno'] = $turno->estado;
                $options['data-bs-toggle'] = 'tooltip';
                $options['tabindex'] = "0";
                $options['data-bs-placement'] = 'top';
                $options['title'] = $nombrePaciente;

                //si existe un turno y es feriado, habilito la posibilidad de cancelar el turno
                if ($feriado != null) {
                    $options['class'] .= 'hora';
                }
            } else {
                $todosTomados = false;
                if (!$deshabilitado) {
                    $slotsDisponibles[] = $slot;
                }
            }

            $unaDeLaManiana = \DateTime::createFromFormat('H:i', "00:00");
            $unaDeLaTarde =  \DateTime::createFromFormat('H:i', "13:00");

            if ($horario >= $unaDeLaManiana && $horario <= $unaDeLaTarde) {
                $botonesTurnosManiana[] = \yii\helpers\Html::tag('span', $hora, $options);
            } else {
                $botonesTurnosTarde[] = \yii\helpers\Html::tag('span', $hora, $options);
            }

            if ($break) {
                $resp = [
                    'turnos' => ['maniana' => $botonesTurnosManiana, 'tarde' => $botonesTurnosTarde, 'todosTomados' => $todosTomados, 'mensajeFeriado' => $mensajeFeriado],
                ];
                if (($request->get('formato') ?: $request->post('formato')) === 'slots') {
                    $resp['results'] = array_map(function ($h) {
                        return ['id' => $h, 'text' => $h];
                    }, $slotsDisponibles);
                }
                return $resp;
            }
            // }
        }
        $resp = [
            'turnos' => ['maniana' => $botonesTurnosManiana, 'tarde' => $botonesTurnosTarde, 'todosTomados' => $todosTomados, 'mensajeFeriado' => $mensajeFeriado],
        ];
        if (($request->get('formato') ?: $request->post('formato')) === 'slots') {
            $resp['results'] = array_map(function ($h) {
                return ['id' => $h, 'text' => $h];
            }, $slotsDisponibles);
        }
        return $resp;
    }

    public function crearSlots($horariosAgenda, $cupoPacientes, $minutosXPaciente, $agregoSegundos)
    {
        // Inicializar el array para los intervalos
        $intervalos = [];
        $intervaloActual = [];

        // Recorrer el array de horarios
        for ($i = 0; $i < count($horariosAgenda); $i++) {
            // Si el intervalo actual está vacío, agregar el primer horario
            if (empty($intervaloActual)) {
                $intervaloActual[] = $horariosAgenda[$i];
            } else {
                // Si el siguiente horario es consecutivo, agregar al intervalo actual
                if ($horariosAgenda[$i] == $intervaloActual[count($intervaloActual) - 1] + 1) {
                    $intervaloActual[] = $horariosAgenda[$i];
                } else {
                    // Si no es consecutivo, guardar el intervalo actual y empezar uno nuevo
                    $intervalos[] = $intervaloActual;
                    $intervaloActual = [$horariosAgenda[$i]];
                }
            }
        }
        // No olvidar agregar el último intervalo al final
        if (!empty($intervaloActual)) {
            $intervalos[] = $intervaloActual;
        }

        $slots = [];

        $minutosXPaciente = intval($minutosXPaciente);

        foreach ($intervalos as $horarios) {

            $inicio = new \DateTime(sprintf('%02d:00', $horarios[0]));
            $ultHora = new \DateTime(sprintf('%02d:00', $horarios[count($horarios) - 1]));
            $fin = $ultHora->modify('+60 minutes');

            while ($inicio < $fin && $cupoPacientes > 0) {
                $slots[] = $inicio->format('H:i');
                $agregoSegundos ? $inicio->modify("+{$minutosXPaciente} minutes 30 seconds") : $inicio->modify("+{$minutosXPaciente} minutes");
                $cupoPacientes--;
            }
        }

        return $slots;
    }

    /**
     * @no_intent_catalog
    */
    public function actionRrhh($id_servicio)
    {
        $idEfector = Yii::$app->user->getIdEfector();
        $sql = "SELECT rh.id_rr_hh, nombre, apellido FROM rrhh_efector "
            . "INNER JOIN rrhh_servicio ON rrhh_servicio.id_rr_hh = rrhh_efector.id_rr_hh "
            . "INNER JOIN personas ON rrhh_efector.id_persona = personas.id_persona "
            . "WHERE rrhh_servicio.id_servicio = $id_servicio AND rrhh_efector.id_efector = $idEfector";

        $command = Yii::$app->db->createCommand($sql);

        $result = $command->queryAll();

        $opciones = '<option>Seleccione...</option>';

        foreach ($result as $row) {
            $opciones .= '<option value="' . $row['id_rr_hh'] . '">' . $row['apellido'] . ', ' . $row['nombre'] . '</option>';
        }
        echo $opciones;
        Yii::$app->end();
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
        $idRrhh = Yii::$app->user->getIdRecursoHumano();
        $servicioAsignado = Yii::$app->request->get('TurnoBusqueda') ? Yii::$app->request->get('TurnoBusqueda')['id_servicio_asignado'] : NULL;
        $rrhhServicioAsignado = Yii::$app->request->get('TurnoBusqueda') ? Yii::$app->request->get('TurnoBusqueda')['id_rrhh_servicio_asignado'] : NULL;

        $searchModel = new TurnoBusqueda();
        if ($servicioAsignado != null) $searchModel->id_servicio_asignado = $servicioAsignado;
        if ($rrhhServicioAsignado != null) $searchModel->id_rrhh_servicio_asignado = $rrhhServicioAsignado;
        $dataProvider = $searchModel->searchAllTurnos($tfecha, $idRrhh);
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
