<?php

namespace frontend\modules\api\v1\controllers;

use common\components\Platform\Assistant\WhatsApp\WhatsAppConfig;
use common\components\Platform\Assistant\WhatsApp\WhatsAppInboundService;
use Yii;
use yii\web\Response;

/**
 * Webhook Meta WhatsApp Cloud API (público; autenticación por firma HMAC).
 *
 * - GET  `whatsapp/webhook` — challenge de verificación
 * - POST `whatsapp/webhook` — mensajes entrantes → asistente paciente
 */
class WhatsAppWebhookController extends BaseController
{
    public static $authenticatorExcept = ['webhook', 'ping'];

    public function beforeAction($action)
    {
        $ok = parent::beforeAction($action);
        if (!$ok) {
            return false;
        }

        // Solo el challenge de Meta debe ser texto plano; ping/POST siguen JSON.
        if ($action->id === 'webhook' && Yii::$app->request->isGet) {
            Yii::$app->response->format = Response::FORMAT_RAW;
        }

        return true;
    }

    /**
     * Smoke público: confirmar que la ruta está desplegada (sin auth).
     *
     * @return array{ok: bool, service: string}
     */
    public function actionPing(): array
    {
        return [
            'ok' => true,
            'service' => 'whatsapp-webhook',
        ];
    }

    /**
     * GET verify + POST receive en la misma URL que exige Meta.
     *
     * @return mixed
     */
    public function actionWebhook()
    {
        if (Yii::$app->request->isGet) {
            return $this->handleVerify();
        }

        return $this->handleReceive();
    }

    /**
     * @return string|array
     */
    private function handleVerify()
    {
        $mode = (string) Yii::$app->request->get('hub_mode', Yii::$app->request->get('hub.mode', ''));
        $token = (string) Yii::$app->request->get('hub_verify_token', Yii::$app->request->get('hub.verify_token', ''));
        $challenge = (string) Yii::$app->request->get('hub_challenge', Yii::$app->request->get('hub.challenge', ''));

        // Yii puede parsear hub.mode como hub_mode según query string.
        if ($mode === '') {
            $mode = (string) ($_GET['hub.mode'] ?? '');
        }
        if ($token === '') {
            $token = (string) ($_GET['hub.verify_token'] ?? '');
        }
        if ($challenge === '') {
            $challenge = (string) ($_GET['hub.challenge'] ?? '');
        }

        $expected = WhatsAppConfig::get()['verifyToken'];
        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            Yii::$app->response->statusCode = 200;

            return $challenge;
        }

        Yii::$app->response->statusCode = 403;
        Yii::$app->response->format = Response::FORMAT_JSON;

        return ['success' => false, 'message' => 'Verify token inválido'];
    }

    /**
     * @return array{success: bool}
     */
    private function handleReceive(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!$this->verifySignature()) {
            Yii::$app->response->statusCode = 401;

            return ['success' => false, 'message' => 'Firma inválida'];
        }

        $raw = Yii::$app->request->getRawBody();
        $payload = [];
        if ($raw !== '') {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                $payload = is_array($decoded) ? $decoded : [];
            } catch (\Throwable $e) {
                Yii::warning('WhatsApp webhook JSON: ' . $e->getMessage(), WhatsAppConfig::LOG_CATEGORY);
            }
        }
        if ($payload === []) {
            $body = Yii::$app->request->getBodyParams();
            $payload = is_array($body) ? $body : [];
        }

        try {
            (new WhatsAppInboundService())->handleWebhookPayload($payload);
        } catch (\Throwable $e) {
            Yii::error('WhatsApp webhook: ' . $e->getMessage(), WhatsAppConfig::LOG_CATEGORY);
        }

        // Meta exige 200 rápido aunque falle el procesamiento interno.
        Yii::$app->response->statusCode = 200;

        return ['success' => true];
    }

    private function verifySignature(): bool
    {
        $appSecret = WhatsAppConfig::get()['appSecret'];
        if ($appSecret === '') {
            Yii::warning('WhatsApp webhook: appSecret vacío; se rechaza POST', WhatsAppConfig::LOG_CATEGORY);

            return false;
        }

        $header = (string) Yii::$app->request->headers->get('X-Hub-Signature-256', '');
        if (!preg_match('/^sha256=([a-f0-9]+)$/i', $header, $m)) {
            return false;
        }

        $expected = hash_hmac('sha256', Yii::$app->request->getRawBody(), $appSecret);

        return hash_equals(strtolower($expected), strtolower($m[1]));
    }
}
