<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\BadRequestHttpException;
use yii\helpers\ArrayHelper;

use common\components\Platform\Core\Auth\DemoSandboxAccessService;
use common\components\Platform\Core\Auth\DemoSandboxSessionService;
use common\components\Platform\Core\Permission\BioenlaceAccessChecker;
use common\components\Platform\Core\Permission\BioenlaceSessionPermissions;
use common\models\User;

use common\components\Domain\Clinical\Inpatient\Service\InternacionMapaWebContext;
use common\models\Clinical\Encounter;
use common\models\Clinical\EncounterDefinition;
use common\models\Efector;
use common\models\Person\Persona;
use common\models\Platform\DemoSandboxSession;
use frontend\components\WebApiJwtSessionService;
use common\models\ProfesionalEfectorServicio;
use common\models\Servicio;
use common\components\Domain\Organization\Service\SesionOperativa\SesionOperativaService;

class SiteController extends Controller
{    
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
     * Panel de inicio (SPA): datos vía GET /api/v1/home/panel.
     *
     * @no_intent_catalog
     */
    public function actionIndex()
    {
        if (Yii::$app->user->isGuest) {
            return $this->redirect(Yii::$app->user->loginUrl);
        }

        if (!$this->sesionOperativaCompleta()) {
            return $this->redirect(['site/sesion-operativa']);
        }

        return $this->renderPanelInicio();
    }

    /**
     * Wizard post-login: efector → encounter → servicio (POST sesion-operativa/establecer).
     *
     * @no_intent_catalog
     */
    public function actionSesionOperativa()
    {
        if (Yii::$app->user->isGuest) {
            return $this->redirect(Yii::$app->user->loginUrl);
        }

        if ($this->sesionOperativaCompleta()) {
            $fechaParam = Yii::$app->request->get('fecha');
            $fecha = $fechaParam ? date('Y-m-d', strtotime($fechaParam)) : date('Y-m-d');

            return $this->redirect(['site/index', 'fecha' => $fecha]);
        }

        $this->layout = 'main_sinmenuizquierda';
        WebApiJwtSessionService::ensureValidTokenInSession();

        return $this->render('despuesdelogin/inicio');
    }

    /**
     * @no_intent_catalog
    */
    public function actionAsistente()
    {
        if (Yii::$app->user->isGuest) {
            return $this->redirect(Yii::$app->user->loginUrl);
        }

        return $this->render('asistente');
    }


    /**
     * Alias legacy → panel de inicio.
     *
     * @no_intent_catalog
     */
    public function actionPacientes()
    {
        $fechaParam = Yii::$app->request->get('fecha');
        if ($fechaParam) {
            return $this->redirect(['site/index', 'fecha' => date('Y-m-d', strtotime($fechaParam))]);
        }

        return $this->redirect(['site/index']);
    }

    private function sesionOperativaCompleta(): bool
    {
        return SesionOperativaService::isSesionOperativaCompleta();
    }

    /**
     * @return string
     */
    private function renderPanelInicio()
    {
        $fechaParam = Yii::$app->request->get('fecha');
        $fecha = $fechaParam ? date('Y-m-d', strtotime($fechaParam)) : date('Y-m-d');
        $encounterClass = Yii::$app->user->getEncounterClass();
        $idServicio = (int) Yii::$app->user->getServicioActual();
        $esImpPiso = $encounterClass === Encounter::ENCOUNTER_CLASS_IMP
            && (!$idServicio || !Servicio::esServicioAgendaQuirurgica($idServicio));

        $mapaCtx = null;
        if ($esImpPiso) {
            $idEfector = (int) Yii::$app->user->getIdEfector();
            $mapaCtx = InternacionMapaWebContext::build(
                $idEfector,
                (int) (Yii::$app->request->post('piso') ?? 0) ?: null,
                (int) (Yii::$app->request->post('sala') ?? 0) ?: null
            );
        }

        return $this->render('//pacientes/listado', [
            'fecha' => $fecha,
            'encounter_class' => $encounterClass,
            'id_servicio_actual' => $idServicio,
            'es_imp_piso' => $esImpPiso,
            'mapa_ctx' => $mapaCtx,
        ]);
    }

    /**
     * Se invoca desde UserConfig::afterLogin (config frontend).
     * Prepara efectores en sesión y redirige al wizard de sesión operativa.
     */
    public static function despuesDeLogin()
    {
        if (Yii::$app->user->isSuperadmin) {
            Yii::$app->response->redirect(['site/index'])->send();
            return;
        }

        $urlARedireccionar = self::establecerSesionInicial();
        Yii::$app->response->redirect($urlARedireccionar)->send();
    }

    /**
     * @no_intent_catalog
     */
    public function actionCambiarEncounterClass($codigo)
    {
        $codigo = strtoupper(trim((string) $codigo));
        $permitidas = EncounterDefinition::sessionSelectableClasses();
        if ($codigo === '' || !isset($permitidas[$codigo])) {
            Yii::$app->session->setFlash(
                'error',
                'Área de trabajo no disponible.'
            );

            return $this->redirect(SesionOperativaService::redirectRouteForCurrentUser());
        }

        return $this->reestablecerContextoOperativoYRedirigir([
            'efector_id' => (int) Yii::$app->user->getIdEfector(),
            'servicio_id' => (int) Yii::$app->user->getServicioActual(),
            'encounter_class' => $codigo,
        ]);
    }

    /**
     * @no_intent_catalog
    */
    public function actionCambiarServicio($id_servicio)
    {
        $encounterClass = (string) (Yii::$app->user->getEncounterClass() ?? '');

        return $this->reestablecerContextoOperativoYRedirigir([
            'efector_id' => (int) Yii::$app->user->getIdEfector(),
            'servicio_id' => (int) $id_servicio,
            'encounter_class' => $encounterClass,
        ]);
    }

    /**
     * Reaplica efector/servicio/encounter vía SesionOperativaService y renueva el JWT
     * de API (evita que JsonHttpBearerAuth pise el contexto con claims viejos).
     *
     * @param array{efector_id:int, servicio_id:int, encounter_class:string} $body
     * @return \yii\web\Response
     */
    private function reestablecerContextoOperativoYRedirigir(array $body)
    {
        $body = $this->pinDemoSandboxOperativeBody($body);

        try {
            $data = (new SesionOperativaService())->establecer($body);
        } catch (\InvalidArgumentException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());

            return $this->redirect(SesionOperativaService::redirectRouteForCurrentUser());
        } catch (\RuntimeException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());

            return $this->redirect(SesionOperativaService::redirectRouteForCurrentUser());
        }

        if (!empty($data['context_token'])) {
            WebApiJwtSessionService::storeRawToken((string) $data['context_token']);
        }

        return $this->redirect($data['redirect_url'] ?? SesionOperativaService::redirectRouteForCurrentUser());
    }

    /**
     * En visita demo: no permitir que un JWT/sesión corrupta (p. ej. efector 863) desvíe el contexto.
     *
     * @param array{efector_id?:int, servicio_id?:int, encounter_class?:string} $body
     * @return array{efector_id:int, servicio_id:int, encounter_class?:string}
     */
    private function pinDemoSandboxOperativeBody(array $body): array
    {
        $sessionId = (int) Yii::$app->session->get(DemoSandboxSessionService::Yii_SESSION_KEY, 0);
        if ($sessionId <= 0) {
            return $body;
        }

        /** @var DemoSandboxSession|null $demo */
        $demo = DemoSandboxSession::findOne($sessionId);
        if ($demo === null || $demo->isPurged()) {
            return $body;
        }

        $idEfector = (int) $demo->id_efector;
        $idServicio = (int) $demo->id_servicio;
        try {
            DemoSandboxSessionService::assertIdEfectorEsPlantillaDev($idEfector);
        } catch (\DomainException $e) {
            Yii::error('demo pin: ' . $e->getMessage(), __METHOD__);

            return $body;
        }

        $body['efector_id'] = $idEfector;
        $body['servicio_id'] = $idServicio;

        return $body;
    }

    /**
     * @no_intent_catalog
    */
    public function actionGuiaServicios()
    {
        return $this->render('guia-servicios');
    }

    /**
     * @no_intent_catalog
    */
    public function actionCentrosSalud($id)
    {
        if (isset($id) and $id != 0) {
            return $this->render('centros-salud', ['id' => $id]);
        }
    }

    /**
     * @no_intent_catalog
    */
    public function actionVerCentroSalud($id)
    {
        $efector = Efector::findOne($id);
        return $this->render('ver-centro-salud', [
            'model' => $efector,
        ]);
    }

    /**
     * Establece efector + servicio + encounter en sesión (p. ej. cambio de efector en layout).
     *
     * @no_intent_catalog
     */
    public function actionEstablecerSesionFinal()
    {
        $req = Yii::$app->request;
        try {
            $data = (new SesionOperativaService())->establecer($this->pinDemoSandboxOperativeBody([
                'efector_id' => (int) $req->post('idEfector'),
                'servicio_id' => (int) $req->post('servicio'),
                'encounter_class' => (string) $req->post('encounterClass'),
            ]));
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (\RuntimeException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if (!empty($data['context_token'])) {
            WebApiJwtSessionService::storeRawToken((string) $data['context_token']);
        }

        return $this->redirect($data['redirect_url']);
    }

    /**
     * Tras login: lista de efectores en sesión y permisos RBAC; destino wizard sesión operativa.
     *
     * @return array<int|string, string>
     */
    private static function establecerSesionInicial()
    {
        $efectoresParaSesion = ProfesionalEfectorServicio::getEfectoresParaSesion((int) Yii::$app->user->getIdPersona());

        if (count($efectoresParaSesion) == 0) {
            BioenlaceAccessChecker::refreshForIdentity(Yii::$app->user->identity);
            \common\components\Platform\Assistant\UiActions\AllowedRoutesResolver::markSessionRoutesOwner((int) Yii::$app->user->id);
            $keys = Yii::$app->session->get(BioenlaceSessionPermissions::SESSION_PREFIX_ROLES);

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

        Yii::$app->user->setEfectores(ArrayHelper::map($efectoresParaSesion, 'id_efector', 'nombre'));

        BioenlaceAccessChecker::refreshForIdentity(Yii::$app->user->identity);
        \common\components\Platform\Assistant\UiActions\AllowedRoutesResolver::markSessionRoutesOwner((int) Yii::$app->user->id);

        return ['site/sesion-operativa'];
    }

    /**
     * @no_intent_catalog
     */
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
            return $this->redirect(['site/sesion-operativa']);
        }
    }

    /**
     * Consume código demo del sitio institucional e inicia sesión en la app.
     *
     * GET /site/demo-entrar?code=…
     *
     * @no_intent_catalog
     */
    public function actionDemoEntrar()
    {
        $code = trim((string) Yii::$app->request->get('code', ''));
        if ($code === '') {
            Yii::$app->session->setFlash('error', 'Enlace de demo inválido.');

            return $this->redirect(Yii::$app->user->loginUrl);
        }

        try {
            $consumed = (new DemoSandboxAccessService())->consume($code);
            $user = $consumed['user'];
            $sessionId = $consumed['session_id'];
        } catch (\DomainException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());

            return $this->redirect(Yii::$app->user->loginUrl);
        } catch (\Throwable $e) {
            Yii::error('demo-entrar: ' . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'No se pudo abrir la demo.');

            return $this->redirect(Yii::$app->user->loginUrl);
        }

        if (!Yii::$app->user->isGuest) {
            // destroySession=false conserva la cookie de sesión, pero hay que limpiar
            // contexto operativo / JWT previos (p. ej. efector 863) antes del login demo.
            Yii::$app->user->logout(false);
        }
        $this->clearOperativeSessionContext();

        $demoTtl = max(600, (int) (Yii::$app->params['demo_sandbox']['session_ttl_seconds'] ?? 14400));
        try {
            $loggedIn = Yii::$app->user->login($user, $demoTtl);
        } catch (\Throwable $e) {
            Yii::error('demo-entrar login: ' . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'No se pudo iniciar la sesión demo.');

            return $this->redirect(Yii::$app->user->loginUrl);
        }
        if (!$loggedIn || Yii::$app->user->isGuest || (int) Yii::$app->user->id !== (int) $user->id) {
            Yii::error('demo-entrar login: login() no dejó sesión activa para ' . $user->username, __METHOD__);
            Yii::$app->session->setFlash('error', 'No se pudo iniciar la sesión demo.');

            return $this->redirect(Yii::$app->user->loginUrl);
        }

        if ($sessionId !== null && $sessionId > 0) {
            Yii::$app->session->set(DemoSandboxSessionService::Yii_SESSION_KEY, $sessionId);

            /** @var DemoSandboxSession|null $demoSession */
            $demoSession = DemoSandboxSession::findOne($sessionId);
            if ($demoSession !== null && !$demoSession->isPurged()) {
                try {
                    DemoSandboxSessionService::assertIdEfectorEsPlantillaDev((int) $demoSession->id_efector);
                    try {
                        $established = (new SesionOperativaService())->establecer([
                            'efector_id' => (int) $demoSession->id_efector,
                            'servicio_id' => (int) $demoSession->id_servicio,
                            'encounter_class' => Encounter::ENCOUNTER_CLASS_AMB,
                        ]);
                        if (!empty($established['context_token'])) {
                            WebApiJwtSessionService::storeRawToken((string) $established['context_token']);
                        } else {
                            $this->mintWebJwtFromCurrentOperativeSession();
                        }
                    } catch (\Throwable $e) {
                        Yii::warning('demo-entrar establecer: ' . $e->getMessage(), __METHOD__);
                        $established = $this->bootstrapDemoSesionOperativa($demoSession);
                        $this->mintWebJwtFromCurrentOperativeSession();
                    }
                    $seed = $demoSession->getSeedPayload();
                    $seedHint = sprintf(
                        ' Seed: %d turnos, %d consultas AMB/async, %d virtual (mensaje), %d guardia, %d internación.',
                        count($seed['turno_ids'] ?? []),
                        count($seed['encounter_ids'] ?? []),
                        count($seed['async_encounter_ids'] ?? []),
                        count($seed['guardia_ids'] ?? []),
                        count($seed['internacion_ids'] ?? [])
                    );
                    Yii::$app->session->setFlash(
                        'success',
                        'Demo temporal en '
                        . ($established['efector']['nombre'] ?? 'plantilla DEV')
                        . ' (' . $user->username . ', efector #' . (int) $demoSession->id_efector . '). '
                        . 'Los datos se borran al cerrar sesión o al expirar.'
                        . $seedHint
                    );

                    $indexParams = ['site/index'];
                    $fechaTurnos = trim((string) ($seed['fecha_turnos'] ?? ''));
                    if ($fechaTurnos !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaTurnos) === 1) {
                        $indexParams['fecha'] = $fechaTurnos;
                    }

                    return $this->redirect($indexParams);
                } catch (\Throwable $e) {
                    Yii::warning('demo-entrar sesion operativa: ' . $e->getMessage(), __METHOD__);
                }
            }
        }

        Yii::$app->session->setFlash(
            'success',
            'Estás en una demo temporal (' . $user->username . '). Los datos son de prueba y se borran al cerrar sesión o al expirar.'
        );

        return $this->redirect(['site/sesion-operativa']);
    }

    /**
     * Quita efector/PES/JWT de una sesión previa (logout(false) no los borra).
     */
    private function clearOperativeSessionContext(): void
    {
        $session = Yii::$app->session;
        if (!$session->isActive) {
            $session->open();
        }
        foreach ([
            'idEfector',
            'nombreEfector',
            'efectores',
            'servicios',
            'servicio_actual',
            'idProfesionalEfectorServicio',
            'servicioYhorarioDeTurno',
            'encounterClass',
            'apiJwtToken',
            DemoSandboxSessionService::Yii_SESSION_KEY,
        ] as $key) {
            $session->remove($key);
        }
    }

    /**
     * Emite JWT web con el contexto operativo ya fijado en sesión (AMB/PES/DEV).
     */
    private function mintWebJwtFromCurrentOperativeSession(): void
    {
        $identity = Yii::$app->user->identity;
        if ($identity === null) {
            return;
        }
        if ((int) ($identity->superadmin ?? 0) === 1) {
            WebApiJwtSessionService::storeTokenForSuperadmin($identity);

            return;
        }
        $persona = Persona::findOne(['id_user' => (int) $identity->id]);
        if ($persona === null) {
            return;
        }
        WebApiJwtSessionService::storeTokenForIdentity($identity, $persona);
    }

    /**
     * Fija sesión operativa demo sin pasar por entitlement (plantilla DEV sin billing).
     *
     * @return array{efector: array{id:int,nombre:string}, redirect_url: string}
     */
    private function bootstrapDemoSesionOperativa(DemoSandboxSession $demoSession): array
    {
        $idEfector = (int) $demoSession->id_efector;
        $idServicio = (int) $demoSession->id_servicio;
        $idPes = (int) $demoSession->id_pes;
        $efector = Efector::findOne($idEfector);
        $nombre = $efector !== null ? (string) $efector->nombre : 'Demo DEV';

        Yii::$app->user->setIdEfector($idEfector);
        Yii::$app->user->setNombreEfector($nombre);
        Yii::$app->user->setIdProfesionalEfectorServicio($idPes);
        Yii::$app->user->setServicioActual($idServicio);
        Yii::$app->user->setEncounterClass(Encounter::ENCOUNTER_CLASS_AMB);

        $pesEnEfector = ProfesionalEfectorServicio::find()
            ->where([
                'id_persona' => (int) $demoSession->id_persona,
                'id_efector' => $idEfector,
                'deleted_at' => null,
            ])
            ->all();
        Yii::$app->user->setServicios(ArrayHelper::map(
            $pesEnEfector,
            'id_servicio',
            static function ($p) {
                return $p->servicio !== null ? (string) $p->servicio->nombre : '';
            }
        ));
        Yii::$app->user->setEfectores([$idEfector => $nombre]);

        BioenlaceAccessChecker::refreshForIdentity(Yii::$app->user->identity);
        \common\components\Platform\Assistant\UiActions\AllowedRoutesResolver::markSessionRoutesOwner((int) Yii::$app->user->id);
        SesionOperativaService::aplicarAgendaDisponibleDesdeContextoUsuario();

        $redirect = Yii::$app->urlManager->createUrl(SesionOperativaService::redirectRouteForCurrentUser());

        return [
            'efector' => ['id' => $idEfector, 'nombre' => $nombre],
            'redirect_url' => (string) $redirect,
        ];
    }

    /**
     * @no_intent_catalog
    */
    public function actionError()
    {
        $exception = Yii::$app->errorHandler->exception;

        // Guest + 403/401: ir al login (no alert + menú del shell).
        if (
            Yii::$app->user->isGuest
            && (
                $exception instanceof \yii\web\ForbiddenHttpException
                || $exception instanceof \yii\web\UnauthorizedHttpException
            )
        ) {
            return $this->redirect(Yii::$app->user->loginUrl);
        }

        if ($exception instanceof yii\web\TooManyRequestsHttpException) {
            $this->layout = 'publico/error';
        } elseif (Yii::$app->user->isGuest) {
            $this->layout = '@frontend/views/layouts/loginLayout.php';
        }

        return $this->render('error', ['exception' => $exception]);
    }

}
