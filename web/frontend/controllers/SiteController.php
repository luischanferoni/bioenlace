<?php

namespace frontend\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;
use yii\httpclient\Client;

//use webvimark\modules\UserManagement\UserManagementModule;
use webvimark\modules\UserManagement\models\User;

use frontend\modules\mapear\controllers;

use common\models\LoginForm;
use common\models\Efector;
use common\models\ContactForm;
use common\models\Consulta;
use common\models\Rrhh;
use common\models\RrhhEfector;
use common\models\RrhhServicio;
use common\models\Persona;
use common\models\Agenda_rrhh;
use common\models\Turno;
use common\models\Guardia;
use common\models\Novedad;
use common\models\InfraestructuraPiso;
use common\models\InfraestructuraCama;
use common\models\busquedas\EfectorBusqueda;

class SiteController extends Controller
{
    /*
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }
    */

    public function actions()
    {
        return [

            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    public function actionInicio()
    {
        // Verificar si el usuario tiene configurado el efector, servicio y encounter class
        $idEfector = Yii::$app->user->getIdEfector();
        $servicioActual = Yii::$app->user->getServicioActual();
        $encounterClass = Yii::$app->user->getEncounterClass();

        // Si no tiene la configuración completa, mostrar la pantalla de selección
        if (!$idEfector || !$servicioActual || !$encounterClass) {
            $this->layout = 'main_sinmenuizquierda';
            
            // Preparar datos para la vista de selección
            $searchEfectores = new EfectorBusqueda();
            $array_efectores = Yii::$app->user->getEfectores() ?? [];
            $dataProviderEfectores = $searchEfectores->search(['EfectorBusqueda' => ['efectores' => array_keys($array_efectores)]]);

            return $this->render('despuesdelogin/inicio', [
                'searchEfectores' => $searchEfectores,
                'dataProviderEfectores' => $dataProviderEfectores,
            ]);
        }

        // Si tiene la configuración completa, mostrar los turnos
        $this->layout = 'main';

        // Obtener fecha desde parámetro o usar hoy
        $fechaParam = Yii::$app->request->get('fecha');
        if ($fechaParam) {
            $fecha = date('Y-m-d', strtotime($fechaParam));
        } else {
            $fecha = date('Y-m-d');
        }

        // Obtener turnos para la fecha seleccionada
        $idRrhh = Yii::$app->user->getIdRecursoHumano();
        $turnos = [];
        if ($idRrhh) {
            $turnos = Turno::getTurnosPorRrhhPorFecha($fecha, $idRrhh);
        }

        return $this->render('inicio-turnos', [
            'turnos' => $turnos,
            'fecha' => $fecha,
        ]);
    }

    public function actionAcciones()
    {
        $this->layout = 'main';

        return $this->render('acciones');
    }


    public function actionIndex()
    {
        if (Yii::$app->user->isGuest) {
            Yii::$app->user->loginRequired();
            return;
        }
        
        $this->layout = 'main';

        // Obtener fecha desde parámetro o usar hoy
        $fechaParam = Yii::$app->request->get('fecha');
        if ($fechaParam) {
            $fecha = date('Y-m-d', strtotime($fechaParam));
        } else {
            $fecha = date('Y-m-d');
        }

        // Obtener turnos para la fecha seleccionada
        $idRrhh = Yii::$app->user->getIdRecursoHumano();
        $turnos = [];
        if ($idRrhh) {
            $turnos = Turno::getTurnosPorRrhhPorFecha($fecha, $idRrhh);
        }

        return $this->render('inicio-turnos', [
            'turnos' => $turnos,
            'fecha' => $fecha,
        ]);
    }

    public function buscarInternados($pisos_efector, $sala)
    {
        $internados = array();
        $i = 0;
        foreach ($pisos_efector as $key => $piso) {

            $salas = $piso->infraestructuraSalas;

            foreach ($salas as $key => $sala) {

                $camas = $sala->infraestructuraCamas;

                foreach ($camas as $key => $cama) {

                    if ($cama->estado == 'ocupada') {
                        $i++;
                        if ($i > 5) continue;
                        $url = "internacion/view";
                        $id = $cama->internacionActual->id;

                        if (is_object($cama->internacionActual)) {
                            $internados[$id]['id'] = $id;
                            $internados[$id]['id_persona'] = $cama->internacionActual->id_persona;
                            $internados[$id]['nombre'] = $cama->internacionActual->paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D);
                            $internados[$id]['cama'] = $cama->nro_cama;
                            $internados[$id]['sala'] = $sala->nro_sala;
                            $internados[$id]['piso'] = $piso->nro_piso;
                        } else {
                            $internados[$id]['nombre'] = "Cama " . $cama->nro_cama . " - Ocupada";
                        }
                    }
                }
            }
        }

        return $internados;
    }

    /**
     * Se la llama desde config/web
     * 1. components/UserConfig afterLogin
     * 2. SiteController despuesDeLogin para establecer permisos iniciales, antes de decidir el Efector
     * 3. En pantalla para seleccionar el Efector se redirecciona y va a establecerSessionFinal
     */
    public static function despuesDeLogin()
    {
        if (Yii::$app->user->isSuperadmin) {
            Yii::$app->response->redirect(['site/index'])->send();
            return;
        }

        $urlARedireccionar = self::establecerSessionInicial();
        Yii::$app->response->redirect($urlARedireccionar)->send();
        return;
    }

    /**
     * Llega hasta aqui despues de elegir el efector con el que desea trabajar
     * setea en session el efector elegido y redirige a elegir el encounter class
     */
    public function actionSessionEfectorRedireccionar()
    {
        return $this->redirect(['site/inicio']);
    }

    /**
     * Llega hasta aqui despues de elegir el encounter class
     * setea en session el encounter y hace la redireccion final
     */
    /*public function actionSessionEncounterclassRedireccionar($codigo)
    {
        $url = self::establecerSessionFinal();

        return $this->redirect($url);
    }*/

    public function actionCambiarEncounterClass($codigo)
    {
        Yii::$app->user->setEncounterClass($codigo);

        return $this->redirect(self::generarUrlUsurioEfectorAredireccionar());
    }

    public function actionCambiarServicio($id_servicio)
    {
        Yii::$app->user->setServicioActual($id_servicio);

        $servicioDelRrhh = RrhhServicio::find()
            ->select(['id'])
            ->andWhere(['id_servicio' => $id_servicio])
            ->andWhere(['id_rr_hh' => Yii::$app->user->getIdRecursoHumano()])
            ->one();

        Yii::$app->user->setIdRrhhServicio($servicioDelRrhh->id);

        return $this->redirect(self::generarUrlUsurioEfectorAredireccionar());
    }

    public function actionGuiaServicios()
    {
        return $this->render('guia-servicios');
    }

    public function actionCentrosSalud($id)
    {
        if (isset($id) and $id != 0) {
            return $this->render('centros-salud', ['id' => $id]);
        }
    }

    public function actionVerCentroSalud($id)
    {
        $efector = Efector::findOne($id);
        return $this->render('ver-centro-salud', [
            'model' => $efector,
        ]);
    }

    /*
    * establece en session el id de efector, de recurso humano y/o los permisos que tiene para el efector usando updatePermissions
    */
    private static function establecerSessionInicial()
    {
        // Confirmamos que el usuario no este asociado a un efector
        $rrhh_efectores = RrhhEfector::getEfectores(Yii::$app->user->getIdPersona());

        // Si el usuario no esta en ningun efector se puede tratar de un usuario con permisos para todos los efectores o ninguno
        if (count($rrhh_efectores) == 0) {
            \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions(Yii::$app->user);
            $keys = Yii::$app->session->get(\webvimark\modules\UserManagement\components\AuthHelper::SESSION_PREFIX_ROLES);

            $x_efector = false;
            foreach ($keys as $key) {
                if (strpos($key, '_x_efector_') !== false) {
                    $x_efector = true;
                    break;
                }
            }

            if (!$x_efector) {
                Yii::$app->user->logout();
                Yii::$app->session->setFlash(
                    'info',
                    'Usted no cuenta con los permisos necesarios para ingresar al sistema, comuníquese con su Administrador de Efector'
                );

                return [Yii::$app->user->loginUrl[0]];
            }
        }

        /*  if (count($rrhh_efectores) == 1) {
            // Seteamos el efector con el que el usuario trabajará          
            Yii::$app->user->setIdEfector($rrhh_efectores[0]['id_efector']);
            Yii::$app->user->setNombreEfector($rrhh_efectores[0]['nombre']);
            Yii::$app->user->setIdRecursoHumano($rrhh_efectores[0]['id_rr_hh']);
            $rrhhServicio = RrhhServicio::findActive()->andWhere(['id_rr_hh' => $rrhh_efectores[0]['id_rr_hh']])->all();
            Yii::$app->user->setServicios(ArrayHelper::map($rrhhServicio, 'id_servicio', 'servicio.nombre'));

            \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions(Yii::$app->user);

            self::establecerAgendaDisponible($rrhh_efectores[0]['id_rr_hh']);

            //   return ['consultas/tipoatencion'];
        }*/

        // En session todos los efectores en los que el usuario trabaja
        // para que luego pueda cambiar si necesita
        Yii::$app->user->setEfectores(ArrayHelper::map($rrhh_efectores, 'id_efector', 'nombre'));

        return ['site/inicio'];
    }

    /*private static function establecerSessionEfectores($id_efector)
    {
        $rrhh_efector = RrhhEfector::find()->where(['id_efector' => $id_efector, 'id_persona' => Yii::$app->user->getIdPersona()])->one();

        // Si el usuario selecciona un efector y llega hasta aqui, pero no esta en RrhhEfector
        // quiere decir que es un usuario que tiene permisos para ver cualquier efector sin ser un recurso humano
        if (!$rrhh_efector) {

            $efector = Efector::find()->where(['id_efector' => $id_efector])->one();
        
            Yii::$app->user->setIdEfector($efector->id_efector);
            Yii::$app->user->setNombreEfector($efector->nombre);

            return ['site/index'];
        }

        Yii::$app->user->setIdEfector($rrhh_efector->id_efector);
        Yii::$app->user->setNombreEfector($rrhh_efector->efector->nombre);
        Yii::$app->user->setIdRecursoHumano($rrhh_efector->id_rr_hh);
        Yii::$app->user->setServicios(ArrayHelper::map($rrhh_efector->rrhhServicio, 'id_servicio', 'servicio.nombre'));
        // AuthHelper::updatePermissions recibe como parametro id_user pero no lo utiliza
        // debido al cambio en config/web.php 'components' => ['authManager'...
        \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions(Yii::$app->user);

        self::establecerAgendaDisponible($rrhh_efector->id_rr_hh);
        
        return ['consultas/tipoatencion'];
    }*/

    public function actionEstablecerSessionFinal()
    {
        $encounterClass = Yii::$app->request->post('encounterClass');
        Yii::$app->user->setEncounterClass($encounterClass);

        $servicio = Yii::$app->request->post('servicio');
        Yii::$app->user->setServicioActual($servicio);

        $idEfector = Yii::$app->request->post('idEfector');
        $rrhhEfector = RrhhEfector::find()
            ->where([
                'id_efector' => $idEfector,
                'id_persona' => Yii::$app->user->getIdPersona()
            ])
            ->one();

        Yii::$app->user->setIdEfector($rrhhEfector->id_efector);
        Yii::$app->user->setNombreEfector($rrhhEfector->efector->nombre);
        Yii::$app->user->setIdRecursoHumano($rrhhEfector->id_rr_hh);

        // Todos los servicios que tiene disponibles para este efector
        Yii::$app->user->setServicios(ArrayHelper::map($rrhhEfector->rrhhServicio, 'id_servicio', 'servicio.nombre'));
        // AuthHelper::updatePermissions recibe como parametro id_user pero no lo utiliza
        // debido al cambio en config/web.php 'components' => ['authManager'...
        \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions(Yii::$app->user);

        self::establecerAgendaDisponible($rrhhEfector->id_rr_hh);

        return \yii\helpers\Url::to(self::generarUrlUsurioEfectorAredireccionar());
    }
    /*
    * Despues de elegir el Efector se lo redirige al usuario a diferentes paginas 
    * dependiendo del rol/profesion que disponga
    */
    private static function generarUrlUsurioEfectorAredireccionar()
    {
        User::hasRole(['Medico']);
        if (User::hasRole(['Medico'])) {
            $url = ['/turnos/espera'];
        }

        if (User::hasRole(['Administrativo'])) {
            // Usuarios administrativos van a la nueva página de inicio con IA
            $url = ['/site/index'];
        } elseif (User::hasRole(['Enfermeria'])) {
            $url = ['/personas/buscar-persona'];
        } else {
            $url = ['/site/index'];
        }

        return $url;
    }

    /**
     * Establece en session un array que nos va a pemitir saber la agenda del dia actual
     */
    private static function establecerAgendaDisponible($id_rr_hh)
    {
        $serviciosDelRrhh = RrhhServicio::find()
            ->select(['id', 'id_servicio'])
            ->andWhere(['id_rr_hh' => $id_rr_hh])
            ->asArray()
            ->all();

        foreach ($serviciosDelRrhh as $servicioDelRrhh) {
            if (Yii::$app->user->getServicioActual() == $servicioDelRrhh["id_servicio"]) {
                Yii::$app->user->setIdRrhhServicio($servicioDelRrhh["id"]);
            }
        }

        $nroDiaDeSemana = date('N') - 1;
        $nroDiaDeSemanaManiana = $nroDiaDeSemana == 6 ? 0 : $nroDiaDeSemana + 1;
        $columnasAgenda = ['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'];
        $agendas = Agenda_rrhh::find()
            ->andWhere(['in', 'id_rrhh_servicio_asignado', ArrayHelper::getColumn($serviciosDelRrhh, 'id')])
            ->all();

        $servicios = [$nroDiaDeSemana => [], ($nroDiaDeSemana + 1) => []];
        foreach ($agendas as $agenda) {
            if (($agenda->{$columnasAgenda[$nroDiaDeSemana]} == null || $agenda->{$columnasAgenda[$nroDiaDeSemana]} == "")
                && ($agenda->{$columnasAgenda[$nroDiaDeSemanaManiana]} == null || $agenda->{$columnasAgenda[$nroDiaDeSemanaManiana]} == "")
            ) {
                continue;
            }

            $horasDeAgendaHoy = explode(",", $agenda->{$columnasAgenda[$nroDiaDeSemana]});
            $servicios[$nroDiaDeSemana] = [
                $agenda->rrhhServicioAsignado->id_servicio => [
                    'nombreServicio' => $agenda->rrhhServicioAsignado->servicio->nombre,
                    'horaInicial' => $horasDeAgendaHoy[0],
                    'horaFinal' => $horasDeAgendaHoy[count($horasDeAgendaHoy) - 1],
                ]
            ];
            // Sumo las de mañana por las dudas haya una agenda con horario corrido desde un dia al otro
            $horasDeAgendaManiana = explode(",", $agenda->{$columnasAgenda[$nroDiaDeSemanaManiana]});
            $servicios[$nroDiaDeSemana + 1] = [
                $agenda->rrhhServicioAsignado->id_servicio => [
                    'nombreServicio' => $agenda->rrhhServicioAsignado->servicio->nombre,
                    'horaInicial' => $horasDeAgendaManiana[0],
                    'horaFinal' => $horasDeAgendaManiana[count($horasDeAgendaManiana) - 1],
                ]
            ];
        }

        Yii::$app->user->setServicioYhorarioDeTurno($servicios);
    }

    public function actionImpersonate()
    {
        $id = file_get_contents(Yii::getAlias('@runtime') . '/impersonation/a.txt');

        $user = User::findOne($id);

        Yii::$app->user->login($user, $duration = 0);

        file_put_contents(Yii::getAlias('@runtime') . '/impersonation/a.txt', "", LOCK_EX);
    }

    public function actionError()
    {
        $exception = Yii::$app->errorHandler->exception;

        if ($exception instanceof yii\web\TooManyRequestsHttpException) {
            $this->layout = 'publico/error';
        }
        return $this->render('error', ['exception' => $exception]);
    }

    /**
     * Obtener acciones comunes para el usuario actual
     * @return \yii\web\Response
     */
    public function actionGetCommonActions()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        // Verificar que el usuario esté autenticado
        if (Yii::$app->user->isGuest) {
            return [
                'success' => false,
                'error' => 'Debe estar autenticado para usar esta funcionalidad',
                'actions' => [],
            ];
        }

        try {
            // Obtener acciones disponibles para el usuario
            $availableActions = \common\components\ActionMappingService::getAvailableActionsForUser();
            
            // Filtrar acciones comunes según el rol del usuario
            $commonActions = self::filterCommonActions($availableActions);
            
            // Limitar a 12 acciones más comunes
            $commonActions = array_slice($commonActions, 0, 12);
            
            // Formatear para respuesta
            $formattedActions = [];
            foreach ($commonActions as $action) {
                $formattedActions[] = [
                    'route' => $action['route'],
                    'name' => $action['display_name'],
                    'description' => $action['description'],
                ];
            }

            return [
                'success' => true,
                'actions' => $formattedActions,
            ];
        } catch (\Exception $e) {
            Yii::error("Error obteniendo acciones comunes: " . $e->getMessage(), 'site-controller');
            return [
                'success' => false,
                'error' => 'Error al cargar acciones comunes',
                'actions' => [],
            ];
        }
    }

    /**
     * Filtrar acciones comunes para usuarios administrativos
     * @param array $actions
     * @return array
     */
    private static function filterCommonActions($actions)
    {
        // Palabras clave que indican acciones comunes para administrativos
        $keywords = [
            'persona', 'buscar', 'crear', 'listar', 'reporte', 'usuario',
            'efector', 'servicio', 'turno', 'consulta', 'configuracion',
            'administrar', 'gestionar'
        ];

        // Ordenar acciones por relevancia
        usort($actions, function($a, $b) use ($keywords) {
            $scoreA = self::calculateRelevanceScore($a, $keywords);
            $scoreB = self::calculateRelevanceScore($b, $keywords);
            return $scoreB <=> $scoreA; // Orden descendente
        });

        return $actions;
    }

    /**
     * Calcular puntuación de relevancia para una acción
     * @param array $action
     * @param array $keywords
     * @return int
     */
    private static function calculateRelevanceScore($action, $keywords)
    {
        $score = 0;
        $text = strtolower($action['display_name'] . ' ' . $action['description'] . ' ' . $action['controller'] . ' ' . $action['action']);
        
        foreach ($keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $score += 2;
            }
        }

        // Priorizar acciones de index (listados)
        if ($action['action'] === 'index') {
            $score += 3;
        }

        return $score;
    }
}
