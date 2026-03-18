<?php

namespace common\components\Chatbot;

use Yii;
use ReflectionClass;
use yii\db\ActiveRecord;
use common\components\ModelDiscoveryService;

/**
 * Servicio para extraer metadata del chatbot desde anotaciones en modelos.
 * Implementación movida desde common\components\ChatbotMetadataExtractor.
 */
class ChatbotMetadataExtractor
{
    private static $modelPaths = [
        '@common/models',
    ];

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

        $models = ModelDiscoveryService::discoverAllModels(false);

        foreach ($models as $modelInfo) {
            $className = $modelInfo['class'];

            try {
                $reflection = new ReflectionClass($className);
                $docComment = $reflection->getDocComment();

                if (!$docComment) {
                    continue;
                }

                $metadata = self::extractModelChatbotMetadata($docComment, $modelInfo);

                if ($metadata && isset($metadata['category'])) {
                    $categoryKey = $metadata['category']['key'];
                    $categories[$categoryKey] = $metadata['category'];

                    foreach ($metadata['intents'] as $intentKey => $intent) {
                        $intentParameters[$intentKey] = $intent['parameters'];
                    }

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

        if ($cache) {
            $cache->set($cacheKey, $result, 3600);
        }

        return $result;
    }

    private static function extractModelChatbotMetadata($docComment, $modelInfo)
    {
        $lines = explode("\n", $docComment);

        $category = null;
        $intents = [];
        $patientReferences = [];
        $currentIntent = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/@chatbot-category\s+(\w+)/i', $line, $matches)) {
                $categoryKey = trim($matches[1]);
                $category = [
                    'key' => $categoryKey,
                    'name' => null,
                    'description' => null,
                ];
            }

            if (preg_match('/@chatbot-category-name\s+"([^"]+)"/i', $line, $matches)) {
                if ($category) {
                    $category['name'] = trim($matches[1]);
                }
            }

            if (preg_match('/@chatbot-category-description\s+"([^"]+)"/i', $line, $matches)) {
                if ($category) {
                    $category['description'] = trim($matches[1]);
                }
            }

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

            if ($currentIntent && isset($intents[$currentIntent])) {
                $intent = &$intents[$currentIntent];

                if (preg_match('/@chatbot-intent-name\s+"([^"]+)"/i', $line, $matches)) {
                    $intent['name'] = trim($matches[1]);
                }

                if (preg_match('/@chatbot-intent-handler\s+(\w+)/i', $line, $matches)) {
                    $intent['handler'] = trim($matches[1]);
                }

                if (preg_match('/@chatbot-intent-priority\s+(critical|high|medium|low)/i', $line, $matches)) {
                    $intent['priority'] = strtolower(trim($matches[1]));
                }

                if (preg_match('/@chatbot-intent-keywords\s+"([^"]+)"/i', $line, $matches)) {
                    $keywords = array_map('trim', explode(',', $matches[1]));
                    $intent['keywords'] = array_filter($keywords, fn($k) => !empty($k));
                }

                if (preg_match('/@chatbot-intent-patterns\s+"([^"]+)"/i', $line, $matches)) {
                    $patterns = array_map('trim', explode(',', $matches[1]));
                    $intent['patterns'] = array_filter($patterns, fn($p) => !empty($p));
                }

                if (preg_match('/@chatbot-intent-required-params\s+([\w,]+)/i', $line, $matches)) {
                    $params = array_map('trim', explode(',', $matches[1]));
                    $intent['parameters']['required_params'] = array_filter($params, fn($p) => !empty($p));
                }

                if (preg_match('/@chatbot-intent-optional-params\s+([\w,]+)/i', $line, $matches)) {
                    $params = array_map('trim', explode(',', $matches[1]));
                    $intent['parameters']['optional_params'] = array_filter($params, fn($p) => !empty($p));
                }

                if (preg_match('/@chatbot-intent-lifetime\s+(\d+)/i', $line, $matches)) {
                    $intent['parameters']['lifetime'] = (int) $matches[1];
                }

                if (preg_match('/@chatbot-intent-patient-profile-can-use\s+([\w,]+)/i', $line, $matches)) {
                    $canUse = array_map('trim', explode(',', $matches[1]));
                    $intent['parameters']['patient_profile']['can_use'] = array_filter($canUse, fn($u) => !empty($u));
                }

                if (preg_match('/@chatbot-intent-patient-profile-resolve-references\s+(true|false)/i', $line, $matches)) {
                    $intent['parameters']['patient_profile']['resolve_references'] = strtolower($matches[1]) === 'true';
                }

                if (preg_match('/@chatbot-intent-patient-profile-update-on-complete-type\s+(\w+)/i', $line, $matches)) {
                    $intent['parameters']['patient_profile']['update_on_complete']['type'] = trim($matches[1]);
                }

                if (preg_match('/@chatbot-intent-patient-profile-update-on-complete-fields\s+([\w,]+)/i', $line, $matches)) {
                    $fields = array_map('trim', explode(',', $matches[1]));
                    $intent['parameters']['patient_profile']['update_on_complete']['fields'] = array_filter($fields, fn($f) => !empty($f));
                }

                if (preg_match('/@chatbot-intent-patient-profile-cache-ttl\s+(\d+)/i', $line, $matches)) {
                    $intent['parameters']['patient_profile']['cache_ttl'] = (int) $matches[1];
                }
            }
        }

        if (!$category) {
            return null;
        }

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

    public static function invalidateCache()
    {
        $cache = Yii::$app->cache;
        if ($cache) {
            $cache->delete('chatbot_metadata_all');
        }
    }
}

