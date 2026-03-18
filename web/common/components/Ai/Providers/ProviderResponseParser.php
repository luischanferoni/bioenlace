<?php

namespace common\components\Ai\Providers;

use Yii;
use common\components\Ai\Transport\ResponseDecompressor;

final class ProviderResponseParser
{
    /**
     * @param \yii\httpclient\Response $response
     * @param string $tipo
     * @return string|null
     */
    public static function parse($response, $tipo)
    {
        $responseContent = $response->content ?? '';
        $responseContentLength = strlen($responseContent);
        Yii::info(
            "IAManager::procesarRespuestaProveedorInstance - INICIO. Tipo: {$tipo}. Respuesta original (longitud: {$responseContentLength}): {$responseContent}",
            'ia-manager'
        );

        $content = ResponseDecompressor::decompress($response);
        Yii::info(
            "IAManager::procesarRespuestaProveedorInstance - Después de descomprimir. Contenido (longitud: " . strlen($content) . "): {$content}",
            'ia-manager'
        );

        $responseData = json_decode($content, true);
        Yii::info(
            "IAManager::procesarRespuestaProveedorInstance - JSON parseado. JSON Error: " . json_last_error_msg() .
            ". ResponseData completo: " . json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'ia-manager'
        );

        if (json_last_error() !== JSON_ERROR_NONE) {
            Yii::error(
                'Error decodificando JSON de IA: ' . json_last_error_msg() . ' - Contenido preview: ' . substr($content, 0, 200),
                'ia-manager'
            );
            return null;
        }

        $contenido = null;
        switch ($tipo) {
            case 'ollama':
                $contenido = $responseData['response'] ?? null;
                break;
            case 'openai':
            case 'groq':
            case 'huggingface':
                $contenido = $responseData['choices'][0]['message']['content'] ?? null;
                break;
            case 'google':
                if (isset($responseData['candidates'][0]['content']['parts'])) {
                    $parts = $responseData['candidates'][0]['content']['parts'];
                    $numParts = count($parts);
                    Yii::info("IAManager: Google respuesta con {$numParts} partes", 'ia-manager');

                    $contenido = '';
                    foreach ($parts as $index => $part) {
                        if (isset($part['text'])) {
                            $contenido .= $part['text'];
                            Yii::info("IAManager: Parte {$index} longitud: " . strlen($part['text']), 'ia-manager');
                        }
                    }

                    if (!empty($contenido)) {
                        Yii::info("IAManager: Contenido total concatenado longitud: " . strlen($contenido), 'ia-manager');
                    }
                } elseif (isset($responseData['predictions'][0]['content'])) {
                    $contenido = $responseData['predictions'][0]['content'];
                } elseif (isset($responseData['predictions'][0]['text'])) {
                    $contenido = $responseData['predictions'][0]['text'];
                } elseif (isset($responseData['choices'][0]['message']['content'])) {
                    $contenido = $responseData['choices'][0]['message']['content'];
                }
                break;
            default:
                $contenido = $responseData;
        }

        $contenidoLength = is_string($contenido)
            ? strlen($contenido)
            : (is_array($contenido) ? ('array con ' . count($contenido) . ' elementos') : gettype($contenido));
        $contenidoPreview = is_string($contenido)
            ? $contenido
            : (is_array($contenido) ? json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : (string)$contenido);

        Yii::info(
            "IAManager::procesarRespuestaProveedorInstance - Contenido final extraído (tipo: {$tipo}, longitud/tamaño: {$contenidoLength}): {$contenidoPreview}",
            'ia-manager'
        );

        return $contenido;
    }
}

