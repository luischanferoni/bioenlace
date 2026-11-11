<?php

namespace frontend\controllers;

use common\components\Domain\Organization\Efector\Application\Service\CommercialSignupDraftService;
use common\components\Domain\Organization\Efector\Application\Service\SignupAbuseGuard;
use common\components\Domain\Organization\Efector\Application\Service\SignupPairingException;
use common\components\Domain\Organization\Efector\Application\Service\SignupPairingService;
use common\components\Domain\Organization\Efector\Application\Service\StaffAffiliationRequestService;
use common\components\Domain\Organization\Efector\Application\UseCase\CreateIdentityAccount;
use common\components\Domain\Organization\Efector\Application\UseCase\SignUpInstitutionalEfector;
use common\components\Domain\Person\Identity\Application\Service\DiditIdentityService;
use common\components\Domain\Person\Identity\Application\Service\IdentityVerificationException;
use common\components\Domain\Person\Identity\Application\UseCase\CreateHostedIdentityVerification;
use common\components\Shared\Infrastructure\Qr\QrSvgRenderer;
use common\models\Organization\SignupPairing;
use frontend\assets\AltaVinculoAsset;
use frontend\assets\AltaVinculoMovilAsset;
use Yii;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\Response;

/**
 * Alta de cuenta desde la web: la computadora elige el centro, muestra el QR y el
 * código; el teléfono verifica la identidad (ver `SignupPairingService`).
 *
 * Acciones JSON (públicas, ligadas a la sesión del navegador): `vinculo-iniciar`,
 * `vinculo-estado`, `vinculo-cancelar`. Los endpoints del teléfono y el alta final
 * viven en las fases siguientes.
 */
class AltaController extends Controller
{
    private const JSON_ACTIONS = [
        'vinculo-iniciar',
        'vinculo-estado',
        'vinculo-cancelar',
        'completar',
        'telefono-codigo',
    ];

    private const RETRY_ATTEMPTS = 6;
    private const RETRY_SECONDS = 5;

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['GET'],
                    'vinculo-iniciar' => ['POST'],
                    'vinculo-estado' => ['POST'],
                    'vinculo-cancelar' => ['POST'],
                    'completar' => ['POST'],
                    'vinculo' => ['GET'],
                    'telefono' => ['GET'],
                    'telefono-codigo' => ['POST'],
                    'telefono-didit' => ['POST'],
                    'telefono-retorno' => ['GET'],
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

    public function actionIndex()
    {
        $this->layout = 'loginLayout';
        AltaVinculoAsset::register($this->view);

        return $this->render('index', [
            'page' => [
                'enabled' => $this->isEnabled(),
                'poll_seconds' => $this->pollSeconds(),
                'urls' => [
                    'iniciar' => Url::to(['/alta/vinculo-iniciar']),
                    'estado' => Url::to(['/alta/vinculo-estado']),
                    'cancelar' => Url::to(['/alta/vinculo-cancelar']),
                    'completar' => Url::to(['/alta/completar']),
                    'centros' => Url::to(['/api/v1/licencia/catalogo-centros']),
                    'provincias' => Url::to(['/api/v1/licencia/catalogo-provincias']),
                ],
            ],
        ]);
    }

    /**
     * Crea el vínculo y devuelve el QR (URL temporal) y el código para la computadora.
     */
    public function actionVinculoIniciar(): array
    {
        if (!$this->isEnabled()) {
            return $this->failure(503, 'El alta web no está habilitada en este entorno.');
        }

        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }

        try {
            (new SignupAbuseGuard())->assertAllowed($body);

            $idEfector = (int) ($body['id_efector'] ?? 0);
            if ($idEfector <= 0) {
                throw new \InvalidArgumentException('Elegí el centro de salud al que pertenecés.');
            }

            $center = SignUpInstitutionalEfector::findAvailableCenter($idEfector);
            if ($center === null) {
                throw new \InvalidArgumentException('El centro elegido no está disponible.');
            }

            $started = $this->pairingService()->start($this->webSessionId(), [
                'id_efector' => $idEfector,
            ]);

            $qrUrl = Url::to(['/alta/vinculo', 'token' => $started['token']], true);
            Yii::info(
                sprintf(
                    'signup-pairing %s: creado desde %s',
                    $started['pairing_id'],
                    $this->clientIpHash()
                ),
                'signup.pairing'
            );

            return [
                'pairing_id' => $started['pairing_id'],
                'code' => $started['code'],
                'expires_at' => $started['expires_at'],
                'qr_svg' => QrSvgRenderer::render(
                    $qrUrl,
                    'Código QR para continuar el alta desde el teléfono'
                ),
                'center' => [
                    'id_efector' => (int) $center['id_efector'],
                    'nombre' => (string) $center['nombre'],
                ],
            ];
        } catch (\DomainException $e) {
            return $this->failure(429, $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->failure(400, $e->getMessage());
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'signup.pairing');

            return $this->failure(500, 'No pudimos iniciar el alta. Reintentá en unos segundos.');
        }
    }

    /**
     * Estado del vínculo para la computadora que lo inició.
     */
    public function actionVinculoEstado(): array
    {
        $pairingId = trim((string) (Yii::$app->request->getBodyParams()['pairing_id'] ?? ''));
        if ($pairingId === '') {
            return $this->failure(400, 'Falta el vínculo del alta.');
        }

        try {
            $status = $this->pairingService()->status($this->webSessionId(), $pairingId);

            return [
                'state' => $status['state'],
                'expires_at' => $status['expires_at'] ?? null,
            ];
        } catch (\InvalidArgumentException $e) {
            return $this->failure(400, $e->getMessage());
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'signup.pairing');

            return $this->failure(500, 'No pudimos consultar el estado del alta.');
        }
    }

    /**
     * Cancela el alta desde la computadora. Idempotente: si el vínculo ya no existe,
     * el resultado es el mismo para quien lo pide.
     */
    public function actionVinculoCancelar(): array
    {
        $pairingId = trim((string) (Yii::$app->request->getBodyParams()['pairing_id'] ?? ''));
        if ($pairingId === '') {
            return ['state' => 'CANCELLED'];
        }

        try {
            $this->pairingService()->cancel($this->webSessionId(), $pairingId);

            return ['state' => 'CANCELLED'];
        } catch (SignupPairingException $e) {
            if ($e->reason === SignupPairingException::NOT_FOUND) {
                return ['state' => 'CANCELLED'];
            }

            return $this->failure(409, $e->getMessage());
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'signup.pairing');

            return $this->failure(500, 'No pudimos cancelar el alta.');
        }
    }

    /**
     * Apertura del enlace del QR desde el teléfono: reclama el vínculo (una sola vez) y
     * redirige a la página del dispositivo, que ya opera con el identificador.
     */
    public function actionVinculo(string $token)
    {
        try {
            $claimed = $this->pairingService()->claim($token, $this->deviceSessionId());
        } catch (SignupPairingException $e) {
            return $this->renderDeviceError($e->getMessage());
        }

        return $this->redirect(['/alta/telefono', 'id' => $claimed['pairing_id']]);
    }

    /**
     * Página del teléfono: código de emparejamiento, lanzamiento de Didit y resultado.
     */
    public function actionTelefono(string $id)
    {
        $status = $this->pairingService()->deviceStatus($id, $this->deviceSessionId());
        if ($status['state'] === SignupPairingService::STATE_NOT_FOUND) {
            return $this->renderDeviceError('Este vínculo es de otro dispositivo o ya no existe.');
        }

        return $this->renderDevicePage($id, $status);
    }

    /**
     * Valida el código que el usuario transcribe en el teléfono.
     */
    public function actionTelefonoCodigo(string $id): array
    {
        $body = Yii::$app->request->getBodyParams();
        $code = trim((string) (is_array($body) ? ($body['code'] ?? '') : ''));

        try {
            $this->pairingService()->authorizeDevice($id, $code, $this->deviceSessionId());

            return ['state' => 'AUTHORIZED'];
        } catch (SignupPairingException $e) {
            Yii::$app->response->statusCode = $e->reason === SignupPairingException::ATTEMPTS_EXHAUSTED
                ? 429
                : 422;

            return [
                'error' => [
                    'message' => $e->getMessage(),
                    'remaining_attempts' => $e->remainingAttempts,
                ],
            ];
        } catch (\InvalidArgumentException $e) {
            Yii::$app->response->statusCode = 400;

            return ['error' => ['message' => $e->getMessage(), 'remaining_attempts' => 0]];
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'signup.pairing');
            Yii::$app->response->statusCode = 500;

            return ['error' => ['message' => 'No pudimos validar el código.', 'remaining_attempts' => 0]];
        }
    }

    /**
     * Crea la sesión hosted de Didit para este vínculo y manda al teléfono a verificarse.
     */
    public function actionTelefonoDidit(string $id)
    {
        $status = $this->pairingService()->deviceStatus($id, $this->deviceSessionId());
        if ($status['state'] === SignupPairingService::STATE_NOT_FOUND) {
            return $this->renderDeviceError('Este vínculo es de otro dispositivo o ya no existe.');
        }
        if ($status['state'] !== SignupPairing::STATE_AUTHORIZED) {
            return $this->renderDevicePage($id, $status, 'Volvé a ingresar el código para continuar.');
        }

        try {
            $callback = Url::to(['/alta/telefono-retorno', 'id' => $id], true);
            $session = (new CreateHostedIdentityVerification())->crear($callback, $id);
            $this->pairingService()->markVerificationStarted($id, $this->deviceSessionId(), $session['session_id']);
        } catch (\RuntimeException $e) {
            Yii::error('Alta web: sesión Didit: ' . $e->getMessage(), 'signup.pairing');

            return $this->renderDevicePage(
                $id,
                $status,
                'No pudimos abrir la verificación de identidad. Probá de nuevo en unos segundos.'
            );
        } catch (SignupPairingException $e) {
            return $this->renderDevicePage($id, $status, $e->getMessage());
        }

        return $this->redirect($session['url']);
    }

    /**
     * Vuelta desde Didit: el servidor consulta la decisión y deja el vínculo verificado.
     * El `status` de la URL se ignora y el `verificationSessionId` sólo se usa para
     * verificar que coincide con la sesión creada.
     */
    public function actionTelefonoRetorno(string $id)
    {
        $status = $this->pairingService()->deviceStatus($id, $this->deviceSessionId());
        if ($status['state'] === SignupPairingService::STATE_NOT_FOUND) {
            return $this->renderDeviceError('Este vínculo es de otro dispositivo o ya no existe.');
        }

        if ($status['state'] !== SignupPairing::STATE_VERIFICATION_STARTED) {
            return $this->renderDevicePage($id, $status);
        }

        $diditSessionId = (string) ($status['didit_session_id'] ?? '');
        if ($diditSessionId === '') {
            return $this->renderDevicePage($id, $status, 'Falta el registro de la verificación. Empezá de nuevo.');
        }

        $returnedSessionId = trim((string) Yii::$app->request->get('verificationSessionId', ''));
        if ($returnedSessionId !== '' && !hash_equals($diditSessionId, $returnedSessionId)) {
            Yii::warning(
                sprintf('signup-pairing %s: sesión Didit no coincide', $id),
                'signup.pairing'
            );

            return $this->renderDeviceError('La verificación no corresponde a este trámite.');
        }

        try {
            (new DiditIdentityService())->fetchApprovedVerification($diditSessionId);
            $this->pairingService()->markVerified($id, $this->deviceSessionId(), $diditSessionId);

            return $this->renderDevicePage($id, ['state' => SignupPairing::STATE_VERIFIED] + $status);
        } catch (IdentityVerificationException $e) {
            if ($e->isRetryable()) {
                return $this->renderDevicePage($id, $status, null, true);
            }

            Yii::info(
                sprintf('signup-pairing %s: verificación %s', $id, $e->verificationStatus),
                'signup.pairing'
            );
            $this->pairingService()->markFailed($id, $this->deviceSessionId());

            return $this->renderDevicePage($id, ['state' => SignupPairing::STATE_FAILED] + $status);
        } catch (\Throwable $e) {
            Yii::error('Alta web: decisión Didit: ' . $e->getMessage(), 'signup.pairing');

            return $this->renderDevicePage(
                $id,
                $status,
                'No pudimos confirmar la verificación con Didit. Probá de nuevo.',
                true
            );
        }
    }

    /**
     * Cierra el alta: crea o reutiliza la cuenta con el centro elegido y registra la
     * solicitud de incorporación.
     *
     * El `verification_id` sale del vínculo (confirmado por el servidor), nunca del
     * navegador. Reintentar sobre un vínculo ya cerrado devuelve el mismo estado.
     */
    public function actionCompletar(): array
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }

        $pairingId = trim((string) ($body['pairing_id'] ?? ''));
        if ($pairingId === '') {
            return $this->failure(400, 'Falta el vínculo del alta.');
        }

        try {
            (new SignupAbuseGuard())->assertAllowed($body);

            $context = $this->pairingService()->completionContext($this->webSessionId(), $pairingId);

            if ($context['state'] === SignupPairingService::STATE_NOT_FOUND) {
                return $this->failure(404, 'El vínculo del alta ya no existe. Volvé a empezar.');
            }
            if ($context['state'] === SignupPairing::STATE_ACCOUNT_CREATED) {
                return ['state' => 'ACCOUNT_CREATED', 'already_done' => true];
            }
            if ($context['state'] !== SignupPairing::STATE_VERIFIED) {
                return $this->failure(409, 'El alta todavía no está lista para terminar.');
            }

            $verificationId = (string) ($context['verification_id'] ?? '');
            $idEfector = (int) ($context['id_efector'] ?? 0);
            if ($verificationId === '' || $idEfector <= 0) {
                return $this->failure(409, 'El alta está incompleta. Volvé a empezar.');
            }

            $identity = (new CreateIdentityAccount())->crear(['verification_id' => $verificationId]);
            $idPersona = (int) $identity['persona']->id_persona;
            $idUser = (int) $identity['user']->id;

            $affiliation = (new StaffAffiliationRequestService())->record($idPersona, $idUser, $idEfector);
            $this->markCompleted($pairingId, $idPersona, $idUser);

            Yii::info(
                sprintf('signup-pairing %s: cuenta creada persona=%d', $pairingId, $idPersona),
                'signup.pairing'
            );

            return [
                'state' => 'ACCOUNT_CREATED',
                'account' => [
                    'es_nueva_persona' => (bool) $identity['es_nueva_persona'],
                    'es_nuevo_usuario' => (bool) $identity['es_nuevo_usuario'],
                ],
                'affiliation' => [
                    'status' => (string) ($affiliation['status'] ?? ''),
                    'nombre_efector' => (string) ($affiliation['nombre_efector'] ?? ''),
                ],
                // Alta de espacio de atención sin terminar: el borrador vive en el servidor.
                'draft_pending' => (new CommercialSignupDraftService())->findForPersona($idPersona) !== null,
            ];
        } catch (SignupPairingException $e) {
            return $this->failure(409, $e->getMessage());
        } catch (\DomainException $e) {
            return $this->failure(429, $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->failure(400, $e->getMessage());
        } catch (IdentityVerificationException $e) {
            if ($e->isRetryable()) {
                return $this->failure(503, 'Todavía no pudimos confirmar tu identidad. Reintentá en unos segundos.');
            }

            return $this->failure(422, 'No pudimos confirmar tu identidad. Volvé a empezar el alta.');
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'signup.pairing');

            return $this->failure(
                500,
                'No pudimos terminar el alta. Reintentá: no vas a duplicar nada.'
            );
        }
    }

    /**
     * El vínculo pudo cerrarse en un reintento concurrente: si ya está en
     * `ACCOUNT_CREATED`, el resultado es el mismo y no hay nada más que hacer.
     */
    private function markCompleted(string $pairingId, int $idPersona, int $idUser): void
    {
        try {
            $this->pairingService()->markAccountCreated($pairingId, $idPersona, $idUser);
        } catch (SignupPairingException $e) {
            Yii::warning(
                sprintf('signup-pairing %s: cierre concurrente (%s)', $pairingId, $e->reason),
                'signup.pairing'
            );
        }
    }

    private function renderDevicePage(
        string $pairingId,
        array $status,
        ?string $notice = null,
        bool $autoRetry = false
    ) {
        $this->layout = 'loginLayout';
        AltaVinculoMovilAsset::register($this->view);

        $attempt = (int) Yii::$app->request->get('attempt', 0);
        $retryUrl = null;
        if ($autoRetry && $attempt + 1 <= self::RETRY_ATTEMPTS) {
            $retryUrl = Url::to([
                '/alta/telefono-retorno',
                'id' => $pairingId,
                'attempt' => $attempt + 1,
            ]);
        }

        return $this->render('vinculo', [
            'page' => [
                'pairing_id' => $pairingId,
                'state' => (string) $status['state'],
                'notice' => $notice,
                'retry_url' => $retryUrl,
                'retry_seconds' => self::RETRY_SECONDS,
                'urls' => [
                    'codigo' => Url::to(['/alta/telefono-codigo', 'id' => $pairingId]),
                    'didit' => Url::to(['/alta/telefono-didit', 'id' => $pairingId]),
                ],
            ],
        ]);
    }

    private function renderDeviceError(string $message)
    {
        $this->layout = 'loginLayout';

        return $this->render('vinculo-error', ['message' => $message]);
    }

    private function pairingService(): SignupPairingService
    {
        return new SignupPairingService(Yii::$app->db);
    }

    private function webSessionId(): string
    {
        return $this->currentSessionId();
    }

    private function deviceSessionId(): string
    {
        return $this->currentSessionId();
    }

    private function currentSessionId(): string
    {
        Yii::$app->session->open();

        return (string) Yii::$app->session->getId();
    }

    private function clientIpHash(): string
    {
        return substr(hash('sha256', (string) Yii::$app->request->getUserIP()), 0, 16);
    }

    private function isEnabled(): bool
    {
        return (bool) (Yii::$app->params['webSignupPairing']['enabled'] ?? true);
    }

    private function pollSeconds(): int
    {
        return max(1, (int) (Yii::$app->params['webSignupPairing']['statusPollSeconds'] ?? 2));
    }

    /**
     * @return array{error: array{message: string}}
     */
    private function failure(int $statusCode, string $message): array
    {
        Yii::$app->response->statusCode = $statusCode;

        return ['error' => ['message' => $message]];
    }
}
