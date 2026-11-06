<?php

namespace common\components\Platform\Ai\SpeechToText;

use common\components\Shared\Infrastructure\Http\HttpApiException;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use common\components\Shared\Infrastructure\Http\MultipartBody;
use Yii;

/**
 * STT vía Groq OpenAI-compatible API (Whisper).
 *
 * El audio viaja como multipart en memoria: no hace falta escribirlo a un archivo temporal
 * ni usar `curl_*` a mano.
 *
 * @see https://console.groq.com/docs/speech-to-text
 */
final class GroqSttClient
{
    public const BASE_URL = 'https://api.groq.com/openai/v1';

    public const LOG_CATEGORY = 'speech-to-text';

    /** Transcripción de audio largo: el timeout más alto del repo. */
    public const TIMEOUT_SECONDS = 120;

    /**
     * @param string $audioData Binario de audio
     * @param string $modelo Ej. whisper-large-v3-turbo
     * @param string $language Código ISO (es)
     * @return array|null ['texto'=>string,'confidence'=>float]
     */
    public static function transcribe($audioData, $modelo, $language = 'es')
    {
        $apiKey = (string) (Yii::$app->params['groq_api_key'] ?? '');
        if ($apiKey === '' || $audioData === '') {
            return null;
        }

        $extension = self::guessExtension($audioData);

        try {
            $response = self::client($apiKey)->postMultipart('/audio/transcriptions', new MultipartBody(
                field: 'file',
                content: $audioData,
                fileName: 'audio.' . $extension,
                mimeType: self::mimeForExtension($extension),
                fields: [
                    'model' => $modelo,
                    'language' => $language,
                    'response_format' => 'json',
                    'temperature' => '0',
                ]
            ));
        } catch (HttpApiException $e) {
            Yii::error('Groq STT HTTP ' . $e->statusCode() . ': ' . $e->getMessage(), self::LOG_CATEGORY);

            return null;
        }

        $texto = trim((string) ($response->body()['text'] ?? ''));
        if ($texto === '') {
            return null;
        }

        return [
            'texto' => $texto,
            'confidence' => 0.9,
        ];
    }

    private static function client(string $apiKey): JsonApiClient
    {
        return new JsonApiClient(
            baseUrl: self::BASE_URL,
            defaultHeaders: ['Authorization' => 'Bearer ' . $apiKey],
            connectTimeout: 10,
            timeout: (float) self::TIMEOUT_SECONDS,
            logCategory: self::LOG_CATEGORY,
            provider: 'groq',
            providerLabel: 'Groq'
        );
    }

    private static function guessExtension(string $audioData): string
    {
        if (strncmp($audioData, 'RIFF', 4) === 0) {
            return 'wav';
        }
        if (strncmp($audioData, 'OggS', 4) === 0) {
            return 'ogg';
        }
        if (strncmp($audioData, 'fLaC', 4) === 0) {
            return 'flac';
        }
        if (strncmp($audioData, "\x1a\x45\xdf\xa3", 4) === 0) {
            return 'webm';
        }
        if (strlen($audioData) > 12 && substr($audioData, 4, 4) === 'ftyp') {
            return 'm4a';
        }
        if (strncmp($audioData, 'ID3', 3) === 0 || strncmp($audioData, "\xff\xfb", 2) === 0) {
            return 'mp3';
        }

        return 'webm';
    }

    private static function mimeForExtension(string $extension): string
    {
        $map = [
            'wav' => 'audio/wav',
            'webm' => 'audio/webm',
            'ogg' => 'audio/ogg',
            'm4a' => 'audio/mp4',
            'mp3' => 'audio/mpeg',
            'flac' => 'audio/flac',
            'mp4' => 'audio/mp4',
        ];

        return $map[$extension] ?? 'application/octet-stream';
    }
}
