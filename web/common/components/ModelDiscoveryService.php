<?php

namespace common\components;

use Yii;
use yii\helpers\FileHelper;
use ReflectionClass;
use ReflectionMethod;
use yii\db\ActiveRecord;

/**
 * Servicio para descubrir dinámicamente todos los modelos ActiveRecord disponibles
 * y extraer metadatos como atributos, relaciones, validaciones y mapeo a controladores
 */
class ModelDiscoveryService
{
    /**
     * Directorios donde buscar modelos
     */
    private static $modelPaths = [
        '@common/models',
    ];

    /**
     * Cache key para modelos descubiertos
     */
    const CACHE_KEY_MODELS = 'discovered_models_all';
    const CACHE_DURATION = 3600; // 1 hora

    /**
     * Descubrir todos los modelos ActiveRecord disponibles
     * @param bool $useCache Usar cache si está disponible
     * @return array Array de modelos con metadatos
     */
    public static function discoverAllModels($useCache = true)
    {
        $cache = Yii::$app->cache;
        $cacheKey = self::CACHE_KEY_MODELS;

        if ($useCache && $cache) {
            $cached = $cache->get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $models = [];

        foreach (self::$modelPaths as $path) {
            $realPath = Yii::getAlias($path);
            if (!is_dir($realPath)) {
                continue;
            }

            $files = FileHelper::findFiles($realPath, [
                'only' => ['*.php'],
                'recursive' => true,
            ]);

            foreach ($files as $file) {
                $modelInfo = self::extractModelFromFile($file, $path);
                if ($modelInfo) {
                    $models[] = $modelInfo;
                }
            }
        }

        // Guardar en cache
        if ($cache) {
            $cache->set($cacheKey, $models, self::CACHE_DURATION);
        }

        return $models;
    }

    /**
     * Extraer información de un modelo desde un archivo
     * @param string $filePath Ruta completa del archivo
     * @param string $basePath Path base para generar rutas
     * @return array|null
     */
    private static function extractModelFromFile($filePath, $basePath)
    {
        $className = self::getClassNameFromFile($filePath);

        if (!$className) {
            return null;
        }

        try {
            // Verificar que la clase existe y es ActiveRecord
            if (!class_exists($className)) {
                return null;
            }

            $reflection = new ReflectionClass($className);
            
            // Verificar que sea ActiveRecord
            if (!$reflection->isSubclassOf('yii\db\ActiveRecord')) {
                return null;
            }

            // Obtener nombre del modelo desde el nombre de clase
            $modelName = $reflection->getShortName();
            
            // Extraer metadatos del modelo
            $metadata = self::extractModelMetadata($reflection, $className);

            return [
                'class' => $className,
                'name' => $modelName,
                'table_name' => $metadata['table_name'],
                'attributes' => $metadata['attributes'],
                'rules' => $metadata['rules'],
                'labels' => $metadata['labels'],
                'relations' => $metadata['relations'],
                'controller' => self::findControllerForModel($modelName),
            ];

        } catch (\Exception $e) {
            Yii::error("Error extrayendo modelo de {$filePath}: " . $e->getMessage(), 'model-discovery');
            return null;
        }
    }

    /**
     * Obtener nombre de clase desde archivo PHP
     * @param string $filePath
     * @return string|null
     */
    private static function getClassNameFromFile($filePath)
    {
        $content = file_get_contents($filePath);
        
        // Buscar namespace
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
        } else {
            return null;
        }

        // Buscar nombre de clase
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $className = $namespace . '\\' . $matches[1];
            return $className;
        }

        return null;
    }

    /**
     * Extraer metadatos de un modelo
     * @param ReflectionClass $reflection
     * @param string $className
     * @return array
     */
    private static function extractModelMetadata($reflection, $className)
    {
        $metadata = [
            'table_name' => null,
            'attributes' => [],
            'rules' => [],
            'labels' => [],
            'relations' => [],
        ];

        try {
            // Obtener nombre de tabla
            if ($reflection->hasMethod('tableName')) {
                $method = $reflection->getMethod('tableName');
                if ($method->isStatic()) {
                    $metadata['table_name'] = $method->invoke(null);
                }
            }

            // Obtener atributos desde un modelo de ejemplo
            if (method_exists($className, 'tableName')) {
                // Verificar si la clase es abstracta antes de instanciar
                if ($reflection->isAbstract()) {
                    // Si es abstracta, usar métodos estáticos para obtener información
                    if ($reflection->hasMethod('tableName')) {
                        $method = $reflection->getMethod('tableName');
                        if ($method->isStatic()) {
                            $metadata['table_name'] = $method->invoke(null);
                        }
                    }
                    // No podemos obtener atributos de una clase abstracta sin instanciarla
                    // Saltar la obtención de atributos
                } else {
                    // La clase no es abstracta, podemos instanciarla
                    $model = new $className();
                    $metadata['table_name'] = $model::tableName();
                    
                    // Obtener atributos del modelo
                    $schema = $model::getTableSchema();
                    if ($schema) {
                        foreach ($schema->columns as $column) {
                            $metadata['attributes'][] = [
                                'name' => $column->name,
                                'type' => $column->type,
                                'phpType' => $column->phpType,
                                'size' => $column->size,
                                'allowNull' => $column->allowNull,
                                'defaultValue' => $column->defaultValue,
                                'isPrimaryKey' => $column->isPrimaryKey,
                                'autoIncrement' => $column->autoIncrement,
                            ];
                        }
                    }
                }
            }

            // Obtener reglas de validación
            if ($reflection->hasMethod('rules') && !$reflection->isAbstract()) {
                $method = $reflection->getMethod('rules');
                if ($method->isPublic()) {
                    try {
                        $model = new $className();
                        $metadata['rules'] = $model->rules();
                    } catch (\Exception $e) {
                        // Ignorar errores al instanciar
                    }
                }
            }

            // Obtener labels
            if ($reflection->hasMethod('attributeLabels') && !$reflection->isAbstract()) {
                $method = $reflection->getMethod('attributeLabels');
                if ($method->isPublic()) {
                    try {
                        $model = new $className();
                        $metadata['labels'] = $model->attributeLabels();
                    } catch (\Exception $e) {
                        // Ignorar errores al instanciar
                    }
                }
            }

            // Intentar detectar relaciones desde métodos get*
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $method) {
                $methodName = $method->getName();
                if (strpos($methodName, 'get') === 0 && strlen($methodName) > 3) {
                    // Podría ser una relación, pero no lo verificamos aquí para evitar instanciar
                }
            }

        } catch (\Exception $e) {
            Yii::error("Error extrayendo metadatos: " . $e->getMessage(), 'model-discovery');
        }

        return $metadata;
    }

    /**
     * Buscar controlador asociado a un modelo
     * @param string $modelName
     * @return string|null
     */
    private static function findControllerForModel($modelName)
    {
        // Convertir nombre de modelo a nombre de controlador
        // Ej: Persona -> persona, Consulta -> consulta
        $controllerName = strtolower($modelName);
        
        // Buscar en acciones descubiertas
        $actions = ActionDiscoveryService::discoverAllActions(false);
        
        foreach ($actions as $action) {
            if (stripos($action['controller'], $controllerName) !== false) {
                return $action['controller'];
            }
        }

        return null;
    }

    /**
     * Buscar modelo por nombre (fuzzy matching)
     * @param string $query Nombre o parte del nombre del modelo
     * @return array|null
     */
    public static function findModelByName($query)
    {
        $models = self::discoverAllModels();
        $query = strtolower($query);
        
        // Buscar coincidencia exacta primero
        foreach ($models as $model) {
            if (strtolower($model['name']) === $query) {
                return $model;
            }
        }

        // Buscar coincidencia parcial
        foreach ($models as $model) {
            if (stripos($model['name'], $query) !== false) {
                return $model;
            }
        }

        // Buscar en nombres de tabla
        foreach ($models as $model) {
            if ($model['table_name'] && stripos($model['table_name'], $query) !== false) {
                return $model;
            }
        }

        return null;
    }

    /**
     * Obtener modelos disponibles para un usuario según permisos
     * @param int|null $userId
     * @return array
     */
    public static function getAvailableModelsForUser($userId = null)
    {
        $allModels = self::discoverAllModels();
        $availableActions = ActionMappingService::getAvailableActionsForUser($userId);
        
        // Filtrar modelos que tienen acciones disponibles para el usuario
        $availableModels = [];
        
        foreach ($allModels as $model) {
            if ($model['controller']) {
                // Verificar si hay acciones CRUD disponibles para este controlador
                foreach ($availableActions as $action) {
                    if ($action['controller'] === $model['controller']) {
                        $availableModels[] = $model;
                        break;
                    }
                }
            }
        }

        return $availableModels;
    }

    /**
     * Invalidar cache de modelos
     */
    public static function invalidateCache()
    {
        $cache = Yii::$app->cache;
        if ($cache) {
            $cache->delete(self::CACHE_KEY_MODELS);
        }
    }
}

