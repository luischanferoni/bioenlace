<?php

namespace common\components\Platform\Assistant\WhatsApp;

use common\components\Platform\Assistant\Copy\AssistantChannelCopy;

/**
 * Traduce el sobre público del asistente a mensajes Cloud API (sin conocer intents de dominio).
 */
final class WhatsAppEnvelopeRenderer
{
    private WhatsAppCloudApiClient $client;

    public function __construct(?WhatsAppCloudApiClient $client = null)
    {
        $this->client = $client ?? new WhatsAppCloudApiClient();
    }

    /**
     * @param array<string, mixed> $envelope
     */
    public function renderAndSend(string $toWaId, array $envelope): void
    {
        $kind = isset($envelope['kind']) ? trim((string) $envelope['kind']) : '';

        if ($kind === 'interactive') {
            $this->sendInteractive($toWaId, $envelope);

            return;
        }

        if ($kind === 'flow') {
            $this->sendFlow($toWaId, $envelope);

            return;
        }

        $text = trim((string) ($envelope['text'] ?? ''));
        if ($text === '' && isset($envelope['error'])) {
            $text = trim((string) $envelope['error']);
        }
        $this->client->sendText($toWaId, $text !== '' ? $text : 'Listo.');
    }

    /**
     * Menú de atajos (lista WA).
     *
     * @param list<array{id: string, title: string, description?: string}> $rows
     */
    public function sendMenu(string $toWaId, string $body, array $rows): void
    {
        if (count($rows) <= 3) {
            $buttons = [];
            foreach ($rows as $r) {
                $buttons[] = [
                    'id' => (string) ($r['id'] ?? ''),
                    'title' => (string) ($r['title'] ?? ''),
                ];
            }
            $this->client->sendReplyButtons($toWaId, $body, $buttons);

            return;
        }

        $this->client->sendList($toWaId, $body, 'Menú', $rows);
    }

    /**
     * @param array<string, mixed> $envelope
     */
    private function sendInteractive(string $toWaId, array $envelope): void
    {
        $text = trim((string) ($envelope['text'] ?? 'Elegí una opción'));
        $buttons = isset($envelope['buttons']) && is_array($envelope['buttons']) ? $envelope['buttons'] : [];
        $options = [];
        foreach ($buttons as $b) {
            if (!is_array($b)) {
                continue;
            }
            $label = trim((string) ($b['label'] ?? ''));
            $intentId = trim((string) ($b['intent_id'] ?? ''));
            if ($label === '' || $intentId === '') {
                continue;
            }
            $options[] = [
                'id' => self::encodeIntentPayload($intentId),
                'title' => $label,
            ];
        }

        if ($options === []) {
            $this->client->sendText($toWaId, $text);

            return;
        }

        if (count($options) <= 3) {
            $this->client->sendReplyButtons($toWaId, $text, $options);

            return;
        }

        $this->client->sendList($toWaId, $text, 'Opciones', $options);
    }

    /**
     * @param array<string, mixed> $envelope
     */
    private function sendFlow(string $toWaId, array $envelope): void
    {
        $text = trim((string) ($envelope['text'] ?? ''));
        $step = isset($envelope['step']) && is_array($envelope['step']) ? $envelope['step'] : [];
        $clientOpen = isset($step['client_open']) && is_array($step['client_open']) ? $step['client_open'] : null;

        if ($clientOpen !== null && $clientOpen !== []) {
            $deepLink = rtrim(WhatsAppConfig::get()['appDeepLinkBase'], '/') . '/';
            $msg = $text !== '' ? $text : AssistantChannelCopy::t('open_ui_button');
            $suffix = AssistantChannelCopy::t('open_ui_deep_link_suffix', ['url' => $deepLink]);
            if ($suffix !== '') {
                $msg .= $suffix;
            }
            $this->client->sendText($toWaId, $msg);

            return;
        }

        $options = $this->flowActionOptions($envelope);
        if ($options === []) {
            $this->client->sendText(
                $toWaId,
                $text !== '' ? $text : AssistantChannelCopy::t('interactive_pick_one')
            );

            return;
        }

        if (count($options) <= 3) {
            $this->client->sendReplyButtons(
                $toWaId,
                $text !== '' ? $text : AssistantChannelCopy::t('interactive_pick_one'),
                $options
            );

            return;
        }

        $this->client->sendList(
            $toWaId,
            $text !== '' ? $text : AssistantChannelCopy::t('interactive_pick_one'),
            'Opciones',
            $options
        );
    }

    /**
     * @param array<string, mixed> $envelope
     * @return list<array{id: string, title: string}>
     */
    private function flowActionOptions(array $envelope): array
    {
        $options = [];
        $hints = isset($envelope['hints']) && is_array($envelope['hints']) ? $envelope['hints'] : [];
        foreach ($hints as $h) {
            if (!is_array($h)) {
                continue;
            }
            $value = trim((string) ($h['value'] ?? ''));
            $label = trim((string) ($h['label'] ?? $value));
            if ($value === '' || $label === '') {
                continue;
            }
            $options[] = [
                'id' => self::encodeHintPayload($value),
                'title' => $label,
            ];
        }

        return array_slice($options, 0, 10);
    }

    public static function encodeIntentPayload(string $intentId): string
    {
        return 'i:' . mb_substr(trim($intentId), 0, 250);
    }

    public static function encodeActionPayload(string $actionId): string
    {
        return 'a:' . mb_substr(trim($actionId), 0, 250);
    }

    public static function encodeHintPayload(string $value): string
    {
        return 'h:' . mb_substr(trim($value), 0, 250);
    }

    /**
     * @return array{type: string, value: string}|null
     */
    public static function decodeCallbackPayload(string $payload): ?array
    {
        $payload = trim($payload);
        if ($payload === '') {
            return null;
        }
        if (str_starts_with($payload, 'i:')) {
            return ['type' => 'intent_id', 'value' => substr($payload, 2)];
        }
        if (str_starts_with($payload, 'a:')) {
            return ['type' => 'action_id', 'value' => substr($payload, 2)];
        }
        if (str_starts_with($payload, 'h:')) {
            return ['type' => 'hint', 'value' => substr($payload, 2)];
        }

        return null;
    }
}
