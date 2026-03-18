<?php

namespace common\components\Ai\SpeechToText;

use Yii;
use yii\httpclient\Client;

final class HuggingFaceSttClient
{
    /**
     * @param string $audioData Datos binarios de audio (idealmente ya optimizados/comprimidos)
     * @param string $modelo
     * @return array|null ['texto'=>string,'confidence'=>float]
     */
    public static function transcribe($audioData, $modelo)
    {
        try {
            $apiKey = Yii::$app->params['hf_api_key'] ?? '';
            if (empty($apiKey)) {
                return null;
            }

            $audioBase64 = base64_encode($audioData);

            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl("https://router.huggingface.co/hf-inference/{$modelo}")
                ->addHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->setContent(json_encode([
                    'inputs' => $audioBase64,
                    'options' => [
                        'wait_for_model' => false,
                    ],
                ]))
                ->send();

            if (!$response->isOk) {
                return null;
            }

            $data = json_decode($response->content, true);

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
                'texto' => trim($texto),
                'confidence' => isset($data['confidence']) ? (float)$data['confidence'] : 0.8,
            ];
        } catch (\Exception $e) {
            Yii::error("Error llamando API HuggingFace STT: " . $e->getMessage(), 'speech-to-text');
            return null;
        }
    }
}

