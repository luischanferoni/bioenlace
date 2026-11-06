<?php

namespace common\components\Domain\Clinical\CareCohort\Infrastructure\External\Gcs;

use common\components\Platform\Ai\Providers\Google\GoogleAuth;
use common\components\Shared\Infrastructure\Http\HttpApiException;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use Yii;

/**
 * ACL de Google Cloud Storage para los JSONL de batch de Vertex.
 *
 * Transporte y token vienen de `Shared/Infrastructure/Http`; acá sólo viven los endpoints
 * de objetos y el contrato "devolver false/null ante error".
 */
final class GcsApiClient
{
    public const BASE_URL = 'https://storage.googleapis.com';

    public const LOG_CATEGORY = 'care-cohort';

    public function uploadString(
        string $bucket,
        string $objectName,
        string $content,
        string $contentType = 'application/jsonl'
    ): bool {
        $token = GoogleAuth::getAccessToken();
        if ($token === '') {
            Yii::warning('GcsApiClient: sin token OAuth', self::LOG_CATEGORY);

            return false;
        }

        try {
            $this->client($token)->post(
                '/upload/storage/v1/b/' . rawurlencode($bucket) . '/o',
                $content,
                ['Content-Type' => $contentType],
                ['uploadType' => 'media', 'name' => $objectName]
            );

            return true;
        } catch (HttpApiException $e) {
            Yii::error('GcsApiClient upload: ' . $e->getMessage(), self::LOG_CATEGORY);

            return false;
        }
    }

    /** @return string|null Contenido del objeto */
    public function downloadString(string $bucket, string $objectName): ?string
    {
        $token = GoogleAuth::getAccessToken();
        if ($token === '') {
            return null;
        }

        try {
            $response = $this->client($token)->get(
                '/storage/v1/b/' . rawurlencode($bucket) . '/o/' . rawurlencode($objectName),
                ['alt' => 'media']
            );
        } catch (HttpApiException $e) {
            Yii::error('GcsApiClient download: ' . $e->getMessage(), self::LOG_CATEGORY);

            return null;
        }

        return $response->rawBody();
    }

    /**
     * Lista nombres de objetos bajo un prefijo (paginación simple, hasta $limit).
     *
     * @return list<string>
     */
    public function listObjectNames(string $bucket, string $prefix, int $limit = 50): array
    {
        $token = GoogleAuth::getAccessToken();
        if ($token === '') {
            return [];
        }

        try {
            $response = $this->client($token)->get(
                '/storage/v1/b/' . rawurlencode($bucket) . '/o',
                [
                    'prefix' => $prefix,
                    'maxResults' => max(1, min(1000, $limit)),
                ]
            );
        } catch (HttpApiException $e) {
            Yii::error('GcsApiClient list: ' . $e->getMessage(), self::LOG_CATEGORY);

            return [];
        }

        $items = $response->body()['items'] ?? null;
        if (!is_array($items)) {
            return [];
        }

        $names = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Descarga y concatena todas las líneas JSONL bajo un prefijo GCS.
     *
     * @return list<string>
     */
    public function downloadJsonlLinesUnderPrefix(string $bucket, string $objectPrefix, int $maxObjects = 20): array
    {
        $prefix = rtrim($objectPrefix, '/') . '/';
        $lines = [];

        foreach ($this->listObjectNames($bucket, $prefix, $maxObjects) as $objectName) {
            if (substr(strtolower($objectName), -6) !== '.jsonl') {
                continue;
            }
            $raw = $this->downloadString($bucket, $objectName);
            if ($raw === null || trim($raw) === '') {
                continue;
            }
            foreach (explode("\n", $raw) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        return $lines;
    }

    private function client(string $token): JsonApiClient
    {
        return new JsonApiClient(
            baseUrl: self::BASE_URL,
            defaultHeaders: ['Authorization' => 'Bearer ' . $token],
            connectTimeout: 10,
            timeout: 120,
            logCategory: self::LOG_CATEGORY,
            provider: 'gcs',
            providerLabel: 'Google Cloud Storage'
        );
    }
}
