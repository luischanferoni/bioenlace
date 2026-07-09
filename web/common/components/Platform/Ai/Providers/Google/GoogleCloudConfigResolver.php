<?php

namespace common\components\Platform\Ai\Providers\Google;

use Yii;

/**
 * Resuelve parámetros de Google Cloud / Vertex desde Yii::$app->params
 * con rutas relativas y ubicaciones legacy tras mover secretos a common/config.
 */
final class GoogleCloudConfigResolver
{
    public const PARAMS_HINT = 'common/config/params-local.php (o frontend/config/params-local.php como override)';

    public static function credentialsPath(): ?string
    {
        $candidates = [];

        $fromParams = trim((string) (Yii::$app->params['google_cloud_credentials_path'] ?? ''));
        if ($fromParams !== '') {
            $candidates = array_merge($candidates, self::expandPathCandidates($fromParams));
        }

        $legacyFrontend = self::legacyFrontendCredentialsDir();
        if ($legacyFrontend !== null) {
            foreach (glob($legacyFrontend . '/*.json') ?: [] as $jsonFile) {
                $candidates[] = $jsonFile;
            }
        }

        $commonCredentialsDir = Yii::getAlias('@common/config/credentials', false);
        if ($commonCredentialsDir !== false && is_dir($commonCredentialsDir)) {
            foreach (glob($commonCredentialsDir . '/*.json') ?: [] as $jsonFile) {
                $candidates[] = $jsonFile;
            }
        }

        $seen = [];
        foreach ($candidates as $path) {
            $normalized = self::normalizePath($path);
            if ($normalized === '' || isset($seen[$normalized])) {
                continue;
            }
            $seen[$normalized] = true;
            if (is_readable($normalized)) {
                return $normalized;
            }
        }

        return null;
    }

    public static function projectId(): string
    {
        $projectId = trim((string) (Yii::$app->params['google_cloud_project_id'] ?? ''));
        if ($projectId !== '') {
            return $projectId;
        }

        $credentials = self::readCredentials();
        return trim((string) ($credentials['project_id'] ?? ''));
    }

    public static function region(): string
    {
        $region = trim((string) (Yii::$app->params['google_cloud_region'] ?? ''));
        if ($region !== '') {
            return $region;
        }

        return trim((string) (Yii::$app->params['vertex_ai_location'] ?? 'us-central1'));
    }

    public static function apiKey(): string
    {
        return trim((string) (Yii::$app->params['google_cloud_api_key'] ?? ''));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function readCredentials(): ?array
    {
        $path = self::credentialsPath();
        if ($path === null) {
            return null;
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    private static function expandPathCandidates(string $path): array
    {
        $out = [$path];

        if (self::isAbsolutePath($path)) {
            return $out;
        }

        $commonConfig = Yii::getAlias('@common/config', false);
        if ($commonConfig !== false) {
            $out[] = $commonConfig . '/' . ltrim($path, '/\\');
            $out[] = dirname($commonConfig) . '/' . ltrim($path, '/\\');
        }

        $frontendConfig = Yii::getAlias('@frontend/config', false);
        if ($frontendConfig !== false) {
            $out[] = $frontendConfig . '/' . ltrim($path, '/\\');
        }

        return $out;
    }

    private static function legacyFrontendCredentialsDir(): ?string
    {
        $frontendConfig = Yii::getAlias('@frontend/config', false);
        if ($frontendConfig === false) {
            return null;
        }

        $dir = $frontendConfig . '/credentials';
        return is_dir($dir) ? $dir : null;
    }

    private static function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }

    private static function normalizePath(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($path));
        if ($path === '') {
            return '';
        }

        $real = realpath($path);
        return $real !== false ? $real : $path;
    }
}
