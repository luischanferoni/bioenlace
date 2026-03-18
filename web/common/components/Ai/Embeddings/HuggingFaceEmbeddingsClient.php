<?php

namespace common\components\Ai\Embeddings;

use Yii;
use yii\httpclient\Client;

final class HuggingFaceEmbeddingsClient
{
    /**
     * @param string|array $inputs Texto o lista de textos
     * @param string $modelo
     * @return array|null
     */
    public static function featureExtraction($inputs, $modelo)
    {
        try {
            $apiKey = Yii::$app->params['hf_api_key'] ?? '';
            if (empty($apiKey)) {
                return null;
            }

            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl("https://router.huggingface.co/hf-inference/pipeline/feature-extraction/{$modelo}")
                ->addHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->setContent(json_encode([
                    'inputs' => $inputs,
                    'options' => [
                        'wait_for_model' => false,
                    ],
                ]))
                ->send();

            if (!$response->isOk) {
                Yii::warning('Error generando embedding con HuggingFace: ' . $response->getStatusCode(), 'embeddings');
                return null;
            }

            $data = json_decode($response->content, true);
            if (!is_array($data) || empty($data)) {
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            Yii::error("Error en generación de embedding HuggingFace: " . $e->getMessage(), 'embeddings');
            return null;
        }
    }
}

