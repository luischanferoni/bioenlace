<?php

namespace common\components;

use Yii;
use ReflectionClass;
use yii\db\ActiveRecord;

/**
 * Servicio para extraer metadata del chatbot desde anotaciones en modelos
 * 
 * Lee anotaciones @chatbot-* en los docblocks de los modelos ActiveRecord
 * y extrae la configuración de categorías, intents y parámetros.
 */
class ChatbotMetadataExtractor
{
    /**
     * Directorios donde buscar modelos
     */
    private static $modelPaths = [
        '@common/models',
    ];

    /**
     * Extraer todas las configuraciones de chatbot desde modelos
     * @param bool $useCache Usar cache si está disponible
     * @return array Estructura con categorías, intents y parámetros
     */
    public static function extractAll($useCache = true)
    {
        $cache = Yii::$app->cache;
        $cacheKey = 'chatbot_metadata_all';
        
        if ($useCache && $cache) {
            $cached = $cache->get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $categories = [];
        $intentParameters = [];
        $patientReferences = [];

        // Descubrir todos los modelos
        $models = ModelDiscoveryService::discoverAllModels(false);
        
        foreach ($models as $modelInfo) {
            $className = $modelInfo['class'];
            
            try {
                $reflection = new ReflectionClass($className);
                $docComment = $reflection->getDocComment();
                
                if (!$docComment) {
                    continue;
                }
                
                // Extraer metadata del chatbot
                $metadata = self::extractModelChatbotMetadata($docComment, $modelInfo);
                
                if ($metadata && isset($metadata['category'])) {
                    // Agregar categoría
                    $categoryKey = $metadata['category']['key'];
                    $categories[$categoryKey] = $metadata['category'];
                    
                    // Agregar parámetros de intents
                    foreach ($metadata['intents'] as $intentKey => $intent) {
                        $intentParameters[$intentKey] = $intent['parameters'];
                    }
                    
                    // Agregar referencias del paciente si existen
                    if (!empty($metadata['patient_references'])) {
                        $patientReferences = array_merge($patientReferences, $metadata['patient_references']);
                    }
                }
            } catch (\Exception $e) {
                Yii::warning("Error extrayendo metadata de chatbot de {$className}: " . $e->getMessage(), 'chatbot-metadata-extractor');
            }
        }

        $result = [
            'categories' => $categories,
            'intent_parameters' => $intentParameters,
            'patient_references' => $patientReferences,
        ];

        // Guardar en cache
        if ($cache) {
            $cache->set($cacheKey, $result, 3600); // 1 hora
        }

        return $result;
    }

    /**
     * Extraer metadata del chatbot de un modelo
     * @param string $docComment Docblock del modelo
     * @param array $modelInfo Información del modelo
     * @return array|null
     */
    private static function extractModelChatbotMetadata($docComment, $modelInfo)
    {
        $lines = explode("\n", $docComment);
        
        $category = null;
        $intents = [];
        $patientReferences = [];
        $currentIntent = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // @chatbot-category {key}
            if (preg_match('/@chatbot-category\s+(\w+)/i', $line, $matches)) {
                $categoryKey = trim($matches[1]);
                $category = [
                    'key' => $categoryKey,
                    'name' => null,
                    'description' => null,
                ];
            }
            
            // @chatbot-category-name "{name}"
            if (preg_match('/@chatbot-category-name\s+"([^"]+)"/i', $line, $matches)) {
                if ($category) {
                    $category['name'] = trim($matches[1]);
                }
            }
            
            // @chatbot-category-description "{description}"
            if (preg_match('/@chatbot-category-description\s+"([^"]+)"/i', $line, $matches)) {
                if ($category) {
                    $category['description'] = trim($matches[1]);
                }
            }
            
            // @chatbot-intent {intent_key}
            if (preg_match('/@chatbot-intent\s+(\w+)/i', $line, $matches)) {
                $intentKey = trim($matches[1]);
                $currentIntent = $intentKey;
                $intents[$intentKey] = [
                    'name' => null,
                    'handler' => null,
                    'priority' => 'medium',
                    'keywords' => [],
                    'patterns' => [],
                    'parameters' => [
                        'required_params' => [],
                        'optional_params' => [],
                        'lifetime' => 300,
                        'cleanup_on' => ['intent_change'],
                        'patient_profile' => [
                            'can_use' => [],
                            'resolve_references' => false,
                        ],
                    ],
                ];
            }
            
            // Procesar anotaciones del intent actual
            if ($currentIntent && isset($intents[$currentIntent])) {
                $intent = &$intents[$currentIntent];
                
                // @chatbot-intent-name "{name}"
                if (preg_match('/@chatbot-intent-name\s+"([^"]+)"/i', $line, $matches)) {
                    $intent['name'] = trim($matches[1]);
                }
                
                // @chatbot-intent-handler {HandlerClass}
                if (preg_match('/@chatbot-intent-handler\s+(\w+)/i', $line, $matches)) {
                    $intent['handler'] = trim($matches[1]);
                }
                
                // @chatbot-intent-priority {priority}
                if (preg_match('/@chatbot-intent-priority\s+(critical|high|medium|low)/i', $line, $matches)) {
                    $intent['priority'] = strtolower(trim($matches[1]));
                }
                
                // @chatbot-intent-keywords "{keyword1,keyword2}"
                if (preg_match('/@chatbot-intent-keywords\s+"([^"]+)"/i', $line, $matches)) {
                    $keywords = array_map('trim', explode(',', $matches[1]));
                    $intent['keywords'] = array_filter($keywords, function($k) { return !empty($k); });
                }
                
                // @chatbot-intent-patterns "{/pattern1/i,/pattern2/i}"
                if (preg_match('/@chatbot-intent-patterns\s+"([^"]+)"/i', $line, $matches)) {
                    $patterns = array_map('trim', explode(',', $matches[1]));
                    $intent['patterns'] = array_filter($patterns, function($p) { return !empty($p); });
                }
                
                // @chatbot-intent-required-params {param1,param2}
                if (preg_match('/@chatbot-intent-required-params\s+([\w,]+)/i', $line, $matches)) {
                    $params = array_map('trim', explode(',', $matches[1]));
                    $intent['parameters']['required_params'] = array_filter($params, function($p) { return !empty($p); });
                }
                
                // @chatbot-intent-optional-params {param1,param2}
                if (preg_match('/@chatbot-intent-optional-params\s+([\w,]+)/i', $line, $matches)) {
                    $params = array_map('trim', explode(',', $matches[1]));
                    $intent['parameters']['optional_params'] = array_filter($params, function($p) { return !empty($p); });
                }
                
                // @chatbot-intent-lifetime {segundos}
                if (preg_match('/@chatbot-intent-lifetime\s+(\d+)/i', $line, $matches)) {
                    $intent['parameters']['lifetime'] = (int)$matches[1];
                }
                
                // @chatbot-intent-patient-profile-can-use {professional,efector,service}
                if (preg_match('/@chatbot-intent-patient-profile-can-use\s+([\w,]+)/i', $line, $matches)) {
                    $canUse = array_map('trim', explode(',', $matches[1]));
                    $intent['parameters']['patient_profile']['can_use'] = array_filter($canUse, function($u) { return !empty($u); });
                }
                
                // @chatbot-intent-patient-profile-resolve-references {true|false}
                if (preg_match('/@chatbot-intent-patient-profile-resolve-references\s+(true|false)/i', $line, $matches)) {
                    $intent['parameters']['patient_profile']['resolve_references'] = strtolower($matches[1]) === 'true';
                }
                
                // @chatbot-intent-patient-profile-update-on-complete-type {type}
                if (preg_match('/@chatbot-intent-patient-profile-update-on-complete-type\s+(\w+)/i', $line, $matches)) {
                    if (!isset($intent['parameters']['patient_profile']['update_on_complete'])) {
                        $intent['parameters']['patient_profile']['update_on_complete'] = [];
                    }
                    $intent['parameters']['patient_profile']['update_on_complete']['type'] = trim($matches[1]);
                }
                
                // @chatbot-intent-patient-profile-update-on-complete-fields {field1,field2}
                if (preg_match('/@chatbot-intent-patient-profile-update-on-complete-fields\s+([\w,]+)/i', $line, $matches)) {
                    if (!isset($intent['parameters']['patient_profile']['update_on_complete'])) {
                        $intent['parameters']['patient_profile']['update_on_complete'] = [];
                    }
                    $fields = array_map('trim', explode(',', $matches[1]));
                    $intent['parameters']['patient_profile']['update_on_complete']['fields'] = array_filter($fields, function($f) { return !empty($f); });
                }
                
                // @chatbot-intent-patient-profile-cache-ttl {segundos}
                if (preg_match('/@chatbot-intent-patient-profile-cache-ttl\s+(\d+)/i', $line, $matches)) {
                    $intent['parameters']['patient_profile']['cache_ttl'] = (int)$matches[1];
                }
            }
        }
        
        if (!$category) {
            return null;
        }
        
        // Agregar intents a la categoría
        $category['intents'] = [];
        foreach ($intents as $intentKey => $intent) {
            $category['intents'][$intentKey] = [
                'name' => $intent['name'],
                'keywords' => $intent['keywords'],
                'patterns' => $intent['patterns'],
                'handler' => $intent['handler'],
                'priority' => $intent['priority'],
            ];
        }
        
        return [
            'category' => $category,
            'intents' => $intents,
            'patient_references' => $patientReferences,
        ];
    }

    /**
     * Invalidar cache de metadata
     */
    public static function invalidateCache()
    {
        $cache = Yii::$app->cache;
        if ($cache) {
            $cache->delete('chatbot_metadata_all');
        }
    }
}
