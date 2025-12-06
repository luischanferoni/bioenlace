<?php

namespace common\components;

use Yii;
use yii\httpclient\Client;

/**
 * Gestor de conversión de audio a texto usando HuggingFace
 * Optimizado para reducir costos mediante pre-procesamiento y selección de modelos
 */
class SpeechToTextManager
{
    private const CACHE_TTL = 86400; // 24 horas para transcripciones
    private const MAX_AUDIO_SIZE = 25 * 1024 * 1024; // 25MB máximo
    
    /**
     * Modelos disponibles ordenados por costo (de menor a mayor)
     */
    private const MODELOS = [
        'economico' => 'jonatasgrosman/wav2vec2-xlsr-53-spanish',
        'balanceado' => 'jonatasgrosman/wav2vec2-large-xlsr-53-spanish',
        'premium' => 'openai/whisper-large-v2'
    ];
    
    /**
     * Convertir audio a texto
     */
    public static function transcribir($audioPath, $modelo = 'economico', $opciones = [])
    {
        $inicio = microtime(true);
        
        try {
            // Verificar cache
            $cacheKey = 'stt_' . md5($audioPath . $modelo);
            $yiiCache = Yii::$app->cache;
            if ($yiiCache) {
                $cached = $yiiCache->get($cacheKey);
                if ($cached !== false) {
                    \Yii::info("Transcripción obtenida desde cache", 'speech-to-text');
                    return $cached;
                }
            }
            
            // Cargar y pre-procesar audio
            $audioData = self::preprocesarAudio($audioPath, $opciones);
            if (!$audioData) {
                return [
                    'texto' => '',
                    'confidence' => 0,
                    'error' => 'No se pudo procesar el audio'
                ];
            }
            
            // Seleccionar modelo
            $modeloSeleccionado = self::MODELOS[$modelo] ?? self::MODELOS['economico'];
            $modeloConfigurado = Yii::$app->params['hf_stt_model'] ?? null;
            if ($modeloConfigurado) {
                $modeloSeleccionado = $modeloConfigurado;
            }
            
            // Realizar transcripción
            $resultado = self::llamarAPIHuggingFace($audioData, $modeloSeleccionado);
            
            if ($resultado && !empty($resultado['texto'])) {
                // Guardar en cache
                if ($yiiCache) {
                    $yiiCache->set($cacheKey, $resultado, self::CACHE_TTL);
                }
                
                $tiempo = microtime(true) - $inicio;
                $resultado['tiempo_procesamiento'] = $tiempo;
                $resultado['modelo_usado'] = $modeloSeleccionado;
                
                \Yii::info("Audio transcrito exitosamente en {$tiempo}s", 'speech-to-text');
                return $resultado;
            }
            
            return [
                'texto' => '',
                'confidence' => 0,
                'error' => 'No se pudo transcribir el audio'
            ];
            
        } catch (\Exception $e) {
            \Yii::error("Error en transcripción de audio: " . $e->getMessage(), 'speech-to-text');
            return [
                'texto' => '',
                'confidence' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private static function preprocesarAudio($audioPath, $opciones = [])
    {
        try {
            if (preg_match('/^data:audio\/(\w+);base64,/', $audioPath, $matches)) {
                $base64 = substr($audioPath, strpos($audioPath, ',') + 1);
                $audioData = base64_decode($base64);
            } elseif (file_exists($audioPath)) {
                $audioData = file_get_contents($audioPath);
            } else {
                $audioData = base64_decode($audioPath);
            }
            
            if (!$audioData || strlen($audioData) > self::MAX_AUDIO_SIZE) {
                return null;
            }
            
            return $audioData;
            
        } catch (\Exception $e) {
            \Yii::error("Error pre-procesando audio: " . $e->getMessage(), 'speech-to-text');
            return null;
        }
    }
    
    private static function llamarAPIHuggingFace($audioData, $modelo)
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
                ->setUrl("https://api-inference.huggingface.co/models/{$modelo}")
                ->addHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json'
                ])
                ->setContent(json_encode([
                    'inputs' => $audioBase64,
                    'options' => [
                        'wait_for_model' => false
                    ]
                ]))
                ->send();
            
            if ($response->isOk) {
                $data = json_decode($response->content, true);
                
                $texto = '';
                if (isset($data['text'])) {
                    $texto = $data['text'];
                } elseif (isset($data[0]['text'])) {
                    $texto = $data[0]['text'];
                } elseif (is_string($data)) {
                    $texto = $data;
                }
                
                if (!empty($texto)) {
                    return [
                        'texto' => trim($texto),
                        'confidence' => isset($data['confidence']) ? (float)$data['confidence'] : 0.8
                    ];
                }
            }
            
        } catch (\Exception $e) {
            \Yii::error("Error llamando API HuggingFace: " . $e->getMessage(), 'speech-to-text');
        }
        
        return null;
    }
}
