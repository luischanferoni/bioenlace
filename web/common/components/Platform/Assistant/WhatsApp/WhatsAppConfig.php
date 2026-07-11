<?php

namespace common\components\Platform\Assistant\WhatsApp;

use Yii;

/**
 * Params `whatsapp.*` (defaults en common/config/params.php; secretos en params-local).
 */
final class WhatsAppConfig
{
    public const APP_CLIENT_ID = 'whatsapp-paciente';

    public const LOG_CATEGORY = 'whatsapp';

    /**
     * @return array{
     *   phoneNumberId: string,
     *   accessToken: string,
     *   verifyToken: string,
     *   appSecret: string,
     *   apiVersion: string,
     *   appDeepLinkBase: string
     * }
     */
    public static function get(): array
    {
        $raw = Yii::$app->params['whatsapp'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }

        return [
            'phoneNumberId' => trim((string) ($raw['phoneNumberId'] ?? '')),
            'accessToken' => trim((string) ($raw['accessToken'] ?? '')),
            'verifyToken' => trim((string) ($raw['verifyToken'] ?? '')),
            'appSecret' => trim((string) ($raw['appSecret'] ?? '')),
            'apiVersion' => trim((string) ($raw['apiVersion'] ?? 'v21.0')) ?: 'v21.0',
            'appDeepLinkBase' => trim((string) ($raw['appDeepLinkBase'] ?? 'https://app.bioenlace.io/'))
                ?: 'https://app.bioenlace.io/',
        ];
    }

    public static function isOutboundConfigured(): bool
    {
        $c = self::get();

        return $c['phoneNumberId'] !== '' && $c['accessToken'] !== '';
    }
}
