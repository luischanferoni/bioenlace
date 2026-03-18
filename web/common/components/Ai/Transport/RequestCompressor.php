<?php

namespace common\components\Ai\Transport;

use Yii;

final class RequestCompressor
{
    /**
     * @param string $data
     * @param string|null $providerType 'huggingface', 'openai', 'groq', 'ollama', 'google'
     * @return array{data:string,headers:array}
     */
    public static function compress($data, $providerType = null)
    {
        // La API router.huggingface.co podría no aceptar compresión gzip.
        if ($providerType === 'huggingface') {
            return ['data' => $data, 'headers' => []];
        }

        // Solo comprimir para proveedores que lo acepten (lista vacía por defecto).
        $providersThatAcceptCompression = [];
        if ($providerType && !in_array($providerType, $providersThatAcceptCompression, true)) {
            return ['data' => $data, 'headers' => []];
        }

        $useCompression = Yii::$app->params['comprimir_datos_transito'] ?? true;
        $headers = [];

        if ($useCompression && function_exists('gzencode') && strlen($data) > 500) {
            $compressed = gzencode($data, 6);
            $headers['Content-Encoding'] = 'gzip';
            Yii::info("Datos comprimidos: " . strlen($data) . " -> " . strlen($compressed) . " bytes", 'ia-manager');
            return ['data' => $compressed, 'headers' => $headers];
        }

        return ['data' => $data, 'headers' => $headers];
    }
}

