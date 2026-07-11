<?php

namespace common\components\Platform\Assistant\WhatsApp;

use common\components\Platform\Assistant\Chat\ChatOrchestrator;
use common\components\Platform\Core\Service\Actions\CommonActionsService;
use common\models\AsistenteWhatsappMensaje;
use common\models\AsistenteWhatsappVinculo;
use Yii;
use yii\web\Request;

/**
 * Orquesta webhook Meta → identidad → ChatOrchestrator → render WhatsApp.
 */
final class WhatsAppInboundService
{
    private WhatsAppIdentityService $identity;
    private WhatsAppEnvelopeRenderer $renderer;
    private WhatsAppCloudApiClient $api;

    public function __construct(
        ?WhatsAppIdentityService $identity = null,
        ?WhatsAppEnvelopeRenderer $renderer = null,
        ?WhatsAppCloudApiClient $api = null
    ) {
        $this->identity = $identity ?? new WhatsAppIdentityService();
        $this->api = $api ?? new WhatsAppCloudApiClient();
        $this->renderer = $renderer ?? new WhatsAppEnvelopeRenderer($this->api);
    }

    /**
     * @param array<string, mixed> $payload body JSON de Meta
     */
    public function handleWebhookPayload(array $payload): void
    {
        $entries = isset($payload['entry']) && is_array($payload['entry']) ? $payload['entry'] : [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $changes = isset($entry['changes']) && is_array($entry['changes']) ? $entry['changes'] : [];
            foreach ($changes as $change) {
                if (!is_array($change)) {
                    continue;
                }
                $value = isset($change['value']) && is_array($change['value']) ? $change['value'] : [];
                $messages = isset($value['messages']) && is_array($value['messages']) ? $value['messages'] : [];
                $contacts = isset($value['contacts']) && is_array($value['contacts']) ? $value['contacts'] : [];
                $contactWaId = '';
                if ($contacts !== [] && is_array($contacts[0] ?? null)) {
                    $contactWaId = trim((string) (($contacts[0]['wa_id'] ?? '') ?: ''));
                }
                foreach ($messages as $message) {
                    if (is_array($message)) {
                        $this->handleInboundMessage($message, $contactWaId);
                    }
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $message
     */
    private function handleInboundMessage(array $message, string $contactWaId): void
    {
        $waId = trim((string) ($message['from'] ?? $contactWaId));
        $wamid = trim((string) ($message['id'] ?? ''));
        if ($waId === '' || $wamid === '') {
            return;
        }

        if (!AsistenteWhatsappMensaje::claim($wamid, $waId)) {
            return;
        }

        $parsed = $this->parseInboundContent($message);
        $text = $parsed['text'];
        $callback = $parsed['callback'];

        $identityResult = $this->identity->resolveOrBootstrap($waId, $waId, $text);
        $status = (string) ($identityResult['status'] ?? '');

        if ($status !== 'linked' && $status !== 'just_linked') {
            $msg = trim((string) ($identityResult['message'] ?? ''));
            if ($msg !== '') {
                $sent = $this->api->sendText($waId, $msg);
                if (!$sent) {
                    Yii::error(
                        'WhatsAppInboundService: no se pudo enviar reply de identidad status='
                        . $status . ' wa_id=' . $waId,
                        WhatsAppConfig::LOG_CATEGORY
                    );
                }
            }

            return;
        }

        /** @var AsistenteWhatsappVinculo $vinculo */
        $vinculo = $identityResult['vinculo'];
        $userId = (int) $vinculo->user_id;
        $idPersona = (int) $vinculo->id_persona;

        if (!$this->identity->establishYiiIdentity($userId, $idPersona)) {
            $this->api->sendText($waId, 'No pudimos iniciar tu sesión. Probá más tarde o usá la app Bioenlace.');

            return;
        }

        $this->setAppClientHeader();

        if ($status === 'just_linked' || !empty($identityResult['menu']) || $this->isMenuCommand($text)) {
            $intro = trim((string) ($identityResult['message'] ?? ''));
            if ($intro !== '') {
                $this->api->sendText($waId, $intro);
            }
            $this->sendShortcutsMenu($waId, $userId);
            if ($status === 'just_linked' || $this->isMenuCommand($text)) {
                return;
            }
        }

        if ($callback === null && trim($text) === '') {
            $this->api->sendText(
                $waId,
                'Por ahora solo leo texto y opciones del menú. Escribí tu pedido o pedí «menú».'
            );

            return;
        }

        $body = $this->buildOrchestratorBody($vinculo, $text, $callback);
        try {
            $envelope = ChatOrchestrator::handle($body, $userId);
        } catch (\Throwable $e) {
            Yii::error(
                'WhatsAppInboundService orchestrator: ' . $e->getMessage(),
                WhatsAppConfig::LOG_CATEGORY
            );
            $this->api->sendText($waId, 'Hubo un error al procesar tu mensaje. Intentá de nuevo.');

            return;
        }

        $this->persistFlowSession($vinculo, $envelope);
        $this->renderer->renderAndSend($waId, $envelope);
    }

    /**
     * @param array<string, mixed> $message
     * @return array{text: string, callback: array{type: string, value: string}|null}
     */
    private function parseInboundContent(array $message): array
    {
        $type = trim((string) ($message['type'] ?? 'text'));
        if ($type === 'text') {
            $body = isset($message['text']) && is_array($message['text']) ? $message['text'] : [];

            return [
                'text' => trim((string) ($body['body'] ?? '')),
                'callback' => null,
            ];
        }

        if ($type === 'interactive') {
            $interactive = isset($message['interactive']) && is_array($message['interactive'])
                ? $message['interactive']
                : [];
            $itype = trim((string) ($interactive['type'] ?? ''));
            $payload = '';
            $title = '';
            if ($itype === 'button_reply') {
                $reply = isset($interactive['button_reply']) && is_array($interactive['button_reply'])
                    ? $interactive['button_reply']
                    : [];
                $payload = trim((string) ($reply['id'] ?? ''));
                $title = trim((string) ($reply['title'] ?? ''));
            } elseif ($itype === 'list_reply') {
                $reply = isset($interactive['list_reply']) && is_array($interactive['list_reply'])
                    ? $interactive['list_reply']
                    : [];
                $payload = trim((string) ($reply['id'] ?? ''));
                $title = trim((string) ($reply['title'] ?? ''));
            }

            $decoded = WhatsAppEnvelopeRenderer::decodeCallbackPayload($payload);

            return [
                'text' => $title,
                'callback' => $decoded,
            ];
        }

        return [
            'text' => '',
            'callback' => null,
        ];
    }

    /**
     * @param array{type: string, value: string}|null $callback
     * @return array<string, mixed>
     */
    private function buildOrchestratorBody(AsistenteWhatsappVinculo $vinculo, string $text, ?array $callback): array
    {
        $session = $vinculo->getFlowSessionArray();
        $body = [
            'senderId' => (string) $vinculo->user_id,
        ];

        if ($callback !== null) {
            if ($callback['type'] === 'intent_id') {
                $body['intent_id'] = $callback['value'];
                $body['draft'] = [];

                return $body;
            }
            if ($callback['type'] === 'action_id') {
                $body['action_id'] = $callback['value'];

                return $body;
            }
            if ($callback['type'] === 'hint') {
                $text = $callback['value'];
            }
        }

        $intentId = trim((string) ($session['intent_id'] ?? ''));
        if ($intentId !== '') {
            $body['intent_id'] = $intentId;
            $body['subintent_id'] = trim((string) ($session['subintent_id'] ?? ''));
            $draft = isset($session['draft']) && is_array($session['draft']) ? $session['draft'] : [];
            $body['draft'] = $draft;
            $body['content'] = $text;

            return $body;
        }

        if ($text !== '') {
            $body['content'] = $text;
        }

        return $body;
    }

    /**
     * @param array<string, mixed> $envelope
     */
    private function persistFlowSession(AsistenteWhatsappVinculo $vinculo, array $envelope): void
    {
        $kind = trim((string) ($envelope['kind'] ?? ''));
        if ($kind !== 'flow') {
            $vinculo->setFlowSessionArray(null);
            $vinculo->save(false);

            return;
        }

        $session = isset($envelope['session']) && is_array($envelope['session']) ? $envelope['session'] : [];
        $intentId = trim((string) ($session['intent_id'] ?? ''));
        if ($intentId === '') {
            $vinculo->setFlowSessionArray(null);
            $vinculo->save(false);

            return;
        }

        $prev = $vinculo->getFlowSessionArray();
        $draft = isset($prev['draft']) && is_array($prev['draft']) ? $prev['draft'] : [];
        $delta = isset($session['draft_delta']) && is_array($session['draft_delta']) ? $session['draft_delta'] : [];
        foreach ($delta as $k => $v) {
            $draft[$k] = $v;
        }

        $vinculo->setFlowSessionArray([
            'intent_id' => $intentId,
            'subintent_id' => trim((string) ($session['subintent_id'] ?? '')),
            'draft' => $draft,
        ]);
        $vinculo->save(false);
    }

    private function sendShortcutsMenu(string $waId, int $userId): void
    {
        $payload = CommonActionsService::getFormattedForUser(
            $userId,
            CommonActionsService::DEFAULT_LIMIT,
            WhatsAppConfig::APP_CLIENT_ID
        );
        $rows = [];
        foreach ($payload['actions'] as $action) {
            if (!is_array($action)) {
                continue;
            }
            $actionId = trim((string) ($action['action_id'] ?? ''));
            $name = trim((string) ($action['name'] ?? $actionId));
            if ($actionId === '' || $name === '') {
                continue;
            }
            $rows[] = [
                'id' => WhatsAppEnvelopeRenderer::encodeActionPayload($actionId),
                'title' => $name,
                'description' => mb_substr(trim((string) ($action['description'] ?? '')), 0, 72),
            ];
        }

        if ($rows === []) {
            $this->api->sendText(
                $waId,
                'Escribí lo que necesitás (por ejemplo: cancelar un turno) o pedí ayuda.'
            );

            return;
        }

        $this->renderer->sendMenu(
            $waId,
            '¿En qué te ayudo? Elegí una opción o escribí tu pedido.',
            $rows
        );
    }

    private function isMenuCommand(string $text): bool
    {
        $t = mb_strtolower(trim($text));

        return in_array($t, ['menu', 'menú', 'inicio', 'hola', 'hi', 'hey', 'buenas', 'buen dia', 'buen día'], true);
    }

    private function setAppClientHeader(): void
    {
        try {
            $request = Yii::$app->request;
            if (!$request instanceof Request) {
                return;
            }
            // Canal paciente: no defaulting a X-Client=web (oculta intents como-paciente y mezcla PES).
            $request->headers->set('X-App-Client', WhatsAppConfig::APP_CLIENT_ID);
            $request->headers->set('X-Client', 'mobile');
        } catch (\Throwable $e) {
            Yii::debug('WhatsAppInboundService header: ' . $e->getMessage(), WhatsAppConfig::LOG_CATEGORY);
        }
    }
}
