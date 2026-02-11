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
use frontend\components\UserRequest;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use yii\debug\models\timeline\DataProvider;

/**
 * TurnosController implements the CRUD actions for Turno model.
 */
class TurnosController extends Controller
{

    public $freeAccessActions = ['list-turnos', 'eventos'];

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
            ],
        ];
    }

    /**
     * Listar turnos de un paciente
     * @entity Turnos
     * @tags turno,cita,listar,ver,agenda
     * @keywords listar,ver turnos,citas,agenda
     * @synonyms turno,cita,agenda,reserva
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
     */
    public function actionView($id_persona)
    {
        return $this->render('view', [
            'model' => \common\models\Persona::findOne($id_persona),
        ]);
    }

    /**
     * Crea un nuevo turno médico
     * @entity Turnos
     * @tags turno,cita,crear,agendar,solicitar,nuevo
     * @keywords crear turno,agendar turno,solicitar turno,nuevo turno,crear cita,agendar cita
     * @synonyms turno,cita,agenda,reserva,consulta
     */
    public function actionCreate()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $model = new Turno();
        $model->load(Yii::$app->request->post());

        // Campos obligatorios recibidos por POST
        $model->id_persona = UserRequest::requireUserParam('id_persona');
        $model->id_rr_hh = UserRequest::requireUserParam('idRecursoHumano');
        $model->id_efector = UserRequest::requireUserParam('idEfector');
        $model->id_servicio = UserRequest::requireUserParam('servicio_actual');

        $cps = ConsultaDerivaciones::getDerivacionesPorPersona($model->id_persona, $model->id_efector, $model->id_servicio_asignado, ConsultaDerivaciones::ESTADO_EN_ESPERA);
        if (count($cps) > 0):
            foreach ($cps as $cp) {
                $cp->estado = ConsultaDerivaciones::ESTADO_CON_TURNO;
                $cp->save();
                $parent_id = $cp->id;
            }
            $model->parent_class = Consulta::PARENT_CLASSES[Consulta::PARENT_DERIVACION];
            $model->parent_id = $parent_id;

        endif;

        if ($model->id_servicio_asignado == "" || $model->id_servicio_asignado == false) {
            throw new BadRequestHttpException('Parametro servicio faltante');
        }

        $servicioEfector = ServiciosEfector::find()->where(['id_servicio' => $model->id_servicio_asignado])->andWhere(['id_efector' => $model->id_efector])->one();

        if ($servicioEfector->formas_atencion == ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS) {
            $model->scenario = ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS;
        } elseif ($servicioEfector->formas_atencion == ServiciosEfector::DELEGAR_A_CADA_RRHH) {
            $model->scenario = ServiciosEfector::DELEGAR_A_CADA_RRHH;

            //Aqui chequeo si el rrhh tiene cupo para atender un paciente mas.
            $agenda = Agenda_rrhh::find()->andWhere(['id_rrhh_servicio_asignado' => $model->id_rrhh_servicio_asignado])->one();
            $cantTurnosOtorgados = Turno::cantidadDeTurnosOtorgados($model->id_rrhh_servicio_asignado, $model->fecha);

            if ($agenda->cupo_pacientes != 0 && $agenda->cupo_pacientes <= $cantTurnosOtorgados) {
                return ["success" => false, "message" => "Ya se otorgaron todos los turnos correspondientes al limite establecido, por favor revise el historial de turnos del profesional"];
            }
        }

        if ($model->save()) {
            return ["success" => true];
        } else {
            return ["success" => false, "message" => $model->getErrorSummary(true)];
        }
    }

    /**
     * Crea un turno para el paciente autenticado (usado principalmente desde la app móvil)
     * El id_persona se obtiene automáticamente del usuario autenticado
     * 
     * @entity Turnos
     * @tags turno,cita,crear,agendar,solicitar,nuevo,paciente
     * @keywords crear turno,agendar turno,solicitar turno,nuevo turno,crear cita,agendar cita,mi turno
     * @synonyms turno,cita,agenda,reserva,consulta
     * 
     * @paramOption id_servicio_asignado select servicios|efector_servicios
     * @paramOption id_rr_hh select rrhh|efector_rrhh
     * @paramOption id_efector select efectores|user_efectores
     * 
     * @return array Respuesta JSON con success y message
     */
    public function actionCrearMiTurno()
    {
        // Si es GET, devolver wizard_config desde template
        if (Yii::$app->request->isGet) {
            // No establecer FORMAT_JSON aquí, dejar que CrudController lo maneje
            // Obtener parámetros proporcionados por el usuario (vienen en GET)
            $providedParams = Yii::$app->request->get();
            // Remover action_id si está presente (es un parámetro de la API, no del formulario)
            unset($providedParams['action_id']);
            
            // Parámetros para procesar variables dinámicas (como "today")
            $templateParams = array_merge([
                'today' => date('Y-m-d')
            ], $providedParams);
            
            $config = \common\components\FormConfigTemplateManager::render(
                'turnos',  // entity
                'crear-mi-turno',  // action
                $templateParams  // Incluye both template vars y provided params para calcular initial_step
            );
            
            // Verificar que el config tenga wizard_config y no esté vacío
            if (!isset($config['wizard_config'])) {
                Yii::error("FormConfigTemplateManager no devolvió wizard_config para turnos/crear-mi-turno", 'turnos-controller');
                throw new \yii\web\ServerErrorHttpException(
                    "No se pudo cargar la configuración del formulario. Por favor, contacte al administrador."
                );
            }
            
            // Verificar que wizard_config tenga contenido
            $wizardConfig = $config['wizard_config'];
            $hasSteps = !empty($wizardConfig['steps'] ?? []);
            $hasFields = !empty($wizardConfig['fields'] ?? []);
            
            if (!$hasSteps && !$hasFields) {
                Yii::error("FormConfigTemplateManager devolvió wizard_config vacío para turnos/crear-mi-turno", 'turnos-controller');
                throw new \yii\web\ServerErrorHttpException(
                    "No se pudo cargar la configuración del formulario. Por favor, contacte al administrador."
                );
            }
            
            
            // El config ya tiene wizard_config, devolverlo directamente
            // Formato esperado: ['wizard_config' => [...]]
            Yii::info("Devolviendo wizard_config desde template: " . json_encode($config), 'turnos-controller');
            return $config;
        }

        // Si es POST, establecer formato JSON para la respuesta
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        // Obtener id_persona de la sesión (ya asignado por la autenticación JWT o web)
        // La autenticación garantiza que idPersona esté disponible o lanza error antes
        $session = Yii::$app->session;
        $idPersona = $session->get('idPersona');

        $model = new Turno();
        $model->load(Yii::$app->request->post());
        if (empty($model->tipo_atencion)) {
            $model->tipo_atencion = Turno::TIPO_ATENCION_PRESENCIAL;
        }
        // Si es teleconsulta, validar que la agenda del profesional (Agenda_rrhh) acepte consultas online
        if ($model->tipo_atencion === Turno::TIPO_ATENCION_TELECONSULTA) {
            $idRrhhServicio = !empty($model->id_rrhh_servicio_asignado) ? $model->id_rrhh_servicio_asignado : null;
            if (!$idRrhhServicio && !empty($model->id_rr_hh) && !empty($model->id_servicio_asignado)) {
                $rs = \common\models\RrhhServicio::find()
                    ->andWhere(['id_rr_hh' => $model->id_rr_hh, 'id_servicio' => $model->id_servicio_asignado])
                    ->select('id')->one();
                $idRrhhServicio = $rs ? $rs->id : null;
            }
            if ($idRrhhServicio) {
                $aceptaOnline = Agenda_rrhh::find()
                    ->andWhere(['id_rrhh_servicio_asignado' => $idRrhhServicio])
                    ->andWhere(['acepta_consultas_online' => true])
                    ->exists();
                if (!$aceptaOnline) {
                    return [
                        'success' => false,
                        'message' => 'El profesional seleccionado no acepta consultas por chat. Elegí atención presencial u otro profesional.',
                    ];
                }
            } elseif ($model->id_rrhh_servicio_asignado || $model->id_rr_hh) {
                return [
                    'success' => false,
                    'message' => 'No se encontró la agenda del profesional para el servicio.',
                ];
            }
        }

        // Asignar id_persona del usuario autenticado (no se recibe por POST)
        $model->id_persona = $idPersona;
        
        // Campos obligatorios recibidos por POST usando nombres del modelo
        $post = Yii::$app->request->post();
        if (!isset($post['id_rr_hh'])) {
            throw new BadRequestHttpException('Parámetro requerido: id_rr_hh');
        }
        if (!isset($post['id_efector'])) {
            throw new BadRequestHttpException('Parámetro requerido: id_efector');
        }
        if (!isset($post['id_servicio_asignado'])) {
            throw new BadRequestHttpException('Parámetro requerido: id_servicio_asignado');
        }
        
        $model->id_rr_hh = $post['id_rr_hh'];
        $model->id_efector = $post['id_efector'];
        $model->id_servicio_asignado = $post['id_servicio_asignado'];
        // id_servicio se asigna automáticamente desde id_servicio_asignado si es necesario
        if (isset($post['id_servicio'])) {
            $model->id_servicio = $post['id_servicio'];
        } else {
            $model->id_servicio = $post['id_servicio_asignado'];
        }

        // Verificar derivaciones pendientes
        $cps = ConsultaDerivaciones::getDerivacionesPorPersona($model->id_persona, $model->id_efector, $model->id_servicio_asignado, ConsultaDerivaciones::ESTADO_EN_ESPERA);
        if (count($cps) > 0):
            foreach ($cps as $cp) {
                $cp->estado = ConsultaDerivaciones::ESTADO_CON_TURNO;
                $cp->save();
                $parent_id = $cp->id;
            }
            $model->parent_class = Consulta::PARENT_CLASSES[Consulta::PARENT_DERIVACION];
            $model->parent_id = $parent_id;
        endif;

        // Validar servicio asignado
        if ($model->id_servicio_asignado == "" || $model->id_servicio_asignado == false) {
            throw new BadRequestHttpException('Parametro servicio faltante');
        }

        // Obtener configuración del servicio-efector para determinar forma de atención
        $servicioEfector = ServiciosEfector::find()
            ->where(['id_servicio' => $model->id_servicio_asignado])
            ->andWhere(['id_efector' => $model->id_efector])
            ->one();

        if (!$servicioEfector) {
            throw new BadRequestHttpException('Servicio no encontrado para el efector especificado');
        }

        // Configurar escenario según forma de atención
        if ($servicioEfector->formas_atencion == ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS) {
            $model->scenario = ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS;
        } elseif ($servicioEfector->formas_atencion == ServiciosEfector::DELEGAR_A_CADA_RRHH) {
            $model->scenario = ServiciosEfector::DELEGAR_A_CADA_RRHH;

            // Verificar si el rrhh tiene cupo para atender un paciente más
            $agenda = Agenda_rrhh::find()
                ->andWhere(['id_rrhh_servicio_asignado' => $model->id_rrhh_servicio_asignado])
                ->one();
            
            if ($agenda) {
                $cantTurnosOtorgados = Turno::cantidadDeTurnosOtorgados($model->id_rrhh_servicio_asignado, $model->fecha);

                if ($agenda->cupo_pacientes != 0 && $agenda->cupo_pacientes <= $cantTurnosOtorgados) {
                    return [
                        "success" => false, 
                        "message" => "Ya se otorgaron todos los turnos correspondientes al límite establecido, por favor revise el historial de turnos del profesional"
                    ];
                }
            }
        }

        // Guardar el turno
        if ($model->save()) {
            return [
                "success" => true,
                "message" => "Turno creado exitosamente",
                "data" => [
                    "id_turno" => $model->id_turnos,
                    "fecha" => $model->fecha,
                    "hora" => $model->hora
                ]
            ];
        } else {
            return [
                "success" => false, 
                "message" => "Error al crear el turno",
                "errors" => $model->getErrorSummary(true)
            ];
        }
    }

    /**
     * Se lo llama desde el index
     *
     * Recibe id_rrhh_servicio_asignado e id_servicio
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

    /**
     * Updates an existing Turno model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        $date_arr = explode("-", $model->fecha);
        $model->fecha = $date_arr[2] . '-' . $date_arr[1] . '-' . $date_arr[0];


        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['index']);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Turno model.
     * If deletion is successful, the browser will respond "OK" as this request only receives ajax.
     * @param string $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        if (\Yii::$app->request->isAjax) {
            \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

            // esta modificacion es para cargar la propiedad motivo_cancelacion aparte del soft delete
            $model = $this->findModel($id);
            $model->load(Yii::$app->request->post());
            $model->estado = 'CANCELADO';
            $model->deleted_by = Yii::$app->user->id;
            $model->deleted_at = new Expression('NOW()');

            $success = true;
            $msg = '';
            if (! $model->save()) {
                $success = false;
                $msg = $model->getErrorSummary(true);
            }
            return ["success" => $success, "message" => $msg];
        }
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

    public function actionEspera()
    {
        $fecha = Yii::$app->request->get('fecha') ? Yii::$app->request->get('fecha') : date("Y-m-d");

        //para que lo vean desde el administrativo
        if (Yii::$app->request->get('rrhh')) {
            $rrhh = Yii::$app->request->get('rrhh');
            $rrhhServicio = RrhhServicio::findOne(['id' => $rrhh]);
            $idRrhh = $rrhhServicio->id_rr_hh;
        } else {
            $idRrhh = Yii::$app->user->getIdRecursoHumano();
        }

        $turnos = Turno::getTurnosPorRrhhPorFecha($fecha, $idRrhh);

        if (Yii::$app->request->get('rrhh')) {
            $this->layout = 'imprimir';
        }

        return $this->render('espera', ['turnos' => $turnos, 'fecha' => $fecha, 'profesional' => $rrhhServicio ?? '']);
    }

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

    public function actionNoSePresento()
    {

        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $id_turno = Yii::$app->request->post('id_turno');

        $turno = Turno::findOne($id_turno);
        $idRrhhServicio = UserRequest::requireUserParam('id_rrhh_servicio');
        $idServicio = UserRequest::requireUserParam('servicio_actual');

        if ($turno->id_rrhh_servicio_asignado == $idRrhhServicio) {

            Turno::NoSePresento($id_turno);
            return [
                'success' => true,
                'msg' => '<p class="text-success">El paciente no se presentó</p>'
            ];
        } elseif ($turno->id_rrhh_servicio_asignado == 0 && $turno->id_servicio_asignado == $idServicio) {

            Turno::NoSePresento($id_turno);
            return [
                'success' => true,
                'msg' => '<p class="text-success">El paciente no se presentó</p>'
            ];
        }
        return [
            'success' => false,
            'msg' => '<p class="text-danger">El estado del turno no se pudo modificar</p>'
        ];
    }


    //Metodo de acceso libre para mostrar turnos pendiente por DNI
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
