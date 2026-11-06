<?php

namespace common\components\Platform\Core\Service\Push;

use common\components\Shared\Infrastructure\Http\Auth\GoogleServiceAccountTokenProvider;
use common\components\Shared\Infrastructure\Http\Auth\OAuthTokenException;
use Yii;

/**
 * OAuth2 para FCM HTTP v1 (independiente de Vertex / google_cloud_*).
 *
 * El flujo es el mismo de {@see GoogleServiceAccountTokenProvider}; sólo cambia el scope.
 */
final class FcmGoogleAuth
{
    public const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** Clave histórica de cache: no invalidar tokens vigentes al desplegar. */
    public const CACHE_KEY_PREFIX = 'fcm_oauth_token';

    public static function getAccessToken(): string
    {
        $cfg = FcmPushConfig::get();
        $credentialsPath = isset($cfg['credentialsPath']) ? trim((string) $cfg['credentialsPath']) : '';
        if ($credentialsPath === '' || !is_file($credentialsPath)) {
            return '';
        }

        try {
            return (new GoogleServiceAccountTokenProvider(
                credentialsPath: $credentialsPath,
                scope: self::SCOPE,
                cacheKeyPrefix: self::CACHE_KEY_PREFIX,
                logCategory: FcmPushConfig::LOG_CATEGORY
            ))->token();
        } catch (OAuthTokenException $e) {
            Yii::error('FCM OAuth: ' . $e->getMessage(), FcmPushConfig::LOG_CATEGORY);

            return '';
        }
    }
}
