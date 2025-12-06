<?php

namespace common\components;

use Yii;
use yii\httpclient\Client;

/**
 * Procesador de batch para agrupar múltiples requests de HuggingFace
 */
class HuggingFaceBatchProcessor
{
    private static $batchQueue = [];
    private const BATCH_SIZE = 10;
    
    public static function agregarABatch($endpoint, $payload, $callback = null, $inmediato = false)
    {
        $requestId = uniqid('batch_');
        
        $request = [
            'id' => $requestId,
            'endpoint' => $endpoint,
            'payload' => $payload,
            'callback' => $callback,
            'timestamp' => microtime(true)
        ];
        
        if (!isset(self::$batchQueue[$endpoint])) {
            self::$batchQueue[$endpoint] = [];
        }
        
        self::$batchQueue[$endpoint][] = $request;
        
        if ($inmediato || count(self::$batchQueue[$endpoint]) >= self::BATCH_SIZE) {
            self::procesarBatch($endpoint);
        }
        
        return $requestId;
    }
    
    public static function procesarBatch($endpoint)
    {
        if (!isset(self::$batchQueue[$endpoint]) || empty(self::$batchQueue[$endpoint])) {
            return [];
        }
        
        $requests = self::$batchQueue[$endpoint];
        self::$batchQueue[$endpoint] = [];
        
        $resultados = [];
        
        foreach ($requests as $request) {
            try {
                $resultado = self::procesarRequest($request);
                $resultados[$request['id']] = $resultado;
                
                if ($request['callback'] && is_callable($request['callback'])) {
                    call_user_func($request['callback'], $resultado);
                }
            } catch (\Exception $e) {
                \Yii::error("Error procesando request en batch: " . $e->getMessage(), 'hf-batch');
                $resultados[$request['id']] = ['error' => $e->getMessage()];
            }
        }
        
        return $resultados;
    }
    
    private static function procesarRequest($request)
    {
        if (!HuggingFaceRateLimiter::puedeHacerRequest($request['endpoint'], false)) {
            self::$batchQueue[$request['endpoint']][] = $request;
            return ['error' => 'Rate limit'];
        }
        
        $apiKey = Yii::$app->params['hf_api_key'] ?? '';
        if (empty($apiKey)) {
            return ['error' => 'API key no configurada'];
        }
        
        try {
            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($request['endpoint'])
                ->addHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json'
                ])
                ->setContent(json_encode($request['payload']))
                ->send();
            
            if ($response->isOk) {
                HuggingFaceRateLimiter::registrarExito($request['endpoint']);
                return [
                    'success' => true,
                    'data' => json_decode($response->content, true)
                ];
            } else {
                HuggingFaceRateLimiter::registrarError($request['endpoint'], $response->getStatusCode());
                return [
                    'success' => false,
                    'error' => 'HTTP ' . $response->getStatusCode()
                ];
            }
            
        } catch (\Exception $e) {
            HuggingFaceRateLimiter::registrarError($request['endpoint']);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
