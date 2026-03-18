<?php

namespace common\components\Ai\Transport;

use Yii;

final class ResponseDecompressor
{
    /**
     * @param \yii\httpclient\Response $response
     * @return string
     */
    public static function decompress($response)
    {
        $content = $response->content;
        $contentEncoding = $response->headers->get('Content-Encoding', '');

        if (strtolower($contentEncoding) === 'gzip' || (substr($content, 0, 2) === "\x1f\x8b")) {
            if (function_exists('gzdecode')) {
                $decompressed = @gzdecode($content);
                if ($decompressed !== false) {
                    Yii::info("Respuesta descomprimida: " . strlen($content) . " -> " . strlen($decompressed) . " bytes", 'ia-manager');
                    return $decompressed;
                }
                Yii::warning("Error descomprimiendo respuesta gzip", 'ia-manager');
            }
        } elseif (strtolower($contentEncoding) === 'deflate') {
            if (function_exists('gzinflate')) {
                $decompressed = @gzinflate($content);
                if ($decompressed !== false) {
                    Yii::info("Respuesta descomprimida (deflate): " . strlen($content) . " -> " . strlen($decompressed) . " bytes", 'ia-manager');
                    return $decompressed;
                }
                Yii::warning("Error descomprimiendo respuesta deflate", 'ia-manager');
            }
        }

        return $content;
    }
}

