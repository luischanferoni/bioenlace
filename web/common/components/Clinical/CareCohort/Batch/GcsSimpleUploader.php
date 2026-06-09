<?php

namespace common\components\Clinical\CareCohort\Batch;

use common\components\Ai\Providers\Google\GoogleAuth;
use Yii;
use yii\httpclient\Client;

/**
 * Subida simple a GCS (uploadType=media) para JSONL de batch Vertex.
 */
final class GcsSimpleUploader
{
    public function uploadString(string $bucket, string $objectName, string $content, string $contentType = 'application/jsonl'): bool
    {
        $token = GoogleAuth::getAccessToken();
        if ($token === '') {
            Yii::warning('GcsSimpleUploader: sin token OAuth', 'care-cohort');

            return false;
        }

        $encodedName = rawurlencode($objectName);
        $url = 'https://storage.googleapis.com/upload/storage/v1/b/'
            . rawurlencode($bucket)
            . '/o?uploadType=media&name=' . $encodedName;

        try {
            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($url)
                ->addHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => $contentType,
                ])
                ->setContent($content)
                ->send();

            if ($response->isOk) {
                return true;
            }

            Yii::error(
                'GcsSimpleUploader falló: ' . $response->statusCode . ' ' . ($response->content ?? ''),
                'care-cohort'
            );
        } catch (\Throwable $e) {
            Yii::error('GcsSimpleUploader excepción: ' . $e->getMessage(), 'care-cohort');
        }

        return false;
    }

    /**
     * @return string|null Contenido del objeto
     */
    public function downloadString(string $bucket, string $objectName): ?string
    {
        $token = GoogleAuth::getAccessToken();
        if ($token === '') {
            return null;
        }

        $url = 'https://storage.googleapis.com/storage/v1/b/'
            . rawurlencode($bucket)
            . '/o/'
            . rawurlencode($objectName)
            . '?alt=media';

        try {
            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('GET')
                ->setUrl($url)
                ->addHeaders(['Authorization' => 'Bearer ' . $token])
                ->send();

            if ($response->isOk) {
                return (string) $response->content;
            }
        } catch (\Throwable $e) {
            Yii::error('GcsSimpleUploader download: ' . $e->getMessage(), 'care-cohort');
        }

        return null;
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

        $url = 'https://storage.googleapis.com/storage/v1/b/'
            . rawurlencode($bucket)
            . '/o?prefix=' . rawurlencode($prefix)
            . '&maxResults=' . max(1, min(1000, $limit));

        try {
            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('GET')
                ->setUrl($url)
                ->addHeaders(['Authorization' => 'Bearer ' . $token])
                ->send();

            if (!$response->isOk) {
                return [];
            }

            $data = json_decode($response->content ?? '', true);
            if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
                return [];
            }

            $names = [];
            foreach ($data['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $name = trim((string) ($item['name'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }
            }

            return $names;
        } catch (\Throwable $e) {
            Yii::error('GcsSimpleUploader list: ' . $e->getMessage(), 'care-cohort');
        }

        return [];
    }

    /**
     * Descarga y concatena todas las líneas JSONL bajo un prefijo GCS.
     *
     * @return list<string>
     */
    public function downloadJsonlLinesUnderPrefix(string $bucket, string $objectPrefix, int $maxObjects = 20): array
    {
        $prefix = rtrim($objectPrefix, '/') . '/';
        $objects = $this->listObjectNames($bucket, $prefix, $maxObjects);
        $lines = [];

        foreach ($objects as $objectName) {
            $lower = strtolower($objectName);
            if (substr($lower, -6) !== '.jsonl') {
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
}
