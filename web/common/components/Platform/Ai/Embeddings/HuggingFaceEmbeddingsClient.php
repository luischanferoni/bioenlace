<?php

namespace common\components\Platform\Ai\Embeddings;

use common\components\Shared\Infrastructure\Http\HttpApiException;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use Yii;

/**
 * Feature extraction de HuggingFace Inference Providers.
 *
 * La cuota gratuita mensual es limitada: si se agota responde HTTP 402.
 */
final class HuggingFaceEmbeddingsClient
{
    public const BASE_URL = 'https://router.huggingface.co/hf-inference/models/';

    public const LOG_CATEGORY = 'embeddings';

    /**
     * @param string|array $inputs Texto o lista de textos
     * @param string $modelo
     * @return array|null
     */
    public static function featureExtraction($inputs, $modelo)
    {
        $apiKey = (string) (Yii::$app->params['hf_api_key'] ?? '');
        if ($apiKey === '') {
            return null;
        }

        try {
            $response = self::client($modelo, $apiKey)->post('', [
                'inputs' => $inputs,
                'options' => [
                    'wait_for_model' => false,
                ],
            ]);
        } catch (HttpApiException $e) {
            Yii::warning(
                'Error generando embedding con HuggingFace: HTTP ' . $e->statusCode()
                . ' ' . mb_substr((string) $e->responseBody(), 0, 300),
                self::LOG_CATEGORY
            );

            return null;
        }

        $data = $response->body();

        return $data === [] ? null : $data;
    }

    private static function client(string $modelo, string $apiKey): JsonApiClient
    {
        return new JsonApiClient(
            baseUrl: self::BASE_URL . $modelo,
            defaultHeaders: ['Authorization' => 'Bearer ' . $apiKey],
            connectTimeout: 5,
            timeout: 60,
            logCategory: self::LOG_CATEGORY,
            provider: 'huggingface',
            providerLabel: 'HuggingFace'
        );
    }
}
