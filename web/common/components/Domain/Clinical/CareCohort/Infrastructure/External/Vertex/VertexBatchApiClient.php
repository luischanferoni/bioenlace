<?php

namespace common\components\Domain\Clinical\CareCohort\Infrastructure\External\Vertex;

use common\components\Platform\Ai\Providers\Google\GoogleAuth;
use common\components\Platform\Ai\Providers\Google\GoogleCloudConfigResolver;
use common\components\Shared\Infrastructure\Http\HttpApiException;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use Yii;

/**
 * ACL de Vertex AI para batch prediction de Gemini (job por JSONL en GCS).
 *
 * Proyecto y región salen de {@see GoogleCloudConfigResolver}: params o credenciales.
 */
final class VertexBatchApiClient
{
    public const LOG_CATEGORY = 'care-cohort';

    /**
     * @return array{name: string}|null
     */
    public function createGeminiBatchJob(string $gcsInputUri, string $gcsOutputPrefix, ?string $model = null): ?array
    {
        $projectId = GoogleCloudConfigResolver::projectId();
        $location = GoogleCloudConfigResolver::region();
        $model = $model ?? (string) (Yii::$app->params['vertex_ai_model'] ?? 'gemini-2.5-flash-lite');

        if ($projectId === '') {
            Yii::warning('Vertex batch: falta google_cloud_project_id', self::LOG_CATEGORY);

            return null;
        }

        $token = GoogleAuth::getAccessToken();
        if ($token === '') {
            return null;
        }

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
            $response = $this->client($token, $location)->post(
                '/v1/projects/' . rawurlencode($projectId)
                    . '/locations/' . rawurlencode($location)
                    . '/batchPredictionJobs',
                $body
            );
        } catch (HttpApiException $e) {
            Yii::error('Vertex batch create: ' . $e->getMessage(), self::LOG_CATEGORY);

            return null;
        }

        $data = $response->body();

        return $data === [] ? null : $data;
    }

    /** @return array<string, mixed>|null */
    public function getJob(string $jobName): ?array
    {
        $token = GoogleAuth::getAccessToken();
        if ($token === '') {
            return null;
        }

        $location = $this->locationFromJobName($jobName);

        try {
            $response = $this->client($token, $location)->get('/v1/' . ltrim($jobName, '/'));
        } catch (HttpApiException $e) {
            Yii::error('Vertex batch get: ' . $e->getMessage(), self::LOG_CATEGORY);

            return null;
        }

        $data = $response->body();

        return $data === [] ? null : $data;
    }

    private function client(string $token, string $location): JsonApiClient
    {
        return new JsonApiClient(
            baseUrl: 'https://' . $location . '-aiplatform.googleapis.com',
            defaultHeaders: ['Authorization' => 'Bearer ' . $token],
            connectTimeout: 10,
            timeout: 60,
            logCategory: self::LOG_CATEGORY,
            provider: 'vertex-batch',
            providerLabel: 'Vertex AI'
        );
    }

    private function locationFromJobName(string $jobName): string
    {
        if (preg_match('#locations/([^/]+)/#', $jobName, $m)) {
            return $m[1];
        }

        return GoogleCloudConfigResolver::region();
    }
}
