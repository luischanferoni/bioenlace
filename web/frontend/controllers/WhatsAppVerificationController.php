<?php

namespace frontend\controllers;

use common\components\Platform\Assistant\Copy\AssistantChannelCopy;
use common\components\Platform\Assistant\WhatsApp\WhatsAppVerificationService;
use common\models\Integrations\AsistenteWhatsappVinculo;
use Yii;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\Controller;

/**
 * Página pública de verificación de identidad del canal WhatsApp.
 *
 * El enlace llega por WhatsApp con un token opaco. La página no muestra datos del
 * paciente: solo inicia Didit y confirma el resultado contra el servidor.
 */
class WhatsAppVerificationController extends Controller
{
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'verificar' => ['GET'],
                    'verificar-didit' => ['POST'],
                    'verificar-retorno' => ['GET'],
                ],
            ],
        ];
    }

    public function actionVerificar(string $token)
    {
        $vinculo = $this->service()->findVinculoByToken($token);
        if ($vinculo === null) {
            return $this->renderPage($token, null, 'NOT_FOUND');
        }
        if ($vinculo->isLinked()) {
            return $this->renderPage($token, $vinculo, 'ALREADY_LINKED');
        }

        return $this->renderPage($token, $vinculo, 'READY');
    }

    public function actionVerificarDidit(string $token)
    {
        $vinculo = $this->service()->findVinculoByToken($token);
        if ($vinculo === null) {
            return $this->renderPage($token, null, 'NOT_FOUND');
        }
        if ($vinculo->isLinked()) {
            return $this->renderPage($token, $vinculo, 'ALREADY_LINKED');
        }

        try {
            $callback = Url::to(['/whatsapp-verificacion/verificar-retorno', 'token' => $token], true);
            $session = $this->service()->startDiditLink($vinculo, $callback);

            return $this->redirect($session['url']);
        } catch (\Throwable $e) {
            Yii::error('WhatsApp verificación: ' . $e->getMessage(), 'whatsapp');

            return $this->renderPage(
                $token,
                $vinculo,
                'READY',
                'No pudimos abrir la verificación. Probá de nuevo en unos segundos.'
            );
        }
    }

    public function actionVerificarRetorno(string $token)
    {
        $vinculo = $this->service()->findVinculoByToken($token);
        if ($vinculo === null) {
            return $this->renderPage($token, null, 'NOT_FOUND');
        }
        if ($vinculo->isLinked()) {
            return $this->renderPage($token, $vinculo, 'ALREADY_LINKED');
        }

        $sessionId = trim((string) Yii::$app->request->get('verificationSessionId', ''));
        if ($sessionId === '') {
            return $this->renderPage(
                $token,
                $vinculo,
                'READY',
                'No pudimos leer el resultado de la verificación. Probá de nuevo.'
            );
        }

        $result = $this->service()->completeDiditLink($vinculo, $sessionId);
        $state = match ($result['result']) {
            WhatsAppVerificationService::RESULT_LINKED => 'LINKED',
            WhatsAppVerificationService::RESULT_NO_ACCOUNT => 'NO_ACCOUNT',
            WhatsAppVerificationService::RESULT_RETRY => 'RETRY',
            WhatsAppVerificationService::RESULT_REJECTED => 'FAILED',
            default => 'ERROR',
        };

        return $this->renderPage(
            $token,
            $vinculo,
            $state,
            AssistantChannelCopy::t((string) $result['message_key'])
        );
    }

    private function renderPage(
        string $token,
        ?AsistenteWhatsappVinculo $vinculo,
        string $state,
        ?string $notice = null
    ) {
        $this->layout = 'loginLayout';

        $diditUrl = null;
        $retryUrl = null;
        if ($vinculo !== null && !$vinculo->isLinked()) {
            $diditUrl = Url::to(['/whatsapp-verificacion/verificar-didit', 'token' => $token]);
            $retryUrl = Url::to(['/whatsapp-verificacion/verificar', 'token' => $token]);
        }

        return $this->render('verificar', [
            'page' => [
                'state' => $state,
                'notice' => $notice,
                'didit_url' => $diditUrl,
                'retry_url' => $retryUrl,
            ],
        ]);
    }

    private function service(): WhatsAppVerificationService
    {
        return new WhatsAppVerificationService();
    }
}
