<?php

namespace frontend\controllers;

use common\components\Platform\Core\Auth\DemoSandboxSessionService;
use common\models\Platform\User;
use frontend\components\AccountRecoveryService;
use frontend\components\PasskeyAuthenticationService;
use frontend\components\QrLoginService;
use frontend\components\WebApiJwtSessionService;
use Yii;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\filters\VerbFilter;

/**
 * Autenticación web: login, logout, contraseña y confirmación de e-mail.
 */
class AuthController extends Controller
{
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
                ],
            ],
        ];
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
/*
        $this->layout = Yii::$app->id === 'bioenlace-admin'
            ? '@admin/views/layouts/loginLayout.php'
            : '@frontend/views/layouts/loginLayout.php';
*/
        return $this->render('@frontend/views/login/login');
    }

    public function actionPasskeyLoginOptions(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->session->open();

        return PasskeyAuthenticationService::authenticationOptions(Yii::$app->session->getId());
    }

    public function actionPasskeyLoginVerify(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $body = Yii::$app->request->getBodyParams();
        $challengeId = (string) ($body['challengeId'] ?? '');
        $credential = $body['credential'] ?? null;
        if ($challengeId === '' || !is_array($credential)) {
            throw new \yii\web\BadRequestHttpException('Faltan datos para validar la passkey.');
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
        $returnUrl = Yii::$app->user->getReturnUrl(Yii::$app->homeUrl);
        if (
            Yii::$app->id === 'bioenlace-admin'
            && !\admin\components\UserConfig::isValidReturnUrl($returnUrl)
        ) {
            $returnUrl = Yii::$app->homeUrl;
        }

        return [
            'success' => true,
            'redirectUrl' => $returnUrl,
        ];
    }

    public function actionQrLoginStart(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->session->open();

        return QrLoginService::start(Yii::$app->session->getId());
    }

    public function actionQrLoginStatus(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $challengeId = (string) (Yii::$app->request->getBodyParams()['challengeId'] ?? '');
        if ($challengeId === '') {
            throw new \yii\web\BadRequestHttpException('Falta el desafío del código QR.');
        }

        Yii::$app->session->open();
        $result = QrLoginService::status(Yii::$app->session->getId(), $challengeId);

        if ($result['status'] === 'approved' && isset($result['userId'])) {
            $user = User::findIdentity($result['userId']);
            if ($user instanceof User && Yii::$app->user->login($user, 0)) {
                Yii::$app->session->remove('apiJwtToken');
                WebApiJwtSessionService::ensureValidTokenInSession();
                $returnUrl = Yii::$app->user->getReturnUrl(Yii::$app->homeUrl);
                if (Yii::$app->id === 'bioenlace-admin' && !\admin\components\UserConfig::isValidReturnUrl($returnUrl)) {
                    $returnUrl = Yii::$app->homeUrl;
                }

                return ['status' => 'approved', 'redirectUrl' => $returnUrl];
            }
        }

        return ['status' => $result['status']];
    }

    public function actionRecoveryRequest()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $this->layout = '@frontend/views/layouts/loginLayout.php';
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

        return $this->render('@frontend/views/login/recovery-request', [
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

        $this->layout = Yii::$app->id === 'bioenlace-admin'
            ? '@admin/views/layouts/main.php'
            : '@frontend/views/layouts/main.php';

        $requests = (new \yii\db\Query())
            ->select(['r.*', 'u.username', 'u.email'])
            ->from(['r' => '{{%account_recovery_request}}'])
            ->leftJoin(['u' => '{{%user}}'], 'u.id = r.id_user')
            ->where(['r.status' => 'pending'])
            ->orderBy(['r.created_at' => SORT_ASC])
            ->all();

        return $this->render('@frontend/views/login/recovery-review', ['requests' => $requests]);
    }

    public function actionRecoveryApprove(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User || (int) ($identity->superadmin ?? 0) !== 1) {
            Yii::$app->response->statusCode = 403;

            return ['success' => false, 'message' => 'No autorizado.'];
        }

        $requestId = (string) (Yii::$app->request->getBodyParams()['requestId'] ?? '');
        if ($requestId === '') {
            throw new \yii\web\BadRequestHttpException('Falta la solicitud a aprobar.');
        }

        $code = AccountRecoveryService::approve($requestId, $identity);

        return ['success' => true, 'recoveryCode' => $code];
    }

    public function actionRecoveryReject(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User || (int) ($identity->superadmin ?? 0) !== 1) {
            Yii::$app->response->statusCode = 403;

            return ['success' => false, 'message' => 'No autorizado.'];
        }

        $requestId = (string) (Yii::$app->request->getBodyParams()['requestId'] ?? '');
        $reason = (string) (Yii::$app->request->getBodyParams()['reason'] ?? '');
        if ($requestId === '') {
            throw new \yii\web\BadRequestHttpException('Falta la solicitud a rechazar.');
        }

        AccountRecoveryService::reject($requestId, $identity, $reason);

        return ['success' => true];
    }

    public function actionRecoveryConsume()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $this->layout = '@frontend/views/layouts/loginLayout.php';
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

        return $this->render('@frontend/views/login/recovery-consume', [
            'code' => $code,
            'error' => $error,
        ]);
    }

    public function actionPasskeyRegistrationOptions(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $user = Yii::$app->user->identity;
        if (!$user instanceof User || Yii::$app->user->isGuest) {
            Yii::$app->response->statusCode = 401;

            return ['success' => false, 'message' => 'Iniciá sesión para registrar una passkey.'];
        }

        Yii::$app->session->open();

        return PasskeyAuthenticationService::registrationOptions($user, Yii::$app->session->getId());
    }

    public function actionPasskeyRegistrationVerify(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $user = Yii::$app->user->identity;
        if (!$user instanceof User || Yii::$app->user->isGuest) {
            Yii::$app->response->statusCode = 401;

            return ['success' => false, 'message' => 'Iniciá sesión para registrar una passkey.'];
        }

        $body = Yii::$app->request->getBodyParams();
        $challengeId = (string) ($body['challengeId'] ?? '');
        $credential = $body['credential'] ?? null;
        if ($challengeId === '' || !is_array($credential)) {
            throw new \yii\web\BadRequestHttpException('Faltan datos para registrar la passkey.');
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

        $this->layout = Yii::$app->id === 'bioenlace-admin'
            ? '@admin/views/layouts/main.php'
            : '@frontend/views/layouts/main.php';
        $credentials = (new \yii\db\Query())
            ->select(['credential_id_hash', 'name', 'created_at', 'last_used_at'])
            ->from('{{%webauthn_credential}}')
            ->where(['id_user' => (int) Yii::$app->user->id, 'revoked_at' => null])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        return $this->render('@frontend/views/login/passkeys', ['credentials' => $credentials]);
    }

    public function actionPasskeyRevoke(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $userId = (int) Yii::$app->user->id;
        if (Yii::$app->user->isGuest || $userId <= 0) {
            Yii::$app->response->statusCode = 401;

            return ['success' => false, 'message' => 'Iniciá sesión para administrar tus passkeys.'];
        }

        $hash = (string) (Yii::$app->request->getBodyParams()['credentialIdHash'] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            throw new \yii\web\BadRequestHttpException('La passkey indicada no es válida.');
        }

        $activeCount = (new \yii\db\Query())
            ->from('{{%webauthn_credential}}')
            ->where(['id_user' => $userId, 'revoked_at' => null])
            ->count();
        if ((int) $activeCount <= 1) {
            throw new ForbiddenHttpException('Registrá otra passkey antes de revocar la única disponible.');
        }

        $revoked = Yii::$app->db->createCommand()->update('{{%webauthn_credential}}', [
            'revoked_at' => time(),
        ], [
            'credential_id_hash' => $hash,
            'id_user' => $userId,
            'revoked_at' => null,
        ])->execute();
        if ($revoked !== 1) {
            throw new NotFoundHttpException('La passkey no existe o ya fue revocada.');
        }

        \common\components\Platform\Core\Auth\ApiAuthSessionService::revokeAllForUser($userId);
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
    public function actionWebJwt()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

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
}

