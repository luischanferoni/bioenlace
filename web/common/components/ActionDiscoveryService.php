<?php

namespace common\components;

use Yii;
use yii\helpers\FileHelper;
use ReflectionClass;
use ReflectionMethod;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\models\rbacDB\Route;

/**
 * Servicio para descubrir dinámicamente todas las acciones disponibles en los controladores
 * y extraer metadatos como descripciones, parámetros y permisos por rol
 */
class ActionDiscoveryService
{
    /**
     * Directorios donde buscar controladores
     */
    private static $controllerPaths = [
        '@frontend/controllers',
        '@backend/controllers',
    ];

    /**
     * Cache key para acciones descubiertas
     */
    const CACHE_KEY_ACTIONS = 'discovered_actions_all';
    const CACHE_DURATION = 3600; // 1 hora

    /**
     * Descubrir todas las acciones disponibles en el sistema
     * @param bool $useCache Usar cache si está disponible
     * @return array Array de acciones con metadatos
     */
    public static function discoverAllActions($useCache = true)
    {
        $cache = Yii::$app->cache;
        $cacheKey = self::CACHE_KEY_ACTIONS;

        if ($useCache && $cache) {
            $cached = $cache->get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $actions = [];

        foreach (self::$controllerPaths as $path) {
            $realPath = Yii::getAlias($path);
            if (!is_dir($realPath)) {
                continue;
            }

            $files = FileHelper::findFiles($realPath, [
                'only' => ['*Controller.php'],
                'recursive' => false,
            ]);

            foreach ($files as $file) {
                $controllerActions = self::extractActionsFromFile($file, $path);
                $actions = array_merge($actions, $controllerActions);
            }
        }

        // Guardar en cache
        if ($cache) {
            $cache->set($cacheKey, $actions, self::CACHE_DURATION);
        }

        return $actions;
    }

    /**
     * Extraer acciones de un archivo de controlador
     * @param string $filePath Ruta completa del archivo
     * @param string $basePath Path base para generar rutas
     * @return array
     */
    private static function extractActionsFromFile($filePath, $basePath)
    {
        $actions = [];
        $className = self::getClassNameFromFile($filePath);

        if (!$className) {
            return $actions;
        }

        try {
            $reflection = new ReflectionClass($className);
            
            // Verificar que sea un controlador
            if (!$reflection->isSubclassOf('yii\web\Controller') && 
                !$reflection->isSubclassOf('yii\rest\Controller')) {
                return $actions;
            }

            // Obtener nombre del controlador desde el nombre de clase
            $controllerName = self::getControllerName($reflection->getShortName());
            
            // Determinar el módulo si existe
            $module = self::extractModuleFromNamespace($reflection->getNamespaceName(), $basePath);
            
            // Obtener métodos de acciones
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
            
            foreach ($methods as $method) {
                if (strpos($method->getName(), 'action') === 0 && 
                    $method->getName() !== 'actions') {
                    
                    $actionName = str_replace('action', '', $method->getName());
                    $actionName = lcfirst($actionName);
                    
                    // Generar ruta
                    $route = self::generateRoute($module, $controllerName, $actionName);
                    
                    // Extraer metadatos
                    $metadata = self::extractActionMetadata($method, $route, $controllerName, $actionName);
                    
                    if ($metadata) {
                        $actions[] = $metadata;
                    }
                }
            }

            // También procesar acciones definidas en el método actions()
            $actionsFromMethod = self::extractActionsFromActionsMethod($reflection, $module, $controllerName);
            $actions = array_merge($actions, $actionsFromMethod);

        } catch (\Exception $e) {
            Yii::error("Error extrayendo acciones de {$filePath}: " . $e->getMessage(), 'action-discovery');
        }

        return $actions;
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
     * Obtener nombre del controlador desde el nombre de clase
     * @param string $className
     * @return string
     */
    private static function getControllerName($className)
    {
        // Remover "Controller" del final
        $name = str_replace('Controller', '', $className);
        // Convertir a camelCase a kebab-case
        $name = preg_replace('/([a-z])([A-Z])/', '$1-$2', $name);
        return strtolower($name);
    }

    /**
     * Extraer módulo del namespace
     * @param string $namespace
     * @param string $basePath
     * @return string|null
     */
    private static function extractModuleFromNamespace($namespace, $basePath)
    {
        // Detectar si está en un módulo (ej: frontend\modules\api\v1\controllers)
        if (preg_match('/modules\\\\(\w+)\\\\controllers/', $namespace, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Generar ruta canónica
     * @param string|null $module
     * @param string $controller
     * @param string $action
     * @return string
     */
    private static function generateRoute($module, $controller, $action)
    {
        $path = Yii::$app->params['path'] ?? '';
        
        // Limpiar path (remover barra inicial si existe)
        $path = trim($path, '/');
        
        if ($module) {
            return '/' . $path . '/' . $module . '/' . $controller . '/' . $action;
        }
        
        return '/' . $path . '/' . $controller . '/' . $action;
    }

    /**
     * Extraer metadatos de una acción
     * @param ReflectionMethod $method
     * @param string $route
     * @param string $controllerName
     * @param string $actionName
     * @return array|null
     */
    private static function extractActionMetadata($method, $route, $controllerName, $actionName)
    {
        $docComment = $method->getDocComment();
        
        // Extraer descripción del docblock
        $description = self::extractDescriptionFromDocblock($docComment);
        
        // Si no hay descripción, generar una básica
        if (empty($description)) {
            $description = ucfirst(str_replace('-', ' ', $actionName));
        }

        // NUEVO: Extraer metadatos personalizados del docblock
        $customMetadata = self::extractCustomMetadata($docComment, $controllerName, $actionName);

        // Extraer parámetros con metadatos
        $parameters = [];
        foreach ($method->getParameters() as $param) {
            $paramInfo = [
                'name' => $param->getName(),
                'required' => !$param->isOptional(),
            ];
            
            if ($param->isOptional()) {
                try {
                    $paramInfo['default'] = $param->getDefaultValue();
                } catch (\ReflectionException $e) {
                    // No se puede obtener el valor por defecto
                }
            }
            
            // Extraer metadatos del parámetro desde docblock
            $paramMetadata = self::extractParameterMetadata($docComment, $param->getName());
            $paramInfo = array_merge($paramInfo, $paramMetadata);
            
            $parameters[] = $paramInfo;
        }

        // Generar nombre descriptivo
        $displayName = self::generateDisplayName($controllerName, $actionName, $description);

        // Generar identificador único: controlador.accion
        $actionId = strtolower($controllerName . '.' . $actionName);

        return [
            'route' => $route,
            'action_id' => $actionId,
            'controller' => $controllerName,
            'action' => $actionName,
            'display_name' => $displayName,
            'description' => $description,
            'parameters' => $parameters,
            'method' => $method->getName(),
            // NUEVO: Agregar metadatos extraídos
            'category' => $customMetadata['category'],
            'tags' => $customMetadata['tags'],
            'keywords' => $customMetadata['keywords'],
            'synonyms' => $customMetadata['synonyms'],
        ];
    }

    /**
     * Extraer descripción del docblock
     * @param string|false $docComment
     * @return string
     */
    private static function extractDescriptionFromDocblock($docComment)
    {
        if (!$docComment) {
            return '';
        }

        // Buscar la primera línea de descripción (después de /**)
        $lines = explode("\n", $docComment);
        $description = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            // Saltar comentarios vacíos y tags
            if (empty($line) || $line === '/**' || $line === '*/' || strpos($line, '*') === 0 && strlen(trim($line)) <= 2) {
                continue;
            }
            
            // Si empieza con * y no es un tag (@), es descripción
            if (strpos($line, '*') === 0) {
                $line = trim(substr($line, 1));
                if (!empty($line) && strpos($line, '@') !== 0) {
                    $description = $line;
                    break;
                }
            }
        }

        return trim($description);
    }

    /**
     * Generar nombre descriptivo para mostrar
     * @param string $controller
     * @param string $action
     * @param string $description
     * @return string
     */
    private static function generateDisplayName($controller, $action, $description)
    {
        // Si hay descripción, usar las primeras palabras
        if (!empty($description) && strlen($description) < 50) {
            return $description;
        }

        // Generar nombre desde controller y action
        $controllerWords = str_replace('-', ' ', $controller);
        $actionWords = str_replace('-', ' ', $action);
        
        return ucwords($controllerWords) . ' - ' . ucwords($actionWords);
    }

    /**
     * Extraer acciones del método actions()
     * @param ReflectionClass $reflection
     * @param string|null $module
     * @param string $controllerName
     * @return array
     */
    private static function extractActionsFromActionsMethod($reflection, $module, $controllerName)
    {
        $actions = [];
        
        try {
            if ($reflection->hasMethod('actions')) {
                $method = $reflection->getMethod('actions');
                if ($method->isPublic() && $method->isStatic() === false) {
                    // Intentar obtener instancia del controlador (puede fallar)
                    try {
                        $controller = $reflection->newInstanceWithoutConstructor();
                        $actionsArray = $method->invoke($controller);
                        
                        foreach ($actionsArray as $actionName => $actionConfig) {
                            $route = self::generateRoute($module, $controllerName, $actionName);
                            $actions[] = [
                                'route' => $route,
                                'controller' => $controllerName,
                                'action' => $actionName,
                                'display_name' => ucwords(str_replace('-', ' ', $actionName)),
                                'description' => 'Acción definida en método actions()',
                                'parameters' => [],
                                'method' => 'actions',
                            ];
                        }
                    } catch (\Exception $e) {
                        // No se puede instanciar, saltar
                    }
                }
            }
        } catch (\Exception $e) {
            // Método no existe o error al acceder
        }

        return $actions;
    }

    /**
     * Extraer metadatos personalizados del docblock
     * Soporta: @category, @tags, @keywords, @synonyms
     * 
     * @param string|false $docComment
     * @param string $controllerName
     * @param string $actionName
     * @return array
     */
    private static function extractCustomMetadata($docComment, $controllerName, $actionName)
    {
        $metadata = [
            'category' => null,
            'tags' => [],
            'keywords' => [],
            'synonyms' => [],
        ];
        
        if (!$docComment) {
            // Si no hay docblock, inferir desde el nombre del controlador
            $metadata['category'] = self::inferCategory($controllerName);
            $metadata['tags'] = self::inferTags($controllerName, $actionName);
            $metadata['keywords'] = self::inferKeywords($actionName);
            return $metadata;
        }
        
        $lines = explode("\n", $docComment);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // @category Licencias
            if (preg_match('/@category\s+(.+)/i', $line, $matches)) {
                $metadata['category'] = trim($matches[1]);
            }
            
            // @tags licencia,permiso,vacaciones
            if (preg_match('/@tags\s+(.+)/i', $line, $matches)) {
                $tags = array_map('trim', explode(',', $matches[1]));
                $metadata['tags'] = array_filter($tags, function($tag) {
                    return !empty($tag);
                });
            }
            
            // @keywords buscar,listar,filtrar
            if (preg_match('/@keywords\s+(.+)/i', $line, $matches)) {
                $keywords = array_map('trim', explode(',', $matches[1]));
                $metadata['keywords'] = array_filter($keywords, function($keyword) {
                    return !empty($keyword);
                });
            }
            
            // @synonyms permisos,licencias médicas
            if (preg_match('/@synonyms\s+(.+)/i', $line, $matches)) {
                $synonyms = array_map('trim', explode(',', $matches[1]));
                $metadata['synonyms'] = array_filter($synonyms, function($synonym) {
                    return !empty($synonym);
                });
            }
        }
        
        // Si no se encontraron metadatos en docblock, inferir
        if (empty($metadata['category'])) {
            $metadata['category'] = self::inferCategory($controllerName);
        }
        if (empty($metadata['tags'])) {
            $metadata['tags'] = self::inferTags($controllerName, $actionName);
        }
        if (empty($metadata['keywords'])) {
            $metadata['keywords'] = self::inferKeywords($actionName);
        }
        
        return $metadata;
    }

    /**
     * Inferir categoría desde el nombre del controlador si no está especificada
     * @param string $controllerName
     * @return string
     */
    private static function inferCategory($controllerName)
    {
        $categoryMapping = [
            'persona' => 'Pacientes',
            'personas' => 'Pacientes',
            'paciente' => 'Pacientes',
            'consulta' => 'Consultas',
            'consultas' => 'Consultas',
            'turno' => 'Turnos',
            'turnos' => 'Turnos',
            'licencia' => 'Licencias',
            'licencias' => 'Licencias',
            'efector' => 'Efectores',
            'efectores' => 'Efectores',
            'servicio' => 'Servicios',
            'servicios' => 'Servicios',
            'usuario' => 'Usuarios',
            'usuarios' => 'Usuarios',
            'reporte' => 'Reportes',
            'reportes' => 'Reportes',
            'guardia' => 'Guardias',
            'internacion' => 'Internaciones',
            'medicamento' => 'Medicamentos',
            'medicamentos' => 'Medicamentos',
            'rrhh' => 'Recursos Humanos',
            'agenda' => 'Agendas',
            'domicilio' => 'Domicilios',
            'laboratorio' => 'Laboratorios',
            'novedad' => 'Novedades',
            'programa' => 'Programas',
            'referencia' => 'Referencias',
            'receta' => 'Recetas',
        ];
        
        $controllerLower = strtolower($controllerName);
        
        foreach ($categoryMapping as $key => $category) {
            if (stripos($controllerLower, $key) !== false) {
                return $category;
            }
        }
        
        return 'General';
    }

    /**
     * Inferir tags desde el nombre del controlador y acción
     * @param string $controllerName
     * @param string $actionName
     * @return array
     */
    private static function inferTags($controllerName, $actionName)
    {
        $tags = [];
        
        // Tags desde controlador
        $controllerLower = strtolower($controllerName);
        if (stripos($controllerLower, 'persona') !== false) {
            $tags[] = 'persona';
            $tags[] = 'paciente';
        }
        if (stripos($controllerLower, 'consulta') !== false) {
            $tags[] = 'consulta';
            $tags[] = 'atencion';
        }
        if (stripos($controllerLower, 'turno') !== false) {
            $tags[] = 'turno';
            $tags[] = 'cita';
        }
        if (stripos($controllerLower, 'licencia') !== false) {
            $tags[] = 'licencia';
            $tags[] = 'permiso';
        }
        
        // Tags desde acción
        $actionLower = strtolower($actionName);
        if (stripos($actionLower, 'buscar') !== false || stripos($actionLower, 'search') !== false) {
            $tags[] = 'buscar';
            $tags[] = 'busqueda';
        }
        if (stripos($actionLower, 'listar') !== false || stripos($actionLower, 'index') !== false) {
            $tags[] = 'listar';
            $tags[] = 'lista';
        }
        if (stripos($actionLower, 'crear') !== false || stripos($actionLower, 'create') !== false) {
            $tags[] = 'crear';
            $tags[] = 'nuevo';
        }
        if (stripos($actionLower, 'editar') !== false || stripos($actionLower, 'update') !== false) {
            $tags[] = 'editar';
            $tags[] = 'modificar';
        }
        if (stripos($actionLower, 'ver') !== false || stripos($actionLower, 'view') !== false) {
            $tags[] = 'ver';
            $tags[] = 'detalle';
        }
        
        return array_unique($tags);
    }

    /**
     * Inferir keywords desde el nombre de la acción
     * @param string $actionName
     * @return array
     */
    private static function inferKeywords($actionName)
    {
        $keywords = [];
        $actionLower = strtolower($actionName);
        
        // Mapeo de acciones comunes a keywords
        $actionKeywords = [
            'index' => ['listar', 'ver todos', 'mostrar'],
            'view' => ['ver', 'mostrar', 'detalle'],
            'create' => ['crear', 'nuevo', 'agregar'],
            'update' => ['editar', 'modificar', 'actualizar'],
            'delete' => ['eliminar', 'borrar'],
            'search' => ['buscar', 'encontrar', 'filtrar'],
            'buscar' => ['buscar', 'encontrar', 'filtrar'],
            'listar' => ['listar', 'ver todos'],
        ];
        
        foreach ($actionKeywords as $action => $kw) {
            if (stripos($actionLower, $action) !== false) {
                $keywords = array_merge($keywords, $kw);
            }
        }
        
        // Agregar el nombre de la acción como keyword
        $keywords[] = $actionName;
        
        return array_unique($keywords);
    }

    /**
     * Extraer metadatos de un parámetro desde docblock
     * Soporta anotaciones:
     * @paramOption nombre_param tipo opciones
     * @paramFilter nombre_param filtro valor
     * @paramDepends nombre_param depende_de otro_param
     * @paramEndpoint nombre_param endpoint /ruta/api
     * 
     * @param string|false $docComment
     * @param string $paramName
     * @return array
     */
    private static function extractParameterMetadata($docComment, $paramName)
    {
        $metadata = [
            'option_type' => null,
            'option_config' => null,
            'filters' => [],
            'depends_on' => null,
            'endpoint' => null,
            'description' => '',
        ];
        
        if (!$docComment) {
            return $metadata;
        }
        
        $lines = explode("\n", $docComment);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // @paramOption id_efector select efectores|user_efectores
            if (preg_match('/@paramOption\s+' . preg_quote($paramName) . '\s+(\w+)\s+(.+)/i', $line, $matches)) {
                $metadata['option_type'] = trim($matches[1]); // select, autocomplete, date, etc.
                $optionConfig = trim($matches[2]);
                
                // Parsear configuración: "efectores|user_efectores" o "servicios|efector_servicios"
                $parts = explode('|', $optionConfig);
                $metadata['option_config'] = [
                    'source' => $parts[0], // efectores, servicios, personas, etc.
                    'filter' => $parts[1] ?? null, // user_efectores, efector_servicios, etc.
                ];
            }
            
            // @paramFilter id_servicio servicio_especialidad odontologia
            if (preg_match('/@paramFilter\s+' . preg_quote($paramName) . '\s+(\w+)\s+(.+)/i', $line, $matches)) {
                $filterType = trim($matches[1]);
                $filterValue = trim($matches[2]);
                $metadata['filters'][] = [
                    'type' => $filterType,
                    'value' => $filterValue,
                ];
            }
            
            // @paramDepends id_servicio id_efector
            if (preg_match('/@paramDepends\s+' . preg_quote($paramName) . '\s+(\w+)/i', $line, $matches)) {
                $metadata['depends_on'] = trim($matches[1]);
            }
            
            // @paramEndpoint id_persona /api/v1/personas/search
            if (preg_match('/@paramEndpoint\s+' . preg_quote($paramName) . '\s+(.+)/i', $line, $matches)) {
                $metadata['endpoint'] = trim($matches[1]);
            }
            
            // Extraer descripción del @param estándar
            if (preg_match('/@param\s+\S+\s+\$' . preg_quote($paramName) . '\s+(.+)/', $line, $matches)) {
                $metadata['description'] = trim($matches[1]);
            }
        }
        
        return $metadata;
    }

    /**
     * Invalidar cache de acciones
     */
    public static function invalidateCache()
    {
        $cache = Yii::$app->cache;
        if ($cache) {
            $cache->delete(self::CACHE_KEY_ACTIONS);
        }
    }
}

