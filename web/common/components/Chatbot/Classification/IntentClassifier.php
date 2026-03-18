<?php

namespace common\components\Chatbot\Classification;

use Yii;

/**
 * Clasificador de intents
 *
 * Versión movida desde common\components\IntentClassifier.
 */
class IntentClassifier
{
    public static function classify($message, $context = null)
    {
        $rulesResult = self::classifyByRules($message, $context);

        if ($rulesResult && $rulesResult['confidence'] >= 0.7) {
            return $rulesResult;
        }

        $aiResult = self::classifyByAI($message, $context, $rulesResult);

        if ($aiResult) {
            return $aiResult;
        }

        return [
            'category' => 'general',
            'intent' => 'fuera_de_alcance',
            'confidence' => 0.3,
            'method' => 'fallback',
        ];
    }

    private static function classifyByRules($message, $context = null)
    {
        $messageLower = mb_strtolower(trim($message), 'UTF-8');
        $categories = require Yii::getAlias('@common/config/chatbot/intent-categories.php');

        $bestMatch = null;
        $bestScore = 0;

        foreach ($categories as $categoryKey => $category) {
            foreach ($category['intents'] as $intentKey => $intentConfig) {
                if (!empty($intentConfig['is_fallback'])) {
                    continue;
                }

                $score = self::calculateMatchScore($messageLower, $intentConfig);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = [
                        'category' => $categoryKey,
                        'intent' => $intentKey,
                        'confidence' => min($score / 100, 1.0),
                        'method' => 'rules',
                    ];
                }
            }
        }

        if ($bestScore < 30) {
            return null;
        }

        return $bestMatch;
    }

    private static function calculateMatchScore($messageLower, $intentConfig)
    {
        $score = 0;

        if (!empty($intentConfig['keywords']) && is_array($intentConfig['keywords'])) {
            foreach ($intentConfig['keywords'] as $keyword) {
                $keywordLower = mb_strtolower($keyword, 'UTF-8');
                if (stripos($messageLower, $keywordLower) !== false) {
                    $score += 20;
                }
            }
        }

        if (!empty($intentConfig['patterns']) && is_array($intentConfig['patterns'])) {
            foreach ($intentConfig['patterns'] as $pattern) {
                if (preg_match($pattern, $messageLower)) {
                    $score += 30;
                }
            }
        }

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

    private static function classifyByAI($message, $context = null, $rulesResult = null)
    {
        try {
            $prompt = self::buildClassificationPrompt($message, $context, $rulesResult);
            $iaResponse = Yii::$app->iamanager->consultar($prompt, 'intent-classification', 'analysis');

            if (!$iaResponse || !is_array($iaResponse)) {
                return null;
            }

            $category = $iaResponse['category'] ?? null;
            $intent = $iaResponse['intent'] ?? null;
            $confidence = isset($iaResponse['confidence']) ? (float) $iaResponse['confidence'] : 0.7;

            if ($category && $intent && self::validateCategoryAndIntent($category, $intent)) {
                return [
                    'category' => $category,
                    'intent' => $intent,
                    'confidence' => $confidence,
                    'method' => 'ai',
                ];
            }

            return null;
        } catch (\Exception $e) {
            Yii::error("IntentClassifier: Error en clasificación por IA: " . $e->getMessage(), 'intent-classifier');
            return null;
        }
    }

    private static function buildClassificationPrompt($message, $context = null, $rulesResult = null)
    {
        $categories = require Yii::getAlias('@common/config/chatbot/intent-categories.php');

        $categoriesList = [];
        foreach ($categories as $categoryKey => $category) {
            $intents = [];
            foreach ($category['intents'] as $intentKey => $intentConfig) {
                if (!empty($intentConfig['is_fallback'])) {
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

    private static function validateCategoryAndIntent($category, $intent)
    {
        $categories = require Yii::getAlias('@common/config/chatbot/intent-categories.php');

        if (!isset($categories[$category])) {
            return false;
        }

        if (!isset($categories[$category]['intents'][$intent])) {
            return false;
        }

        return true;
    }

    public static function getIntentInfo($category, $intent)
    {
        $categories = require Yii::getAlias('@common/config/chatbot/intent-categories.php');

        if (!isset($categories[$category]['intents'][$intent])) {
            return null;
        }

        return $categories[$category]['intents'][$intent];
    }
}


