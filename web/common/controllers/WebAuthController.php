<?php

namespace common\controllers;

use common\components\Platform\Core\Auth\AccountRecoveryService;
use common\components\Platform\Core\Auth\ApiAuthSessionService;
use common\components\Platform\Core\Auth\DemoSandboxSessionService;
use common\components\Platform\Core\Auth\PasskeyAuthenticationService;
use common\components\Platform\Core\Auth\QrLoginService;
use common\components\Platform\Core\Auth\WebApiJwtSessionService;
use common\components\Shared\Infrastructure\Qr\QrSvgRenderer;
use common\models\Platform\User;
use Yii;
use yii\web\Controller;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;
use yii\filters\VerbFilter;

/**
 * Autenticación web: login, logout, contraseña y confirmación de e-mail.
 */
class WebAuthController extends Controller
{
    private const JSON_ACTIONS = [
        'passkey-login-options',
        'passkey-login-verify',
        'qr-login-start',
        'qr-login-status',
        'recovery-approve',
        'recovery-reject',
        'passkey-registration-options',
        'passkey-registration-verify',
        'passkey-revoke',
        'web-jwt',
    ];

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'passkey-login-options' => ['POST'],
                    'passkey-login-verify' => ['POST'],
                    'passkey-registration-options' => ['POST'],
                    'passkey-registration-verify' => ['POST'],
                    'passkey-revoke' => ['POST'],
                    'qr-login-start' => ['POST'],
                    'qr-login-status' => ['POST'],
                    'recovery-approve' => ['POST'],
                    'recovery-reject' => ['POST'],
                    'web-jwt' => ['GET'],
                ],
            ],
        ];
    }

    public function beforeAction($action): bool
    {
        if (in_array($action->id, self::JSON_ACTIONS, true)) {
            Yii::$app->response->format = Response::FORMAT_JSON;
        }

        return parent::beforeAction($action);
    }

    public function actions(): array
    {
        return [
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'minLength' => 3,
                'maxLength' => 4,
                'offset' => 5,
            ],
        ];
    }

    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $this->layout = 'loginLayout';

        return $this->render('//login/login');
    }

    public function actionPasskeyLoginOptions(): array
    {
        Yii::$app->session->open();

        return PasskeyAuthenticationService::authenticationOptions(Yii::$app->session->getId());
    }

    public function actionPasskeyLoginVerify(): array
    {
        $body = Yii::$app->request->getBodyParams();
        $challengeId = (string) ($body['challengeId'] ?? '');
        $credential = $body['credential'] ?? null;
        if ($challengeId === '' || !is_array($credential)) {
            throw new BadRequestHttpException('Faltan datos para validar la passkey.');
        }

        Yii::$app->session->open();
        $user = PasskeyAuthenticationService::authenticate(
            Yii::$app->session->getId(),
            $challengeId,
            $credential
        );
        Yii::$app->session->remove('apiJwtToken');
        if (!Yii::$app->user->login($user, 0)) {
            throw new \RuntimeException('No se pudo iniciar la sesión web con la passkey.');
        }

        WebApiJwtSessionService::ensureValidTokenInSession();
        return [
            'success' => true,
            'redirectUrl' => $this->getReturnUrl(),
        ];
    }

    public function actionQrLoginStart(): array
    {
        Yii::$app->session->open();

        $result = QrLoginService::start(Yii::$app->session->getId());
        $result['qrSvg'] = QrSvgRenderer::render(
            $result['qrPayload'],
            'Código QR de inicio de sesión'
        );

        return $result;
    }

    public function actionQrLoginStatus(): array
    {
        $challengeId = (string) (Yii::$app->request->getBodyParams()['challengeId'] ?? '');
        if ($challengeId === '') {
            throw new BadRequestHttpException('Falta el desafío del código QR.');
        }

        Yii::$app->session->open();
        $result = QrLoginService::status(Yii::$app->session->getId(), $challengeId);
        $user = $result['user'] ?? null;

        if ($result['status'] === 'approved' && $user instanceof User) {
            if (Yii::$app->user->login($user, 0)) {
                Yii::$app->session->remove('apiJwtToken');
                WebApiJwtSessionService::ensureValidTokenInSession();
                return ['status' => 'approved', 'redirectUrl' => $this->getReturnUrl()];
            }
        }

        return ['status' => $result['status']];
    }

    public function actionRecoveryRequest()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $this->layout = 'loginLayout';
        $identifier = '';
        $note = '';
        $requested = false;

        if (Yii::$app->request->isPost) {
            $identifier = (string) (Yii::$app->request->post('identifier') ?? '');
            $note = (string) (Yii::$app->request->post('note') ?? '');
            if (trim($identifier) !== '') {
                AccountRecoveryService::request($identifier, $note);
                $requested = true;
            }
        }

        return $this->render('//login/recovery-request', [
            'identifier' => $identifier,
            'note' => $note,
            'requested' => $requested,
        ]);
    }

    public function actionRecoveryReview()
    {
        if (Yii::$app->user->isGuest) {
            return $this->redirect(['/auth/login']);
        }
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User || (int) ($identity->superadmin ?? 0) !== 1) {
            throw new ForbiddenHttpException('Solo un superadmin puede revisar solicitudes de recuperación.');
        }

        $this->layout = 'main';
        $requests = AccountRecoveryService::pendingRequests();

        return $this->render('//login/recovery-review', ['requests' => $requests]);
    }

    public function actionRecoveryApprove(): array
    {
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User || (int) ($identity->superadmin ?? 0) !== 1) {
            throw new ForbiddenHttpException('No autorizado.');
        }

        $requestId = (string) (Yii::$app->request->getBodyParams()['requestId'] ?? '');
        if ($requestId === '') {
            throw new BadRequestHttpException('Falta la solicitud a aprobar.');
        }

        $code = AccountRecoveryService::approve($requestId, $identity);

        return ['success' => true, 'recoveryCode' => $code];
    }

    public function actionRecoveryReject(): array
    {
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User || (int) ($identity->superadmin ?? 0) !== 1) {
            throw new ForbiddenHttpException('No autorizado.');
        }

        $requestId = (string) (Yii::$app->request->getBodyParams()['requestId'] ?? '');
        $reason = (string) (Yii::$app->request->getBodyParams()['reason'] ?? '');
        if ($requestId === '') {
            throw new BadRequestHttpException('Falta la solicitud a rechazar.');
        }

        AccountRecoveryService::reject($requestId, $identity, $reason);

        return ['success' => true];
    }

    public function actionRecoveryConsume()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $this->layout = 'loginLayout';
        $code = '';
        $error = null;

        if (Yii::$app->request->isPost) {
            $code = trim((string) (Yii::$app->request->post('code') ?? ''));
            if ($code !== '') {
                try {
                    $user = AccountRecoveryService::consume($code);
                    if (Yii::$app->user->login($user, 0)) {
                        Yii::$app->session->remove('apiJwtToken');
                        WebApiJwtSessionService::ensureValidTokenInSession();

                        return $this->redirect(['/auth/passkeys']);
                    }
                    $error = 'No se pudo iniciar la sesión. Volvé a intentarlo.';
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return $this->render('//login/recovery-consume', [
            'code' => $code,
            'error' => $error,
        ]);
    }

    public function actionPasskeyRegistrationOptions(): array
    {
        $user = Yii::$app->user->identity;
        if (!$user instanceof User || Yii::$app->user->isGuest) {
            throw new UnauthorizedHttpException('Iniciá sesión para registrar una passkey.');
        }

        Yii::$app->session->open();

        return PasskeyAuthenticationService::registrationOptions($user, Yii::$app->session->getId());
    }

    public function actionPasskeyRegistrationVerify(): array
    {
        $user = Yii::$app->user->identity;
        if (!$user instanceof User || Yii::$app->user->isGuest) {
            throw new UnauthorizedHttpException('Iniciá sesión para registrar una passkey.');
        }

        $body = Yii::$app->request->getBodyParams();
        $challengeId = (string) ($body['challengeId'] ?? '');
        $credential = $body['credential'] ?? null;
        if ($challengeId === '' || !is_array($credential)) {
            throw new BadRequestHttpException('Faltan datos para registrar la passkey.');
        }

        Yii::$app->session->open();
        PasskeyAuthenticationService::registerCredential(
            $user,
            Yii::$app->session->getId(),
            $challengeId,
            $credential,
            (string) ($body['name'] ?? '')
        );

        return ['success' => true];
    }

    public function actionPasskeys()
    {
        if (Yii::$app->user->isGuest) {
            return $this->redirect(['/auth/login']);
        }

        $this->layout = 'main';
        $credentials = PasskeyAuthenticationService::activeCredentialsForUser((int) Yii::$app->user->id);

        return $this->render('//login/passkeys', ['credentials' => $credentials]);
    }

    public function actionPasskeyRevoke(): array
    {
        $userId = (int) Yii::$app->user->id;
        if (Yii::$app->user->isGuest || $userId <= 0) {
            throw new UnauthorizedHttpException('Iniciá sesión para administrar tus passkeys.');
        }

        $hash = (string) (Yii::$app->request->getBodyParams()['credentialIdHash'] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            throw new BadRequestHttpException('La passkey indicada no es válida.');
        }

        PasskeyAuthenticationService::revokeCredential($userId, $hash);
        ApiAuthSessionService::revokeAllForUser($userId);
        WebApiJwtSessionService::revokeCurrentToken();
        Yii::$app->user->logout();

        return ['success' => true, 'redirectUrl' => Yii::$app->user->loginUrl];
    }

    public function actionLogout()
    {
        WebApiJwtSessionService::revokeCurrentToken();
        try {
            (new DemoSandboxSessionService())->purgeCurrentYiiSession();
        } catch (\Throwable $e) {
            Yii::warning('demo sandbox purge on logout: ' . $e->getMessage(), __METHOD__);
        }

        Yii::$app->user->logout();

        return $this->redirect(Yii::$app->user->loginUrl);
    }

    /**
     * JWT vigente para la SPA web (autenticación por cookie de sesión Yii, no Bearer).
     *
     * GET /auth/web-jwt
     */
    public function actionWebJwt(): array
    {
        if (Yii::$app->user->isGuest || Yii::$app->user->identity === null) {
            Yii::$app->response->statusCode = 401;

            return [
                'success' => false,
                'message' => 'Usuario no autenticado',
                'data' => null,
            ];
        }

        WebApiJwtSessionService::ensureValidTokenInSession();
        $token = WebApiJwtSessionService::getSessionToken();
        if ($token === null) {
            Yii::$app->response->statusCode = 500;

            return [
                'success' => false,
                'message' => 'No se pudo emitir token de sesión web',
                'data' => null,
            ];
        }

        return [
            'success' => true,
            'message' => 'Token de sesión web',
            'data' => ['token' => $token],
        ];
    }

    protected function isValidReturnUrl(string $url): bool
    {
        return true;
    }

    protected function getReturnUrl(): string
    {
        $returnUrl = Yii::$app->user->getReturnUrl(Yii::$app->homeUrl);

        return $this->isValidReturnUrl($returnUrl) ? $returnUrl : Yii::$app->homeUrl;
    }
}
