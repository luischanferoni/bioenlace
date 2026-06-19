<?php

namespace common\components\Platform\Ai\SpeechToText;

use Yii;

/**
 * STT vía Groq OpenAI-compatible API (Whisper).
 *
 * @see https://console.groq.com/docs/speech-to-text
 */
final class GroqSttClient
{
    private const API_URL = 'https://api.groq.com/openai/v1/audio/transcriptions';

    /**
     * @param string $audioData Binario de audio
     * @param string $modelo Ej. whisper-large-v3-turbo
     * @param string $language Código ISO (es)
     * @return array|null ['texto'=>string,'confidence'=>float]
     */
    public static function transcribe($audioData, $modelo, $language = 'es')
    {
        $apiKey = Yii::$app->params['groq_api_key'] ?? '';
        if ($apiKey === '' || $audioData === '') {
            return null;
        }

        $extension = self::guessExtension($audioData);
        $tempFile = tempnam(sys_get_temp_dir(), 'groq_stt_');
        $audioPath = $tempFile . '.' . $extension;

        try {
            if (!@rename($tempFile, $audioPath)) {
                $audioPath = $tempFile;
            }
            file_put_contents($audioPath, $audioData);

            $mime = self::mimeForExtension($extension);
            $postFields = [
                'file' => new \CURLFile($audioPath, $mime, 'audio.' . $extension),
                'model' => $modelo,
                'language' => $language,
                'response_format' => 'json',
                'temperature' => '0',
            ];

            $ch = curl_init(self::API_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_TIMEOUT => 120,
            ]);

            $body = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($body === false || $httpCode < 200 || $httpCode >= 300) {
                Yii::error(
                    'Groq STT HTTP ' . $httpCode . ': ' . ($curlError ?: (string) $body),
                    'speech-to-text'
                );

                return null;
            }

            $data = json_decode((string) $body, true);
            $texto = trim((string) ($data['text'] ?? ''));
            if ($texto === '') {
                return null;
            }

            return [
                'texto' => $texto,
                'confidence' => 0.9,
            ];
        } catch (\Throwable $e) {
            Yii::error('Error llamando API Groq STT: ' . $e->getMessage(), 'speech-to-text');

            return null;
        } finally {
            if (isset($audioPath) && is_file($audioPath)) {
                @unlink($audioPath);
            } elseif (isset($tempFile) && is_file($tempFile)) {
                @unlink($tempFile);
            }
        }
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
