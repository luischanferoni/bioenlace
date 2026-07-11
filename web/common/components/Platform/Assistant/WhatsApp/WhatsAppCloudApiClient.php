<?php

namespace common\components\Platform\Assistant\WhatsApp;

use Yii;
use yii\httpclient\Client;

/**
 * Cliente delgado a WhatsApp Cloud API (envio de mensajes).
 */
final class WhatsAppCloudApiClient
{
    /**
     * @param array<string, mixed> $message cuerpo `message` (sin messaging_product)
     */
    public function sendMessage(string $toWaId, array $message): bool
    {
        $cfg = WhatsAppConfig::get();
        if ($cfg['phoneNumberId'] === '' || $cfg['accessToken'] === '') {
            Yii::warning('WhatsAppCloudApiClient: faltan phoneNumberId/accessToken', WhatsAppConfig::LOG_CATEGORY);

            return false;
        }

        $to = preg_replace('/\D+/', '', $toWaId) ?? '';
        if ($to === '') {
            return false;
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            rawurlencode($cfg['apiVersion']),
            rawurlencode($cfg['phoneNumberId'])
        );

        $payload = array_merge(
            [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
            ],
            $message
        );

        try {
            $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($url)
                ->addHeaders([
                    'Authorization' => 'Bearer ' . $cfg['accessToken'],
                    'Content-Type' => 'application/json',
                ])
                ->setFormat(Client::FORMAT_JSON)
                ->setData($payload)
                ->send();

            if ($response->isOk) {
                return true;
            }

            Yii::error(
                'WhatsApp send: ' . ($response->statusCode ?? '?') . ' ' . ($response->content ?? ''),
                WhatsAppConfig::LOG_CATEGORY
            );
        } catch (\Throwable $e) {
            Yii::error('WhatsApp send: ' . $e->getMessage(), WhatsAppConfig::LOG_CATEGORY);
        }

        return false;
    }

    public function sendText(string $toWaId, string $body): bool
    {
        $text = trim($body);
        if ($text === '') {
            $text = '…';
        }

        return $this->sendMessage($toWaId, [
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => mb_substr($text, 0, 4096),
            ],
        ]);
    }

    /**
     * @param list<array{id: string, title: string}> $buttons máx. 3
     */
    public function sendReplyButtons(string $toWaId, string $body, array $buttons): bool
    {
        $rows = [];
        foreach (array_slice($buttons, 0, 3) as $b) {
            $id = trim((string) ($b['id'] ?? ''));
            $title = trim((string) ($b['title'] ?? ''));
            if ($id === '' || $title === '') {
                continue;
            }
            $rows[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => mb_substr($id, 0, 256),
                    'title' => mb_substr($title, 0, 20),
                ],
            ];
        }
        if ($rows === []) {
            return $this->sendText($toWaId, $body);
        }

        return $this->sendMessage($toWaId, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => mb_substr(trim($body) !== '' ? $body : 'Elegí una opción', 0, 1024)],
                'action' => ['buttons' => $rows],
            ],
        ]);
    }

    /**
     * @param list<array{id: string, title: string, description?: string}> $rows máx. 10
     */
    public function sendList(string $toWaId, string $body, string $buttonLabel, array $rows): bool
    {
        $items = [];
        foreach (array_slice($rows, 0, 10) as $r) {
            $id = trim((string) ($r['id'] ?? ''));
            $title = trim((string) ($r['title'] ?? ''));
            if ($id === '' || $title === '') {
                continue;
            }
            $item = [
                'id' => mb_substr($id, 0, 200),
                'title' => mb_substr($title, 0, 24),
            ];
            $desc = trim((string) ($r['description'] ?? ''));
            if ($desc !== '') {
                $item['description'] = mb_substr($desc, 0, 72);
            }
            $items[] = $item;
        }
        if ($items === []) {
            return $this->sendText($toWaId, $body);
        }

        $btn = trim($buttonLabel) !== '' ? $buttonLabel : 'Ver opciones';

        return $this->sendMessage($toWaId, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => mb_substr(trim($body) !== '' ? $body : 'Elegí una opción', 0, 1024)],
                'action' => [
                    'button' => mb_substr($btn, 0, 20),
                    'sections' => [
                        [
                            'title' => 'Opciones',
                            'rows' => $items,
                        ],
                    ],
                ],
            ],
        ]);
    }
}
