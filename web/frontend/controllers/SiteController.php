<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\BadRequestHttpException;
use yii\helpers\ArrayHelper;

//use webvimark\modules\UserManagement\UserManagementModule;
use webvimark\modules\UserManagement\models\User;

use common\components\Clinical\Inpatient\Service\InternacionMapaWebContext;
use common\models\Clinical\Encounter;
use common\models\Efector;
use common\models\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\Servicio;
use common\components\Organization\Service\SesionOperativa\SesionOperativaService;
use common\components\Organization\Service\ProfesionalEfectorServicio\ProfesionalEfectorServicioAltaService;
use Firebase\JWT\JWT;

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
        $this->ensureApiJwtTokenEnSesion();

        return $this->render('despuesdelogin/inicio');
    }

    /**
     * @no_intent_catalog
    */
    public function actionAsistente()
    {
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
        return (bool) Yii::$app->user->getIdEfector()
            && (bool) Yii::$app->user->getServicioActual()
            && (bool) Yii::$app->user->getEncounterClass();
    }

    private function ensureApiJwtTokenEnSesion(): void
    {
        $session = Yii::$app->session;
        if ($session->get('apiJwtToken') || Yii::$app->user->isGuest) {
            return;
        }
        $identity = Yii::$app->user->identity;
        if (!$identity) {
            return;
        }
        $persona = Persona::findOne(['id_user' => $identity->id]);
        if (!$persona) {
            return;
        }
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
        Yii::$app->user->setEncounterClass($codigo);

        return $this->redirect(SesionOperativaService::redirectRouteForCurrentUser());
    }

    /**
     * @no_intent_catalog
    */
    public function actionCambiarServicio($id_servicio)
    {
        Yii::$app->user->setServicioActual($id_servicio);

        $idPersona = (int) Yii::$app->user->getIdPersona();
        $idEfector = (int) Yii::$app->user->getIdEfector();
        $idServicio = (int) $id_servicio;
        $pes = ProfesionalEfectorServicio::findOneActivoPorPersonaEfectorServicio($idPersona, $idEfector, $idServicio);
        if ($pes !== null) {
            Yii::$app->user->setIdProfesionalEfectorServicio((int) $pes->id);
        } else {
            try {
                $out = ProfesionalEfectorServicioAltaService::ensurePersonaServicioEnEfector(
                    $idPersona,
                    $idEfector,
                    $idServicio
                );
                Yii::$app->user->setIdProfesionalEfectorServicio((int) $out['id_profesional_efector_servicio']);
            } catch (\Throwable $e) {
                Yii::warning('actionCambiarServicio: no se pudo asegurar PES: ' . $e->getMessage(), __METHOD__);
                Yii::$app->user->setIdProfesionalEfectorServicio(null);
            }
        }

        SesionOperativaService::aplicarAgendaDisponibleDesdeContextoUsuario();

        return $this->redirect(SesionOperativaService::redirectRouteForCurrentUser());
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
            $data = (new SesionOperativaService())->establecer([
                'efector_id' => (int) $req->post('idEfector'),
                'servicio_id' => (int) $req->post('servicio'),
                'encounter_class' => (string) $req->post('encounterClass'),
            ]);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (\RuntimeException $e) {
            throw new BadRequestHttpException($e->getMessage());
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

        Yii::$app->user->setEfectores(ArrayHelper::map($efectoresParaSesion, 'id_efector', 'nombre'));

        \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions(Yii::$app->user->identity);
        \common\components\Actions\AllowedRoutesResolver::markSessionRoutesOwner((int) Yii::$app->user->id);

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
     * @no_intent_catalog
    */
    public function actionError()
    {
        $exception = Yii::$app->errorHandler->exception;

        if ($exception instanceof yii\web\TooManyRequestsHttpException) {
            $this->layout = 'publico/error';
        } elseif (Yii::$app->user->isGuest) {
            $this->layout = '@frontend/views/layouts/loginLayout.php';
        }
        return $this->render('error', ['exception' => $exception]);
    }

}
