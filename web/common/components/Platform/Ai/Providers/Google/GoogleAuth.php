<?php

namespace common\components\Platform\Ai\Providers\Google;

use common\components\Shared\Infrastructure\Http\Auth\GoogleServiceAccountTokenProvider;
use common\components\Shared\Infrastructure\Http\Auth\OAuthTokenException;
use Yii;

/**
 * Token de Google Cloud para Vertex/Gemini.
 *
 * Si hay API key configurada no hace falta token (devuelve `''`). La firma del JWT y el
 * cache viven en {@see GoogleServiceAccountTokenProvider}.
 */
final class GoogleAuth
{
    public const SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    /** Clave histórica de cache: no invalidar tokens vigentes al desplegar. */
    public const CACHE_KEY_PREFIX = 'google_oauth_token';

    public static function getAccessToken(): string
    {
        if (GoogleCloudConfigResolver::apiKey() !== '') {
            return '';
        }

        $credentialsPath = GoogleCloudConfigResolver::credentialsPath();
        if ($credentialsPath === null) {
            Yii::warning(
                'Google Cloud credentials no encontradas. Configure google_cloud_credentials_path o google_cloud_api_key en '
                . GoogleCloudConfigResolver::PARAMS_HINT,
                'ia-manager'
            );

            return '';
        }

        try {
            return (new GoogleServiceAccountTokenProvider(
                credentialsPath: $credentialsPath,
                scope: self::SCOPE,
                cacheKeyPrefix: self::CACHE_KEY_PREFIX,
                logCategory: 'ia-manager'
            ))->token();
        } catch (OAuthTokenException $e) {
            Yii::error($e->getMessage(), 'ia-manager');

            return '';
        }
    }
}
