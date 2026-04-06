<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\helpers\ArrayHelper;

//use webvimark\modules\UserManagement\UserManagementModule;
use webvimark\modules\UserManagement\models\User;

use common\models\Efector;
use common\models\RrhhEfector;
use common\models\RrhhServicio;
use common\models\Persona;
use common\models\Agenda_rrhh;
use Firebase\JWT\JWT;

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
    
    /**
     * Deshabilitar CSRF para el método de prueba
     */
  /*  public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        // Deshabilitar CSRF para test-action-matching
        $behaviors[] = [
            'class' => \yii\filters\ContentNegotiator::class,
            'only' => ['test-action-matching'],
            'formats' => [
                'application/json' => \yii\web\Response::FORMAT_JSON,
            ],
        ];
        
        return $behaviors;
    }*/
    
    /**
     * Deshabilitar validación CSRF para métodos específicos
     */
   /* public function beforeAction($action)
    {
        if ($action->id === 'test-action-matching') {
            $this->enableCsrfValidation = false;
        }
        return parent::beforeAction($action);
    }*/

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

    /**
     * Post-login: si falta efector, servicio o encounter class en sesión, muestra el asistente de selección
     * (vistas despuesdelogin/*). Si ya está completo, redirige al listado de pacientes.
     * No es equivalente a actionPacientes: aquí se resuelve contexto de sesión, allí se lista la agenda del día.
     */
    public function actionInicio()
    {
        $idEfector = Yii::$app->user->getIdEfector();
        $servicioActual = Yii::$app->user->getServicioActual();
        $encounterClass = Yii::$app->user->getEncounterClass();

        if (!$idEfector || !$servicioActual || !$encounterClass) {
            $this->layout = 'main_sinmenuizquierda';

            // La SPA web consume /api/v1 con Bearer JWT (igual que móvil). Si el usuario ya estaba logueado
            // antes de que se genere el token en afterLogin, regenerarlo aquí para evitar 401 en el wizard.
            $session = Yii::$app->session;
            if (!$session->get('apiJwtToken') && !Yii::$app->user->isGuest) {
                $identity = Yii::$app->user->identity;
                if ($identity) {
                    $persona = Persona::findOne(['id_user' => $identity->id]);
                    if ($persona) {
                        $payload = [
                            'user_id' => $identity->id,
                            'email' => $identity->email,
                            'id_persona' => (int) $persona->id_persona,
                            'iat' => time(),
                            'exp' => time() + (24 * 60 * 60),
                        ];
                        $token = JWT::encode($payload, Yii::$app->params['jwtSecret'], 'HS256');
                        $session->set('apiJwtToken', $token);
                    }
                }
            }

            return $this->render('despuesdelogin/inicio');
        }

        $fechaParam = Yii::$app->request->get('fecha');
        $fecha = $fechaParam ? date('Y-m-d', strtotime($fechaParam)) : date('Y-m-d');

        return $this->redirect(['site/pacientes', 'fecha' => $fecha]);
    }

    public function actionAsistente()
    {
        return $this->render('asistente');
    }


    /**
     * Vista HTML del listado de pacientes (datos vía API /api/v1/pacientes/*).
     */
    public function actionPacientes()
    {
        $fechaParam = Yii::$app->request->get('fecha');
        $fecha = $fechaParam ? date('Y-m-d', strtotime($fechaParam)) : date('Y-m-d');

        return $this->render('//pacientes/listado', [
            'fecha' => $fecha,
            'encounter_class' => Yii::$app->user->getEncounterClass(),
            'id_servicio_actual' => (int) Yii::$app->user->getServicioActual(),
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
            Yii::$app->response->redirect(['site/pacientes'])->send();
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
            \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions(Yii::$app->user->identity);
            \common\components\Actions\AllowedRoutesResolver::markSessionRoutesOwner((int) Yii::$app->user->id);
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

        // BioenlaceDbManager usa idRecursoHumano en sesión para armar permisos; tras elegir lista de efectores
        // aún puede faltar ese dato (varios efectores). Refrescar rutas con el contexto actual.
        \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions(Yii::$app->user->identity);
        \common\components\Actions\AllowedRoutesResolver::markSessionRoutesOwner((int) Yii::$app->user->id);

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
        \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions(Yii::$app->user->identity);
        \common\components\Actions\AllowedRoutesResolver::markSessionRoutesOwner((int) Yii::$app->user->id);

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
            $url = ['/site/pacientes'];
        }

        if (User::hasRole(['Administrativo'])) {
            $url = ['/site/pacientes'];
        } elseif (User::hasRole(['Enfermeria'])) {
            $url = ['/personas/buscar-persona'];
        } else {
            $url = ['/site/pacientes'];
        }

        return $url;
    }

    /**
     * Establece en session un array que nos va a pemitir saber la agenda del dia actual
     */
    public static function establecerAgendaDisponible($id_rr_hh)
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
        $path = Yii::getAlias('@runtime') . '/impersonation/a.txt';
        $raw = is_file($path) ? file_get_contents($path) : '';
        $id = is_string($raw) ? (int) trim($raw) : 0;

        if ($id <= 0) {
            Yii::$app->session->setFlash('error', 'Enlace de impersonación inválido o expirado.');

            return $this->redirect(Yii::$app->user->loginUrl);
        }

        $user = User::findOne($id);
        if ($user === null) {
            @file_put_contents($path, '', LOCK_EX);
            Yii::$app->session->setFlash('error', 'Usuario no encontrado.');

            return $this->redirect(Yii::$app->user->loginUrl);
        }

        try {
            Yii::$app->user->login($user, 0);
        } catch (\Throwable $e) {
            @file_put_contents($path, '', LOCK_EX);
            Yii::$app->session->setFlash('error', 'No se pudo iniciar sesión con ese usuario.');
            Yii::error('actionImpersonate: ' . $e->getMessage(), __METHOD__);

            return $this->redirect(Yii::$app->user->loginUrl);
        }

        @file_put_contents($path, '', LOCK_EX);

        // Si afterLogin no terminó la respuesta (p. ej. sin evento redirect), ir a inicio (wizard o pacientes).
        if (!Yii::$app->response->isSent) {
            return $this->redirect(['site/inicio']);
        }
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
     * Obtener acciones comunes para el usuario actual (misma lógica que GET /api/v1/acciones/comunes).
     * @return \yii\web\Response
     */
    public function actionGetCommonActions()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $userId = (int) Yii::$app->user->id;
        if ($userId <= 0) {
            return [
                'success' => false,
                'error' => 'No autenticado',
                'actions' => [],
            ];
        }

        try {
            $actions = \common\components\Services\Actions\CommonActionsService::getFormattedForUser($userId);
            $out = [];
            foreach ($actions as $row) {
                $out[] = [
                    'route' => $row['route'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                ];
            }

            return [
                'success' => true,
                'actions' => $out,
            ];
        } catch (\Throwable $e) {
            Yii::error("Error obteniendo acciones comunes: " . $e->getMessage(), 'site-controller');
            return [
                'success' => false,
                'error' => 'Error al cargar acciones comunes',
                'actions' => [],
            ];
        }
    }

}
