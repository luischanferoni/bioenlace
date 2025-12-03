<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\UploadedFile;
use yii\web\Response;
use yii\rest\Controller;

class OmniformController extends Controller
{
    public $enableCsrfValidation = false;

    public function actionAudio()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // 1. Recibir el audio (asumiendo campo 'audio' en multipart/form-data)
        $audio = UploadedFile::getInstanceByName('audio');
        if (!$audio) {
            return ['success' => false, 'error' => 'No se recibió archivo de audio.'];
        }

        // Guardar temporalmente el archivo
        $tmpPath = Yii::getAlias('@runtime') . '/audio_' . uniqid() . '.' . $audio->extension;
        if (!$audio->saveAs($tmpPath)) {
            return ['success' => false, 'error' => 'No se pudo guardar el audio.'];
        }

        // 2. Enviar el audio a una API gratuita de STT (ejemplo: OpenAI Whisper API pública, AssemblyAI, etc.)
        // Aquí un ejemplo genérico usando cURL (ajusta la URL y headers según el servicio real)
        $apiUrl = 'https://api.openai.com/v1/audio/transcriptions'; // Cambia por la API gratuita que uses
        $apiKey = 'TU_API_KEY'; // Si requiere API Key

        $curl = curl_init();
        $postFields = [
            'file' => new \CURLFile($tmpPath, $audio->type, $audio->name),
            'model' => 'whisper-1', // Si usas OpenAI Whisper
        ];
        curl_setopt_array($curl, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $apiKey"
            ]
        ]);
        $apiResponse = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        // Borrar el archivo temporal
        @unlink($tmpPath);

        if ($err) {
            return ['success' => false, 'error' => 'Error al llamar a la API de audio: ' . $err];
        }

        $apiData = json_decode($apiResponse, true);
        if (empty($apiData['text'])) {
            return ['success' => false, 'error' => 'No se pudo transcribir el audio.'];
        }

        $texto = $apiData['text'];

        // 3. Armar el prompt hardcodeado
        $json = $this->armarPrompt($texto);

        return [
            'success' => true,
            'json' => $json,
        ];
    }

    private function armarPrompt($texto)
    {

        $prompt = <<<EOT
        Analizá el siguiente texto devolvé un JSON con este formato:
        Devuelve un JSON con este formato:

        {
        "intencion": "...",
        "datos": {
            "servicio": "...",
            "dia": "...",
            "horario": "...",
            "modificaciones": {
            "servicio": "...",
            "dia": "...",
            "horario": "..."
            }
        }
        }
        Texto: "$texto"
        EOT;

        $endpointIA = 'http://192.168.1.11:11434/api/generate';
        //$endpointIA = 'http://190.30.242.228:1000/api/generate';

        $payload = [
            'model' => 'mistral',
            'prompt' => $prompt,
            'stream' => false
        ];

        $client = new \yii\httpclient\Client();
        $response = $client->createRequest()
            ->setMethod('POST')
            ->setUrl($endpointIA)
            ->addHeaders(['Content-Type' => 'application/json'])
            ->setContent(json_encode($payload))
            ->send();

        if ($response->isOk) {
            \Yii::info('Respuesta IA: ' . $response->data["response"], 'consulta-oftalmo');
            return json_decode($response->data["response"], true);
        } else {
            \Yii::error('Error en la respuesta de la IA: ' . $response->getStatusCode(), 'consulta-oftalmo');
        }
    }
}