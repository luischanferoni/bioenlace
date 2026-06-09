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
}
