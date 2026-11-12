<?php

namespace common\components\Platform\Assistant\WhatsApp;

/**
 * Token opaco de vinculación: se envía en la URL y en la BD solo queda el hash.
 */
final class WhatsAppLinkToken
{
    /**
     * @return array{token: string, hash: string, expires_at: string}
     */
    public static function generate(int $ttlSeconds): array
    {
        $token = bin2hex(random_bytes(32));

        return [
            'token' => $token,
            'hash' => self::hash($token),
            'expires_at' => date('Y-m-d H:i:s', time() + max(1, $ttlSeconds)),
        ];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', trim($token));
    }

    public static function isExpired(?string $expiresAt, ?int $now = null): bool
    {
        $expiresAt = trim((string) $expiresAt);
        if ($expiresAt === '') {
            return true;
        }

        return strtotime($expiresAt) <= ($now ?? time());
    }
}
