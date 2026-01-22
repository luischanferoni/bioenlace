<?php

namespace common\components;

use Yii;

/**
 * Clasificador de intents
 * 
 * Clasifica la intención del usuario usando reglas predefinidas (keywords, patrones)
 * y fallback a IA si no hay match claro.
 * 
 * Diferente a ConsultaClassifier que optimiza procesamiento de consultas médicas.
 */
class IntentClassifier
{
    /**
     * Clasificar intent del mensaje
     * @param string $message Mensaje del usuario
     * @param array|null $context Contexto de conversación (opcional)
     * @return array ['category' => string, 'intent' => string, 'confidence' => float, 'method' => string]
     */
    public static function classify($message, $context = null)
    {
        // Primero intentar detección por reglas (más rápido)
        $rulesResult = self::classifyByRules($message, $context);
        
        if ($rulesResult && $rulesResult['confidence'] >= 0.7) {
            // Match claro por reglas
            return $rulesResult;
        }
        
        // Si no hay match claro, usar IA
        $aiResult = self::classifyByAI($message, $context, $rulesResult);
        
        if ($aiResult) {
            return $aiResult;
        }
        
        // Fallback: intent general
        return [
            'category' => 'general',
            'intent' => 'fuera_de_alcance',
            'confidence' => 0.3,
            'method' => 'fallback'
        ];
    }
    
    /**
     * Clasificar por reglas (keywords y patrones)
     * @param string $message
     * @param array|null $context
     * @return array|null
     */
    private static function classifyByRules($message, $context = null)
    {
        $messageLower = mb_strtolower(trim($message), 'UTF-8');
        $categories = require Yii::getAlias('@common/config/IntentCategories.php');
        
        $bestMatch = null;
        $bestScore = 0;
        
        // Buscar en todas las categorías e intents
        foreach ($categories as $categoryKey => $category) {
            foreach ($category['intents'] as $intentKey => $intentConfig) {
                // Saltar fallback intents en detección por reglas
                if (isset($intentConfig['is_fallback']) && $intentConfig['is_fallback']) {
                    continue;
                }
                
                $score = self::calculateMatchScore($messageLower, $intentConfig);
                
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = [
                        'category' => $categoryKey,
                        'intent' => $intentKey,
                        'confidence' => min($score / 100, 1.0), // Normalizar a 0-1
                        'method' => 'rules'
                    ];
                }
            }
        }
        
        // Si el score es muy bajo, no retornar match
        if ($bestScore < 30) {
            return null;
        }
        
        return $bestMatch;
    }
    
    /**
     * Calcular score de match para un intent
     * @param string $messageLower Mensaje en minúsculas
     * @param array $intentConfig Configuración del intent
     * @return float Score (0-100+)
     */
    private static function calculateMatchScore($messageLower, $intentConfig)
    {
        $score = 0;
        
        // Buscar keywords
        if (isset($intentConfig['keywords']) && is_array($intentConfig['keywords'])) {
            foreach ($intentConfig['keywords'] as $keyword) {
                $keywordLower = mb_strtolower($keyword, 'UTF-8');
                if (stripos($messageLower, $keywordLower) !== false) {
                    $score += 20; // Cada keyword encontrada suma 20 puntos
                }
            }
        }
        
        // Buscar patrones (regex)
        if (isset($intentConfig['patterns']) && is_array($intentConfig['patterns'])) {
            foreach ($intentConfig['patterns'] as $pattern) {
                if (preg_match($pattern, $messageLower)) {
                    $score += 30; // Cada patrón encontrado suma 30 puntos
                }
            }
        }
        
        // Bonus por prioridad
        if (isset($intentConfig['priority'])) {
            switch ($intentConfig['priority']) {
                case 'critical':
                    $score += 50;
                    break;
                case 'high':
                    $score += 20;
                    break;
                case 'medium':
                    $score += 10;
                    break;
            }
        }
        
        return $score;
    }
    
    /**
     * Clasificar usando IA
     * @param string $message
     * @param array|null $context
     * @param array|null $rulesResult Resultado de reglas (puede usarse como hint)
     * @return array|null
     */
    private static function classifyByAI($message, $context = null, $rulesResult = null)
    {
        try {
            // Construir prompt para IA
            $prompt = self::buildClassificationPrompt($message, $context, $rulesResult);
            
            // Llamar a IA usando IAManager
            $iaResponse = Yii::$app->iamanager->consultar($prompt, 'intent-classification', 'analysis');
            
            if (!$iaResponse || !is_array($iaResponse)) {
                return null;
            }
            
            // Parsear respuesta de IA
            $category = $iaResponse['category'] ?? null;
            $intent = $iaResponse['intent'] ?? null;
            $confidence = isset($iaResponse['confidence']) ? (float)$iaResponse['confidence'] : 0.7;
            
            if ($category && $intent) {
                // Validar que la categoría e intent existen
                if (self::validateCategoryAndIntent($category, $intent)) {
                    return [
                        'category' => $category,
                        'intent' => $intent,
                        'confidence' => $confidence,
                        'method' => 'ai'
                    ];
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Yii::error("IntentClassifier: Error en clasificación por IA: " . $e->getMessage(), 'intent-classifier');
            return null;
        }
    }
    
    /**
     * Construir prompt para clasificación por IA
     * @param string $message
     * @param array|null $context
     * @param array|null $rulesResult
     * @return string
     */
    private static function buildClassificationPrompt($message, $context = null, $rulesResult = null)
    {
        $categories = require Yii::getAlias('@common/config/IntentCategories.php');
        
        // Construir lista de categorías e intents disponibles
        $categoriesList = [];
        foreach ($categories as $categoryKey => $category) {
            $intents = [];
            foreach ($category['intents'] as $intentKey => $intentConfig) {
                if (isset($intentConfig['is_fallback']) && $intentConfig['is_fallback']) {
                    continue;
                }
                $intents[] = $intentKey;
            }
            $categoriesList[] = "{$categoryKey}: " . implode(', ', $intents);
        }
        
        $categoriesText = implode("\n", $categoriesList);
        
        $contextText = '';
        if ($context && isset($context['current_intent'])) {
            $contextText = "\nContexto actual: Intent '{$context['current_intent']}' en categoría '{$context['current_category']}'";
        }
        
        $hintText = '';
        if ($rulesResult) {
            $hintText = "\nHint de reglas: {$rulesResult['category']} -> {$rulesResult['intent']} (confidence: {$rulesResult['confidence']})";
        }
        
        $prompt = <<<PROMPT
Clasifica esta consulta de usuario en un sistema de salud del ministerio de salud.

Mensaje: "{$message}"
{$contextText}{$hintText}

Categorías e intents disponibles:
{$categoriesText}

Responde ÚNICAMENTE con este JSON:
{
  "category": "nombre_categoria",
  "intent": "nombre_intent",
  "confidence": 0.0-1.0,
  "reasoning": "breve explicación"
}

Reglas:
- Usa solo categorías e intents de la lista
- Si es una emergencia crítica (no respira, perdió conocimiento, etc.) → emergencias.emergencia_critica
- Si pregunta sobre síntomas o medicamentos → consulta_medica.consulta_sintomas o consulta_medica.consulta_medicamento
- Si es acción concreta (sacar turno, buscar farmacia) → categoría específica
- Si no encaja → general.fuera_de_alcance
PROMPT;

        return $prompt;
    }
    
    /**
     * Validar que categoría e intent existen
     * @param string $category
     * @param string $intent
     * @return bool
     */
    private static function validateCategoryAndIntent($category, $intent)
    {
        $categories = require Yii::getAlias('@common/config/IntentCategories.php');
        
        if (!isset($categories[$category])) {
            return false;
        }
        
        if (!isset($categories[$category]['intents'][$intent])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Obtener información del intent
     * @param string $category
     * @param string $intent
     * @return array|null
     */
    public static function getIntentInfo($category, $intent)
    {
        $categories = require Yii::getAlias('@common/config/IntentCategories.php');
        
        if (!isset($categories[$category]['intents'][$intent])) {
            return null;
        }
        
        return $categories[$category]['intents'][$intent];
    }
}
