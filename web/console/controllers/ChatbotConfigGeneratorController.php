<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use common\components\ChatbotMetadataExtractor;
use yii\helpers\FileHelper;

/**
 * Controlador para generar archivos de configuración del chatbot
 * desde anotaciones en modelos
 * 
 * Uso:
 *   php yii chatbot-config/generate              - Generar todos los archivos
 *   php yii chatbot-config/generate --file=categories  - Solo intent-categories.php
 *   php yii chatbot-config/generate --file=parameters  - Solo intent-parameters.php
 *   php yii chatbot-config/generate --force       - Forzar regeneración (ignorar cache)
 *   php yii chatbot-config/validate               - Validar configuración sin generar
 */
class ChatbotConfigGeneratorController extends Controller
{
    /**
     * @var string Archivo específico a generar (categories, parameters, references)
     */
    public $file;

    /**
     * @var bool Forzar regeneración ignorando cache
     */
    public $force = false;

    /**
     * {@inheritdoc}
     */
    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['file', 'force']);
    }

    /**
     * Generar archivos de configuración del chatbot
     */
    public function actionGenerate()
    {
        $this->stdout("Iniciando generación de configuración del chatbot...\n", \yii\helpers\Console::FG_YELLOW);

        try {
            // Invalidar cache si se fuerza
            if ($this->force) {
                ChatbotMetadataExtractor::invalidateCache();
                $this->stdout("Cache invalidado.\n", \yii\helpers\Console::FG_GREEN);
            }

            // Extraer metadata desde modelos
            $this->stdout("Extrayendo metadata de modelos...\n", \yii\helpers\Console::FG_YELLOW);
            $metadata = ChatbotMetadataExtractor::extractAll(!$this->force);

            if (empty($metadata['categories'])) {
                $this->stdout("No se encontraron modelos con anotaciones @chatbot-category.\n", \yii\helpers\Console::FG_YELLOW);
                $this->stdout("Asegúrate de agregar anotaciones en los modelos.\n", \yii\helpers\Console::FG_YELLOW);
                return Controller::EXIT_CODE_NORMAL;
            }

            $this->stdout("Categorías encontradas: " . count($metadata['categories']) . "\n", \yii\helpers\Console::FG_GREEN);

            // Generar archivos según parámetro
            $filesToGenerate = [];
            if ($this->file) {
                switch ($this->file) {
                    case 'categories':
                        $filesToGenerate[] = 'categories';
                        break;
                    case 'parameters':
                        $filesToGenerate[] = 'parameters';
                        break;
                    case 'references':
                        $filesToGenerate[] = 'references';
                        break;
                    default:
                        $this->stdout("Archivo desconocido: {$this->file}\n", \yii\helpers\Console::FG_RED);
                        $this->stdout("Opciones válidas: categories, parameters, references\n", \yii\helpers\Console::FG_RED);
                        return Controller::EXIT_CODE_ERROR;
                }
            } else {
                $filesToGenerate = ['categories', 'parameters', 'references'];
            }

            // Generar cada archivo
            foreach ($filesToGenerate as $fileType) {
                $this->stdout("\nGenerando {$fileType}...\n", \yii\helpers\Console::FG_YELLOW);
                
                switch ($fileType) {
                    case 'categories':
                        $this->generateIntentCategories($metadata['categories']);
                        break;
                    case 'parameters':
                        $this->generateIntentParameters($metadata['intent_parameters']);
                        break;
                    case 'references':
                        $this->generatePatientReferences($metadata['patient_references']);
                        break;
                }
            }

            $this->stdout("\n¡Configuración generada exitosamente!\n", \yii\helpers\Console::FG_GREEN);
            return Controller::EXIT_CODE_NORMAL;

        } catch (\Exception $e) {
            $this->stdout("Error: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            $this->stdout($e->getTraceAsString() . "\n", \yii\helpers\Console::FG_RED);
            return Controller::EXIT_CODE_ERROR;
        }
    }

    /**
     * Validar configuración sin generar archivos
     */
    public function actionValidate()
    {
        $this->stdout("Validando configuración del chatbot...\n", \yii\helpers\Console::FG_YELLOW);

        try {
            $metadata = ChatbotMetadataExtractor::extractAll();
            
            $errors = [];
            $warnings = [];

            // Validar categorías
            foreach ($metadata['categories'] as $categoryKey => $category) {
                if (empty($category['name'])) {
                    $warnings[] = "Categoría '{$categoryKey}' no tiene nombre";
                }
                
                if (empty($category['intents'])) {
                    $warnings[] = "Categoría '{$categoryKey}' no tiene intents";
                }
                
                // Validar intents
                foreach ($category['intents'] as $intentKey => $intent) {
                    if (empty($intent['handler'])) {
                        $errors[] = "Intent '{$intentKey}' no tiene handler definido";
                    } else {
                        $handlerClass = "common\\components\\intent_handlers\\{$intent['handler']}";
                        if (!class_exists($handlerClass)) {
                            $errors[] = "Handler '{$intent['handler']}' no existe para intent '{$intentKey}'";
                        }
                    }
                    
                    if (empty($intent['name'])) {
                        $warnings[] = "Intent '{$intentKey}' no tiene nombre";
                    }
                }
            }

            // Mostrar resultados
            if (empty($errors) && empty($warnings)) {
                $this->stdout("✓ Validación exitosa. No se encontraron errores ni advertencias.\n", \yii\helpers\Console::FG_GREEN);
                return Controller::EXIT_CODE_NORMAL;
            }

            if (!empty($errors)) {
                $this->stdout("\nErrores encontrados:\n", \yii\helpers\Console::FG_RED);
                foreach ($errors as $error) {
                    $this->stdout("  ✗ {$error}\n", \yii\helpers\Console::FG_RED);
                }
            }

            if (!empty($warnings)) {
                $this->stdout("\nAdvertencias:\n", \yii\helpers\Console::FG_YELLOW);
                foreach ($warnings as $warning) {
                    $this->stdout("  ⚠ {$warning}\n", \yii\helpers\Console::FG_YELLOW);
                }
            }

            return !empty($errors) ? Controller::EXIT_CODE_ERROR : Controller::EXIT_CODE_NORMAL;

        } catch (\Exception $e) {
            $this->stdout("Error: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            return Controller::EXIT_CODE_ERROR;
        }
    }

    /**
     * Generar archivo intent-categories.php
     */
    private function generateIntentCategories($categories)
    {
        $filePath = Yii::getAlias('@common/config/chatbot/intent-categories.php');
        $dir = dirname($filePath);
        
        if (!is_dir($dir)) {
            FileHelper::createDirectory($dir);
        }

        $content = "<?php\n";
        $content .= "/**\n";
        $content .= " * Configuración de categorías e intents para el orquestador de consultas\n";
        $content .= " * \n";
        $content .= " * Este archivo es GENERADO AUTOMÁTICAMENTE desde anotaciones en modelos.\n";
        $content .= " * NO editar manualmente. Para modificar, edita las anotaciones @chatbot-* en los modelos.\n";
        $content .= " * \n";
        $content .= " * Generado: " . date('Y-m-d H:i:s') . "\n";
        $content .= " * Comando: php yii chatbot-config/generate\n";
        $content .= " */\n\n";
        $content .= "return [\n";

        foreach ($categories as $categoryKey => $category) {
            $content .= "    // " . strtoupper($category['name'] ?? $categoryKey) . "\n";
            $content .= "    '{$categoryKey}' => [\n";
            $content .= "        'name' => " . $this->formatString($category['name'] ?? '') . ",\n";
            $content .= "        'description' => " . $this->formatString($category['description'] ?? '') . ",\n";
            $content .= "        'intents' => [\n";

            foreach ($category['intents'] as $intentKey => $intent) {
                $content .= "            '{$intentKey}' => [\n";
                $content .= "                'name' => " . $this->formatString($intent['name'] ?? '') . ",\n";
                
                if (!empty($intent['keywords'])) {
                    $content .= "                'keywords' => [\n";
                    foreach ($intent['keywords'] as $keyword) {
                        $content .= "                    " . $this->formatString($keyword) . ",\n";
                    }
                    $content .= "                ],\n";
                }
                
                if (!empty($intent['patterns'])) {
                    $content .= "                'patterns' => [\n";
                    foreach ($intent['patterns'] as $pattern) {
                        $content .= "                    " . $this->formatString($pattern) . ",\n";
                    }
                    $content .= "                ],\n";
                }
                
                if (!empty($intent['handler'])) {
                    $content .= "                'handler' => " . $this->formatString($intent['handler']) . ",\n";
                }
                
                if (!empty($intent['priority'])) {
                    $content .= "                'priority' => " . $this->formatString($intent['priority']) . "\n";
                }
                
                $content .= "            ],\n";
            }

            $content .= "        ]\n";
            $content .= "    ],\n\n";
        }

        $content .= "];\n";

        file_put_contents($filePath, $content);
        $this->stdout("✓ Generado: {$filePath}\n", \yii\helpers\Console::FG_GREEN);
    }

    /**
     * Generar archivo intent-parameters.php
     */
    private function generateIntentParameters($intentParameters)
    {
        $filePath = Yii::getAlias('@common/config/chatbot/intent-parameters.php');
        $dir = dirname($filePath);
        
        if (!is_dir($dir)) {
            FileHelper::createDirectory($dir);
        }

        $content = "<?php\n";
        $content .= "/**\n";
        $content .= " * Configuración de parámetros por intent\n";
        $content .= " * \n";
        $content .= " * Este archivo es GENERADO AUTOMÁTICAMENTE desde anotaciones en modelos.\n";
        $content .= " * NO editar manualmente. Para modificar, edita las anotaciones @chatbot-* en los modelos.\n";
        $content .= " * \n";
        $content .= " * Generado: " . date('Y-m-d H:i:s') . "\n";
        $content .= " * Comando: php yii chatbot-config/generate\n";
        $content .= " */\n\n";
        $content .= "return [\n";

        foreach ($intentParameters as $intentKey => $params) {
            $content .= "    '{$intentKey}' => [\n";
            
            if (!empty($params['required_params'])) {
                $content .= "        'required_params' => [";
                $content .= implode(', ', array_map(function($p) { return $this->formatString($p); }, $params['required_params']));
                $content .= "],\n";
            } else {
                $content .= "        'required_params' => [],\n";
            }
            
            if (!empty($params['optional_params'])) {
                $content .= "        'optional_params' => [";
                $content .= implode(', ', array_map(function($p) { return $this->formatString($p); }, $params['optional_params']));
                $content .= "],\n";
            } else {
                $content .= "        'optional_params' => [],\n";
            }
            
            $content .= "        'lifetime' => " . ($params['lifetime'] ?? 300) . ",\n";
            
            if (!empty($params['cleanup_on'])) {
                $content .= "        'cleanup_on' => [";
                $content .= implode(', ', array_map(function($c) { return $this->formatString($c); }, $params['cleanup_on']));
                $content .= "],\n";
            }
            
            if (!empty($params['patient_profile'])) {
                $content .= "        'patient_profile' => [\n";
                $profile = $params['patient_profile'];
                
                if (!empty($profile['can_use'])) {
                    $content .= "            'can_use' => [";
                    $content .= implode(', ', array_map(function($u) { return $this->formatString($u); }, $profile['can_use']));
                    $content .= "],\n";
                } else {
                    $content .= "            'can_use' => [],\n";
                }
                
                if (isset($profile['resolve_references'])) {
                    $content .= "            'resolve_references' => " . ($profile['resolve_references'] ? 'true' : 'false') . ",\n";
                }
                
                if (!empty($profile['update_on_complete'])) {
                    $content .= "            'update_on_complete' => [\n";
                    if (!empty($profile['update_on_complete']['type'])) {
                        $content .= "                'type' => " . $this->formatString($profile['update_on_complete']['type']) . ",\n";
                    }
                    if (!empty($profile['update_on_complete']['fields'])) {
                        $content .= "                'fields' => [";
                        $content .= implode(', ', array_map(function($f) { return $this->formatString($f); }, $profile['update_on_complete']['fields']));
                        $content .= "]\n";
                    }
                    $content .= "            ],\n";
                }
                
                if (!empty($profile['cache_ttl'])) {
                    $content .= "            'cache_ttl' => " . $profile['cache_ttl'] . "\n";
                }
                
                $content .= "        ]\n";
            }
            
            $content .= "    ],\n\n";
        }

        $content .= "];\n";

        file_put_contents($filePath, $content);
        $this->stdout("✓ Generado: {$filePath}\n", \yii\helpers\Console::FG_GREEN);
    }

    /**
     * Generar archivo patient-references.php
     */
    private function generatePatientReferences($patientReferences)
    {
        $filePath = Yii::getAlias('@common/config/chatbot/patient-references.php');
        $dir = dirname($filePath);
        
        if (!is_dir($dir)) {
            FileHelper::createDirectory($dir);
        }

        $content = "<?php\n";
        $content .= "/**\n";
        $content .= " * Referencias del paciente para el chatbot\n";
        $content .= " * \n";
        $content .= " * Este archivo es GENERADO AUTOMÁTICAMENTE desde anotaciones en modelos.\n";
        $content .= " * NO editar manualmente. Para modificar, edita las anotaciones @chatbot-* en los modelos.\n";
        $content .= " * \n";
        $content .= " * Generado: " . date('Y-m-d H:i:s') . "\n";
        $content .= " * Comando: php yii chatbot-config/generate\n";
        $content .= " */\n\n";
        $content .= "return [\n";

        if (empty($patientReferences)) {
            $content .= "    // No hay referencias definidas\n";
        } else {
            foreach ($patientReferences as $referenceKey => $reference) {
                $content .= "    '{$referenceKey}' => [\n";
                foreach ($reference as $key => $value) {
                    if (is_array($value)) {
                        $content .= "        '{$key}' => [";
                        $content .= implode(', ', array_map(function($v) { return $this->formatString($v); }, $value));
                        $content .= "],\n";
                    } else {
                        $content .= "        '{$key}' => " . $this->formatString($value) . ",\n";
                    }
                }
                $content .= "    ],\n";
            }
        }

        $content .= "];\n";

        file_put_contents($filePath, $content);
        $this->stdout("✓ Generado: {$filePath}\n", \yii\helpers\Console::FG_GREEN);
    }

    /**
     * Formatear string para PHP
     */
    private function formatString($value)
    {
        if (is_numeric($value)) {
            return $value;
        }
        return "'" . addslashes($value) . "'";
    }
}
