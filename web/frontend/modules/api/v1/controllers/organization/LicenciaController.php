<?php

namespace frontend\modules\api\v1\controllers\organization;

use frontend\modules\api\v1\controllers\BaseController;
use common\components\Domain\Organization\Efector\Application\UseCase\SwitchBillingMembership;
use common\components\Domain\Organization\Efector\Application\UseCase\SignUpInstitutionalEfector;
use common\components\Domain\Organization\Efector\Application\UseCase\RequestMinistrySignup;
use common\components\Domain\Organization\Efector\Application\UseCase\CreateIdentityAccount;
use common\components\Domain\Organization\Efector\Application\UseCase\CompleteMinistrySignupIdentity;
use common\components\Domain\Organization\Efector\Application\Service\SignupAbuseGuard;
use common\components\Platform\Core\Auth\DemoSandboxAccessService;
use common\components\Platform\Core\Auth\DemoSandboxCaptchaService;
use common\components\Platform\Core\Http\Contract\ApiException;
use common\models\Platform\User;
use Yii;

/**
 * Onboarding comercial de licencia (institucional + AdminEfector).
 *
 * Públicas (sin auth): catalogo-ministerios, planes, crear-cuenta, registrar-efector,
 * solicitar-ministerio, demo-perfiles, demo-captcha, demo-acceso, demo-acceso-mobile.
 * Auth AdminEfector: mi-licencia, desvincular-pago-ministerio, asociar-pago-ministerio.
 */
class LicenciaController extends BaseController
{
    public static $authenticatorExcept = [
        'catalogo-ministerios',
        'planes',
        'crear-cuenta',
        'completar-ministerio',
        'registrar-efector',
        'solicitar-ministerio',
        'demo-perfiles',
        'demo-captcha',
        'demo-acceso',
        'demo-acceso-mobile',
    ];

    public function init()
    {
        parent::init();
        $this->contractResponses = true;
    }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);

        return $actions;
    }

    protected function verbs()
    {
        $verbs = parent::verbs();
        $verbs['catalogo-ministerios'] = ['GET', 'OPTIONS'];
        $verbs['planes'] = ['GET', 'OPTIONS'];
        $verbs['crear-cuenta'] = ['POST', 'OPTIONS'];
        $verbs['completar-ministerio'] = ['POST', 'OPTIONS'];
        $verbs['registrar-efector'] = ['POST', 'OPTIONS'];
        $verbs['solicitar-ministerio'] = ['POST', 'OPTIONS'];
        $verbs['demo-perfiles'] = ['GET', 'OPTIONS'];
        $verbs['demo-captcha'] = ['GET', 'OPTIONS'];
        $verbs['demo-acceso'] = ['POST', 'OPTIONS'];
        $verbs['demo-acceso-mobile'] = ['POST', 'OPTIONS'];
        $verbs['mi-licencia'] = ['GET', 'OPTIONS'];
        $verbs['desvincular-pago-ministerio'] = ['POST', 'OPTIONS'];
        $verbs['asociar-pago-ministerio'] = ['POST', 'OPTIONS'];

        return $verbs;
    }

    /**
     * @action_name Catálogo de ministerios activos (onboarding)
     */
    public function actionCatalogoMinisterios()
    {
        return $this->success([
            'items' => SignUpInstitutionalEfector::listMinisteriosActivos(),
        ]);
    }

    /**
     * @action_name Planes / precios de licencia (metadata)
     */
    public function actionPlanes()
    {
        return $this->success(SignUpInstitutionalEfector::planesCatalog());
    }

    /**
     * @action_name Cuenta de identidad (Persona + User passwordless, sin efector)
     */
    public function actionCrearCuenta()
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }

        try {
            (new SignupAbuseGuard())->assertAllowed($body);
            $data = (new CreateIdentityAccount())->crear($body);

            return $this->success([
                'id_persona' => (int) $data['persona']->id_persona,
                'id_user' => (int) $data['user']->id,
                'username' => (string) $data['user']->username,
                'email' => (string) $data['user']->email,
                'token' => $data['token'] ?? null,
                'es_nueva_persona' => (bool) $data['es_nueva_persona'],
                'es_nuevo_usuario' => (bool) $data['es_nuevo_usuario'],
            ], 'Cuenta de identidad creada.');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, str_contains($e->getMessage(), 'Demasiados') ? 429 : 400);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'billing.signup');

            return $this->error('No se pudo crear la cuenta. Reintentá o escribinos.', null, 500);
        }
    }

    /**
     * @action_name Completar identidad de solicitud de ministerio (token + Didit)
     */
    public function actionCompletarMinisterio()
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }

        try {
            (new SignupAbuseGuard())->assertAllowed($body);
            $token = trim((string) ($body['token'] ?? ''));
            $verificationId = trim((string) ($body['verification_id'] ?? ''));
            $req = RequestMinistrySignup::resolvePendingByToken($token);
            $data = (new CompleteMinistrySignupIdentity())->complete((int) $req->id, $verificationId);

            return $this->success([
                'id_request' => (int) $data['id_request'],
                'id_persona' => (int) $data['id_persona'],
                'id_user' => (int) $data['id_user'],
            ], 'Identidad verificada. Un administrador aprobará tu solicitud.');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, str_contains($e->getMessage(), 'Demasiados') ? 429 : 400);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'billing.signup');

            return $this->error('No se pudo completar la solicitud. Reintentá o escribinos.', null, 500);
        }
    }

    /**
     * @action_name Alta self-service efector + AdminEfector + pago simulado
     */
    public function actionRegistrarEfector()
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }

        try {
            (new SignupAbuseGuard())->assertAllowed($body);
            $data = SignUpInstitutionalEfector::register($body);

            return $this->success($data, 'Cuenta creada. Ya podés ingresar a la plataforma.');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, str_contains($e->getMessage(), 'Demasiados') ? 429 : 400);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'billing.signup');

            return $this->error('No se pudo completar el alta. Reintentá o escribinos.', null, 500);
        }
    }

    /**
     * @action_name Solicitud asistida de cuenta ministerio
     */
    public function actionSolicitarMinisterio()
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }

        try {
            $req = RequestMinistrySignup::createRequest($body);

            return $this->success([
                'id' => (int) $req->id,
                'status' => (string) $req->status,
            ], 'Solicitud recibida. Te contactaremos para verificar y activar la cuenta.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }
    }

    /**
     * Perfiles demo disponibles (staff / paciente) según params.
     *
     * GET /api/v1/licencia/demo-perfiles
     *
     * @action_name Perfiles de acceso demo sandbox
     */
    public function actionDemoPerfiles()
    {
        if (!DemoSandboxAccessService::isEnabled()) {
            return $this->success([
                'enabled' => false,
                'items' => [],
            ], 'Acceso demo deshabilitado');
        }

        return $this->success([
            'enabled' => true,
            'require_captcha' => (new DemoSandboxCaptchaService())->isRequired(),
            'items' => DemoSandboxAccessService::listProfiles(),
        ]);
    }

    /**
     * Emite challenge de captcha (imagen data-URI + id) para el CTA demo.
     *
     * GET /api/v1/licencia/demo-captcha
     *
     * @action_name Captcha acceso demo sandbox
     */
    public function actionDemoCaptcha()
    {
        if (!DemoSandboxAccessService::isEnabled()) {
            return $this->error('Acceso demo no habilitado en este entorno', null, 403);
        }

        try {
            $data = (new DemoSandboxCaptchaService())->issue();

            return $this->success($data);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage(), 'demo.sandbox');

            return $this->error('No se pudo generar el captcha.', null, 500);
        }
    }

    /**
     * Emite URL de un solo uso para entrar al sandbox (sitio institucional).
     *
     * POST /api/v1/licencia/demo-acceso
     * Body: { "role": "staff"|"paciente", "email"?: "...", "website"?: "", "captcha"?: "...", "captcha_challenge_id"?: "..." }
     *
     * @action_name Solicitar acceso demo sandbox
     */
    public function actionDemoAcceso()
    {
        if (!DemoSandboxAccessService::isEnabled()) {
            return $this->error('Acceso demo no habilitado en este entorno', null, 403);
        }

        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }

        try {
            $data = (new DemoSandboxAccessService())->issue($body);

            return $this->success($data, 'Listo. Abrí el enlace para entrar a la demo.');
        } catch (\DomainException $e) {
            $msg = $e->getMessage();
            $status = str_contains($msg, 'Demasiados') ? 429 : 400;

            return $this->error($msg, null, $status);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'demo.sandbox');

            return $this->error('No se pudo generar el acceso demo.', null, 500);
        }
    }

    /**
     * Acceso demo staff para app Personal de Salud (JWT + contexto AMB, sin password).
     *
     * POST /api/v1/licencia/demo-acceso-mobile
     * Body: { "role"?: "staff"|"enfermeria"|"administrativo", "website"?: "", "email"?: "..." }
     *
     * @action_name Solicitar acceso demo sandbox (móvil staff)
     */
    public function actionDemoAccesoMobile()
    {
        if (!DemoSandboxAccessService::isEnabled()) {
            return $this->error('Acceso demo no habilitado en este entorno', null, 403);
        }

        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }

        try {
            $data = (new DemoSandboxAccessService())->issueMobileStaff($body);

            return $this->success($data, 'Demo lista. Sesión temporal de staff.');
        } catch (\DomainException $e) {
            $msg = $e->getMessage();
            $status = str_contains($msg, 'Demasiados') ? 429 : 400;

            return $this->error($msg, null, $status);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'demo.sandbox');

            return $this->error('No se pudo abrir la demo móvil.', null, 500);
        }
    }

    /**
     * @action_name Resumen de licencia del efector de sesión (AdminEfector)
     */
    public function actionMiLicencia()
    {
        $this->assertAdminEfector();
        $idEfector = (int) Yii::$app->user->getIdEfector();
        if ($idEfector <= 0) {
            throw new ApiException('request_invalid', ['detail' => 'Establecé el efector en la sesión operativa.']);
        }

        return $this->success(SwitchBillingMembership::summaryForEfector($idEfector));
    }

    /**
     * @action_name Desvincular pago del ministerio (pasa a cuenta propia + cobro simulado)
     */
    public function actionDesvincularPagoMinisterio()
    {
        $this->assertAdminEfector();
        $idEfector = (int) Yii::$app->user->getIdEfector();
        if ($idEfector <= 0) {
            throw new ApiException('request_invalid', ['detail' => 'Establecé el efector en la sesión operativa.']);
        }

        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }

        try {
            $data = SwitchBillingMembership::desvincularPagoMinisterio(
                $idEfector,
                (int) Yii::$app->user->id,
                is_array($body['plan'] ?? null) ? $body['plan'] : [],
                is_array($body['payment'] ?? null) ? $body['payment'] : []
            );

            return $this->success($data, 'Pago desvinculado del ministerio. Ahora facturás en cuenta propia.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }
    }

    /**
     * @action_name Solicitar asociar el pago (POOL) a un ministerio
     */
    public function actionAsociarPagoMinisterio()
    {
        $this->assertAdminEfector();
        $idEfector = (int) Yii::$app->user->getIdEfector();
        if ($idEfector <= 0) {
            throw new ApiException('request_invalid', ['detail' => 'Establecé el efector en la sesión operativa.']);
        }

        $body = Yii::$app->request->getBodyParams();
        $idMinisterio = (int) ($body['id_billing_account_ministerio'] ?? 0);
        if ($idMinisterio <= 0) {
            return $this->error('Indicá id_billing_account_ministerio.', null, 400);
        }

        try {
            $req = SwitchBillingMembership::solicitarAsociarPagoMinisterio(
                $idEfector,
                (int) Yii::$app->user->id,
                $idMinisterio
            );

            return $this->success([
                'id_request' => (int) $req->id,
                'status' => (string) $req->status,
            ], 'Solicitud enviada. El ministerio / Bioenlace debe aprobar el cambio de cupo.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }
    }

    private function assertAdminEfector(): void
    {
        if (!User::hasRole(['AdminEfector'], false)) {
            // Fallback: PES AdminEfector en sesión (API sin roles de sesión web)
            $idServicio = (int) (Yii::$app->user->getServicioActual() ?? 0);
            if ($idServicio > 0
                && \common\components\Domain\Organization\SesionOperativa\Application\UseCase\EstablishOperativeSession::isServicioAdminEfector($idServicio)
            ) {
                return;
            }
            throw new ApiException('forbidden_detail', ['detail' => 'Se requiere rol AdminEfector.']);
        }
    }
}
