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
    // Tamaño de batch optimizado: más grande para embeddings (50), más pequeño para text-generation (20)
    private const BATCH_SIZE_EMBEDDINGS = 50; // Embeddings soportan batches grandes
    private const BATCH_SIZE_TEXT_GEN = 20; // Text generation más conservador
    private const BATCH_SIZE_DEFAULT = 20; // Default
    
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
        
        // Determinar tamaño de batch según el tipo de endpoint
        $batchSize = self::obtenerBatchSize($endpoint);
        
        if ($inmediato || count(self::$batchQueue[$endpoint]) >= $batchSize) {
            self::procesarBatch($endpoint);
        }
        
        return $requestId;
    }
    
    /**
     * Obtener tamaño de batch según el tipo de endpoint
     * @param string $endpoint
     * @return int
     */
    private static function obtenerBatchSize($endpoint)
    {
        // Embeddings soportan batches más grandes
        if (strpos($endpoint, 'feature-extraction') !== false || strpos($endpoint, 'pipeline/feature-extraction') !== false) {
            return self::BATCH_SIZE_EMBEDDINGS;
        }
        
        // Text generation más conservador
        if (strpos($endpoint, 'text-generation') !== false || strpos($endpoint, 'generate') !== false) {
            return self::BATCH_SIZE_TEXT_GEN;
        }
        
        return self::BATCH_SIZE_DEFAULT;
    }
    
    public static function procesarBatch($endpoint)
    {
        if (!isset(self::$batchQueue[$endpoint]) || empty(self::$batchQueue[$endpoint])) {
            return [];
        }
        
        $requests = self::$batchQueue[$endpoint];
        self::$batchQueue[$endpoint] = [];
        
        $resultados = [];
        
        // Si hay múltiples requests para el mismo endpoint, intentar procesarlos juntos
        // (aunque HuggingFace API no soporta batch nativo, agrupamos para optimizar conexiones)
        if (count($requests) > 1 && self::puedeProcesarEnBatch($requests)) {
            $resultados = self::procesarBatchNativo($endpoint, $requests);
        } else {
            // Procesar individualmente
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
        }
        
        return $resultados;
    }
    
    /**
     * Verificar si los requests pueden procesarse en batch nativo
     */
    private static function puedeProcesarEnBatch($requests)
    {
        // Solo para embeddings que soportan batch nativo
        if (strpos($requests[0]['endpoint'], 'feature-extraction') !== false) {
            return true;
        }
        return false;
    }
    
    /**
     * Procesar batch nativo (para embeddings que lo soportan)
     */
    private static function procesarBatchNativo($endpoint, $requests)
    {
        $resultados = [];
        
        try {
            // Agrupar inputs de todos los requests
            $inputs = [];
            $requestMap = [];
            
            foreach ($requests as $request) {
                if (isset($request['payload']['inputs'])) {
                    if (is_array($request['payload']['inputs'])) {
                        $inputs = array_merge($inputs, $request['payload']['inputs']);
                    } else {
                        $inputs[] = $request['payload']['inputs'];
                    }
                    $requestMap[] = $request;
                }
            }
            
            if (empty($inputs)) {
                return self::procesarIndividual($requests);
            }
            
            // Procesar batch nativo
            $apiKey = Yii::$app->params['hf_api_key'] ?? '';
            if (empty($apiKey)) {
                return self::procesarIndividual($requests);
            }
            
            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($endpoint)
                ->addHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json'
                ])
                ->setContent(json_encode([
                    'inputs' => $inputs,
                    'options' => [
                        'wait_for_model' => false
                    ]
                ]))
                ->send();
            
            if ($response->isOk) {
                $responseData = json_decode($response->content, true);
                $index = 0;
                
                foreach ($requestMap as $request) {
                    if (isset($responseData[$index])) {
                        $resultado = [
                            'success' => true,
                            'data' => $responseData[$index]
                        ];
                        $resultados[$request['id']] = $resultado;
                        
                        if ($request['callback'] && is_callable($request['callback'])) {
                            call_user_func($request['callback'], $resultado);
                        }
                        $index++;
                    }
                }
            }
            
        } catch (\Exception $e) {
            \Yii::error("Error procesando batch nativo: " . $e->getMessage(), 'hf-batch');
            return self::procesarIndividual($requests);
        }
        
        return $resultados;
    }
    
    /**
     * Procesar requests individualmente
     */
    private static function procesarIndividual($requests)
    {
        $resultados = [];
        foreach ($requests as $request) {
            try {
                $resultado = self::procesarRequest($request);
                $resultados[$request['id']] = $resultado;
                
                if ($request['callback'] && is_callable($request['callback'])) {
                    call_user_func($request['callback'], $resultado);
                }
            } catch (\Exception $e) {
                \Yii::error("Error procesando request: " . $e->getMessage(), 'hf-batch');
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
