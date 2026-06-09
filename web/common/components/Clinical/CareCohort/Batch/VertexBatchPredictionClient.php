<?php

namespace common\components\Clinical\CareCohort\Batch;

use common\components\Ai\Providers\Google\GoogleAuth;
use Yii;
use yii\httpclient\Client;

final class VertexBatchPredictionClient
{
    /**
     * @return array{name: string}|null
     */
    public function createGeminiBatchJob(string $gcsInputUri, string $gcsOutputPrefix, ?string $model = null): ?array
    {
        $projectId = Yii::$app->params['google_cloud_project_id'] ?? '';
        $location = Yii::$app->params['google_cloud_region'] ?? 'us-central1';
        $model = $model ?? (Yii::$app->params['vertex_ai_model'] ?? 'gemini-2.5-flash-lite');

        if ($projectId === '') {
            Yii::warning('Vertex batch: falta google_cloud_project_id', 'care-cohort');

            return null;
        }

        $token = GoogleAuth::getAccessToken();
        if ($token === '') {
            return null;
        }

        $url = "https://{$location}-aiplatform.googleapis.com/v1/projects/{$projectId}/locations/{$location}/batchPredictionJobs";

        $body = [
            'displayName' => 'bioenlace-care-pack-' . date('Ymd-His'),
            'model' => 'publishers/google/models/' . $model,
            'inputConfig' => [
                'instancesFormat' => 'jsonl',
                'gcsSource' => [
                    'uris' => [$gcsInputUri],
                ],
            ],
            'outputConfig' => [
                'predictionsFormat' => 'jsonl',
                'gcsDestination' => [
                    'outputUriPrefix' => rtrim($gcsOutputPrefix, '/') . '/',
                ],
            ],
        ];

        try {
            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($url)
                ->addHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ])
                ->setContent(json_encode($body))
                ->send();

            if (!$response->isOk) {
                Yii::error('Vertex batch create: ' . $response->statusCode . ' ' . ($response->content ?? ''), 'care-cohort');

                return null;
            }

            $data = json_decode($response->content ?? '', true);

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Yii::error('Vertex batch create excepción: ' . $e->getMessage(), 'care-cohort');

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getJob(string $jobName): ?array
    {
        $token = GoogleAuth::getAccessToken();
        if ($token === '') {
            return null;
        }

        $url = 'https://' . $this->apiHostFromJobName($jobName) . '/v1/' . ltrim($jobName, '/');

        try {
            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('GET')
                ->setUrl($url)
                ->addHeaders(['Authorization' => 'Bearer ' . $token])
                ->send();

            if ($response->isOk) {
                $data = json_decode($response->content ?? '', true);

                return is_array($data) ? $data : null;
            }
        } catch (\Throwable $e) {
            Yii::error('Vertex batch get: ' . $e->getMessage(), 'care-cohort');
        }

        return null;
    }

    private function apiHostFromJobName(string $jobName): string
    {
        if (preg_match('#locations/([^/]+)/#', $jobName, $m)) {
            return $m[1] . '-aiplatform.googleapis.com';
        }

        $location = Yii::$app->params['google_cloud_region'] ?? 'us-central1';

        return $location . '-aiplatform.googleapis.com';
    }
}
