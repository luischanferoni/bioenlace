<?php

namespace common\components\Ai\Embeddings;

use Yii;
use yii\httpclient\Client;

final class OpenAIEmbeddingsClient
{
    /**
     * @param string|array $input
     * @param string $model
     * @return array|null Respuesta JSON decodificada
     */
    public static function embeddings($input, $model = 'text-embedding-3-small')
    {
        try {
            $apiKey = Yii::$app->params['openai_api_key'] ?? '';
            if (empty($apiKey)) {
                return null;
            }

            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl('https://api.openai.com/v1/embeddings')
                ->addHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->setContent(json_encode([
                    'model' => $model,
                    'input' => $input,
                ]))
                ->send();

            if (!$response->isOk) {
                $statusCode = $response->getStatusCode();
                $errorMsg = 'Error generando embeddings OpenAI: ' . $statusCode;
                if ($statusCode === 401) {
                    $errorMsg .= ' - API key inválida o expirada. Verifica tu configuración en params-local.php';
                }
                Yii::error($errorMsg, 'embeddings');
                return null;
            }

            $responseData = json_decode($response->content, true);
            return is_array($responseData) ? $responseData : null;
        } catch (\Exception $e) {
            Yii::error("Error en generación de embeddings OpenAI: " . $e->getMessage(), 'embeddings');
            return null;
        }
    }
}

