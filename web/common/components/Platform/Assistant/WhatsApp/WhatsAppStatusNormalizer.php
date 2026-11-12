<?php

namespace common\components\Platform\Assistant\WhatsApp;

/**
 * Normaliza el objeto `statuses` del webhook de Meta.
 *
 * No toca base: devuelve el shape que persiste {@see \common\models\Integrations\AsistenteWhatsappMensajeEstado}.
 */
final class WhatsAppStatusNormalizer
{
    /**
     * @param array<string, mixed> $status
     * @return array{wamid: string, wa_id: string, status: string, pricing_category: string|null, pricing_type: string|null, billable: int|null, conversation_id: string|null, event_at: string|null}|null
     */
    public static function normalize(array $status): ?array
    {
        $wamid = trim((string) ($status['id'] ?? ''));
        $state = trim((string) ($status['status'] ?? ''));
        if ($wamid === '' || $state === '') {
            return null;
        }

        $pricing = is_array($status['pricing'] ?? null) ? $status['pricing'] : [];
        $conversation = is_array($status['conversation'] ?? null) ? $status['conversation'] : [];
        $timestamp = $status['timestamp'] ?? null;

        return [
            'wamid' => $wamid,
            'wa_id' => trim((string) ($status['recipient_id'] ?? '')),
            'status' => $state,
            'pricing_category' => self::text($pricing['category'] ?? null),
            'pricing_type' => self::text($pricing['type'] ?? null),
            'billable' => array_key_exists('billable', $pricing)
                ? (int) (bool) $pricing['billable']
                : null,
            'conversation_id' => self::text($conversation['id'] ?? null),
            'event_at' => is_numeric($timestamp) ? date('Y-m-d H:i:s', (int) $timestamp) : null,
        ];
    }

    private static function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
