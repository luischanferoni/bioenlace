<?php

namespace common\components\Platform\Ai\SpeechToText;

use common\components\Shared\Infrastructure\Http\HttpApiException;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use Yii;

/**
 * STT de HuggingFace Inference Providers (audio base64 en JSON).
 */
final class HuggingFaceSttClient
{
    public const BASE_URL = 'https://router.huggingface.co/hf-inference/';

    public const LOG_CATEGORY = 'speech-to-text';

    /**
     * @param string $audioData Datos binarios de audio (idealmente ya optimizados/comprimidos)
     * @param string $modelo
     * @return array|null ['texto'=>string,'confidence'=>float]
     */
    public static function transcribe($audioData, $modelo)
    {
        $apiKey = (string) (Yii::$app->params['hf_api_key'] ?? '');
        if ($apiKey === '' || $audioData === '') {
            return null;
        }

        try {
            $response = self::client($modelo, $apiKey)->post('', [
                'inputs' => base64_encode($audioData),
                'options' => [
                    'wait_for_model' => false,
                ],
            ]);
        } catch (HttpApiException $e) {
            Yii::error(
                'Error llamando API HuggingFace STT: ' . $e->getMessage(),
                self::LOG_CATEGORY
            );

            return null;
        }

        $data = $response->body();
        if ($data === []) {
            return null;
        }

        $texto = '';
        if (isset($data['text'])) {
            $texto = $data['text'];
        } elseif (isset($data[0]['text'])) {
            $texto = $data[0]['text'];
        } elseif (is_string($data)) {
            $texto = $data;
        }

        if (empty($texto)) {
            return null;
        }

        return [
            'texto' => trim((string) $texto),
            'confidence' => isset($data['confidence']) ? (float) $data['confidence'] : 0.8,
        ];
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
