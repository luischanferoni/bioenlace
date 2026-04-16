<?php

namespace common\components\Actions;

use Yii;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\helpers\Url;
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
     * Orquestador / descubrimiento de acciones: solo API v1 (ver {@see discoverApiV1ControllerActions}).
     * No se indexan controladores web de frontend ni backend.
     */
    private static $controllerPaths = [];

    /**
     * Cache key para acciones descubiertas
     */
    public const CACHE_KEY_ACTIONS = 'discovered_actions_api_v1_only_v7';
    public const CACHE_KEY_FRONTEND_UI = 'discovered_frontend_ui_v3_native_path_infer';
    public const CACHE_DURATION = 3600; // 1 hora

    /**
     * Descubrir todas las acciones disponibles en el sistema
     * @param bool $useCache Usar cache si está disponible
     * @return array Array de acciones con metadatos
     */
    public static function discoverAllActions($useCache = true)
    {
        $useCache = ActionCatalogSettings::shouldUseCache($useCache);

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

        $actions = array_merge($actions, self::discoverApiV1ControllerActions());

        // Guardar en cache
        if ($cache) {
            $cache->set($cacheKey, $actions, self::CACHE_DURATION);
        }

        return $actions;
    }

    /**
     * Descubre **UIs HTML/JSON** implementadas en controladores web del frontend (`frontend/controllers`).
     *
     * Importante de terminología:
     * - Esto NO descubre endpoints de dominio de la API.
     * - Estos métodos representan pantallas (HTML) o definiciones de UI (arrays) que serán expuestas como
     *   descriptores bajo `/api/v1/<controller>/<action>` (templates JSON en `views/json/...`).
     *
     * Convención de tagging:
     * - Por defecto, toda `action*` del frontend se considera parte del catálogo.
     * - Para excluir manualmente: agregar `@no_intent_catalog` en el docblock del método.
     * - Opcionalmente se puede usar `@intent_catalog` (no requerido si se usa el default).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function discoverFrontendUiDefinitions(bool $useCache = true): array
    {
        $useCache = ActionCatalogSettings::shouldUseCache($useCache);
        $cache = Yii::$app->cache;
        $cacheKey = self::CACHE_KEY_FRONTEND_UI;
        if ($useCache && $cache) {
            $cached = $cache->get($cacheKey);
            if ($cached !== false && is_array($cached)) {
                return $cached;
            }
        }

        $alias = '@frontend/controllers';
        $realPath = Yii::getAlias($alias);
        if (!is_dir($realPath)) {
            return [];
        }

        $files = FileHelper::findFiles($realPath, [
            'only' => ['*Controller.php'],
            // Importante: incluir controladores en subcarpetas (frontend/controllers/**).
            // El catálogo debe considerar todas las actions públicas del frontend (salvo @no_intent_catalog).
            'recursive' => true,
        ]);

        $out = [];
        foreach ($files as $file) {
            $className = self::getClassNameFromFile($file);
            if (!$className) {
                continue;
            }
            try {
                $reflection = new ReflectionClass($className);
                if (
                    !$reflection->isSubclassOf('yii\web\Controller') &&
                    !$reflection->isSubclassOf('yii\rest\Controller')
                ) {
                    continue;
                }
                if ($reflection->isAbstract()) {
                    continue;
                }

                $controllerName = self::getControllerName($reflection->getShortName());
                $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
                foreach ($methods as $method) {
                    $methodName = $method->getName();
                    if (strpos($methodName, 'action') !== 0 || $methodName === 'actions') {
                        continue;
                    }
                    $methodName = str_replace('action', '', $methodName);
                    $actionName = Inflector::camel2id($methodName);

                    // Este route es el "destino UI" (no confundir con APIs de dominio).
                    $route = '/api/v1/' . $controllerName . '/' . $actionName;

                    $metadata = self::extractActionMetadata($method, $route, $controllerName, $actionName);
                    if (!$metadata) {
                        continue;
                    }

                    // Default include; allow explicit exclusion tag.
                    $doc = (string) ($method->getDocComment() ?: '');
                    if ($doc !== '' && preg_match('/@no_intent_catalog\b/i', $doc) === 1) {
                        continue;
                    }

                    $metadata['intent_catalog'] = true;
                    $metadata['intent_catalog_source'] = 'frontend-controller';
                    $out[] = $metadata;
                }
            } catch (\Throwable $e) {
                Yii::error("Error extrayendo UIs frontend de {$file}: " . $e->getMessage(), 'action-discovery');
            }
        }

        if ($cache) {
            $cache->set($cacheKey, $out, self::CACHE_DURATION);
        }
        return $out;
    }

    /**
     * Acciones del módulo API v1: path HTTP versionado ({@see urlManager} `api/<version>/...`).
     * RBAC/webvimark sigue usando `/api/&lt;controller&gt;/&lt;action&gt;` sin segmento de versión; véase {@see AllowedRoutesResolver::apiHttpPathToPermissionRoute}.
     */
    private static function discoverApiV1ControllerActions(): array
    {
        $alias = '@frontend/modules/api/v1/controllers';
        $realPath = Yii::getAlias($alias);
        if (!is_dir($realPath)) {
            return [];
        }

        $files = FileHelper::findFiles($realPath, [
            'only' => ['*Controller.php'],
            'recursive' => false,
        ]);

        $actions = [];
        foreach ($files as $file) {
            $actions = array_merge($actions, self::extractActionsFromApiV1ControllerFile($file));
        }

        return $actions;
    }

    /**
     * @param string $filePath
     * @return array
     */
    private static function extractActionsFromApiV1ControllerFile($filePath)
    {
        $actions = [];
        $className = self::getClassNameFromFile($filePath);

        if (!$className) {
            return $actions;
        }

        try {
            $reflection = new ReflectionClass($className);

            if (
                !$reflection->isSubclassOf('yii\web\Controller') &&
                !$reflection->isSubclassOf('yii\rest\Controller')
            ) {
                return $actions;
            }

            $shortName = $reflection->getShortName();
            if ($shortName === 'BaseController' || $reflection->isAbstract()) {
                return $actions;
            }

            $controllerName = self::getControllerName($shortName);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                $methodName = $method->getName();
                if (strpos($methodName, 'action') !== 0 || $methodName === 'actions') {
                    continue;
                }

                $methodName = str_replace('action', '', $methodName);
                $actionName = Inflector::camel2id($methodName);
                $route = self::generateApiGhostRoute($controllerName, $actionName);

                $metadata = self::extractActionMetadata($method, $route, $controllerName, $actionName);
                if ($metadata) {
                    $actions[] = $metadata;
                }
            }
        } catch (\Exception $e) {
            Yii::error("Error extrayendo acciones API v1 de {$filePath}: " . $e->getMessage(), 'action-discovery');
        }

        return $actions;
    }

    /**
     * Path HTTP bajo el prefijo `api/<version>/` del {@see \yii\web\UrlManager}.
     */
    private static function generateApiGhostRoute(string $controllerName, string $actionKebab): string
    {
        return '/api/v1/' . $controllerName . '/' . $actionKebab;
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
            if (
                !$reflection->isSubclassOf('yii\web\Controller') &&
                !$reflection->isSubclassOf('yii\rest\Controller')
            ) {
                return $actions;
            }

            // Obtener nombre del controlador desde el nombre de clase
            $controllerName = self::getControllerName($reflection->getShortName());

            // Determinar el módulo si existe
            $module = self::extractModuleFromNamespace($reflection->getNamespaceName(), $basePath);

            // Obtener métodos de acciones
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                if (strpos($method->getName(), 'action') === 0 && $method->getName() !== 'actions') {
                    // Extraer nombre de acción del método (remover prefijo "action")
                    $methodName = str_replace('action', '', $method->getName());

                    // Convertir de camelCase a kebab-case usando Inflector de Yii2
                    $actionName = Inflector::camel2id($methodName);

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
     * Generar ruta canónica usando Inflector de Yii2 para convertir nombres
     * @param string|null $module
     * @param string $controller Nombre del controlador (puede estar en camelCase)
     * @param string $action Nombre de la acción (ya convertido a kebab-case)
     * @return string
     */
    private static function generateRoute($module, $controller, $action)
    {
        $path = Yii::$app->params['path'] ?? '';

        // Limpiar path (remover barra inicial si existe)
        $path = trim($path, '/');

        // Convertir nombre del controlador de camelCase a kebab-case usando Inflector
        $controllerId = Inflector::camel2id($controller);

        if ($module) {
            // Convertir nombre del módulo también
            $moduleId = Inflector::camel2id($module);
            return '/' . ($path ? $path . '/' : '') . $moduleId . '/' . $controllerId . '/' . $action;
        }

        return '/' . ($path ? $path . '/' : '') . $controllerId . '/' . $action;
    }

    /**
     * URL para fetch del HTML nativo (sin layout) del shell SPA.
     *
     * Por defecto se arma con {@see Url::to()} y la ruta canónica `/<controller>/<action>`
     * (o `/<controller>/index`). Override opcional vía `@native_ui_path` en el docblock.
     *
     * @param array<string, mixed> $def Metadata descubierta (incl. `native_ui_path` opcional).
     */
    public static function resolveNativeWebFetchPath(array $def, string $controller, string $action): string
    {
        $override = isset($def['native_ui_path']) && is_string($def['native_ui_path'])
            ? trim($def['native_ui_path'])
            : '';
        if ($override !== '') {
            return $override;
        }

        $route = $action === 'index' ? $controller . '/index' : $controller . '/' . $action;
        if (Yii::$app->has('urlManager')) {
            return Url::to(['/' . $route]);
        }

        $path = '/' . rawurlencode($controller);
        if ($action !== 'index') {
            $path .= '/' . rawurlencode($action);
        }

        return $path;
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
            'action_name' => $customMetadata['action_name'],
            'description' => $description,
            'parameters' => $parameters,
            'method' => $method->getName(),
            // NUEVO: Agregar metadatos extraídos
            'entity' => $customMetadata['entity'],
            'tags' => $customMetadata['tags'],
            'keywords' => $customMetadata['keywords'],
            'synonyms' => $customMetadata['synonyms'],
            'intent_catalog' => $customMetadata['intent_catalog'],
            'native_ui_path' => $customMetadata['native_ui_path'],
            'spa_presentation' => $customMetadata['spa_presentation'],
            'native_assets_css' => $customMetadata['native_assets_css'],
            'native_assets_js' => $customMetadata['native_assets_js'],
            'mobile_screen_id' => $customMetadata['mobile_screen_id'],
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
            if (
                empty($line) ||
                $line === '/**' ||
                $line === '*/' ||
                (strpos($line, '*') === 0 && strlen(trim($line)) <= 2)
            ) {
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
                            // Convertir nombre de acción a kebab-case usando Inflector
                            $actionId = Inflector::camel2id($actionName);
                            $route = self::generateRoute($module, $controllerName, $actionId);
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
     * Soporta: @entity, @tags, @keywords, @synonyms
     *
     * @param string|false $docComment
     * @param string $controllerName
     * @param string $actionName
     * @return array
     */
    private static function extractCustomMetadata($docComment, $controllerName, $actionName)
    {
        $metadata = [
            'entity' => null,
            'tags' => [],
            'keywords' => [],
            'synonyms' => [],
            // Nombre "humano" explícito para UI / execute-action.
            // Se expone como action_name y tiene prioridad sobre display_name.
            'action_name' => null,
            // Si false, la acción NO debe aparecer en el catálogo de UIs.
            'intent_catalog' => true,
            // UI nativa para shell SPA: HTML sin layout (partial) + presentación.
            // Docblock:
            // - @native_ui_path /ruta/explicita  (opcional; por defecto Url::to + controller/action)
            // - @spa_presentation inline|fullscreen
            // - @native_assets_css /css/a.css,/css/b.css
            // - @native_assets_js /js/a.js,/js/b.js
            'native_ui_path' => null,
            'spa_presentation' => 'inline',
            'native_assets_css' => [],
            'native_assets_js' => [],
            // Identificador de pantalla nativa en móvil (Flutter).
            // - @mobile_screen_id agenda.crear
            'mobile_screen_id' => null,
        ];

        if (!$docComment) {
            // Si no hay docblock, inferir desde el nombre del controlador
            $metadata['entity'] = self::inferEntity($controllerName);
            $metadata['tags'] = self::inferTags($controllerName, $actionName);
            $metadata['keywords'] = self::inferKeywords($actionName);
            return $metadata;
        }

        $lines = explode("\n", $docComment);

        foreach ($lines as $line) {
            $line = trim($line);

            // @intent_catalog / @no_intent_catalog
            if (preg_match('/@no_intent_catalog\b/i', $line)) {
                $metadata['intent_catalog'] = false;
            }
            if (preg_match('/@intent_catalog\b/i', $line)) {
                $metadata['intent_catalog'] = true;
            }

            // @action_name "Reservar turno"
            // @display_name "Reservar turno" (alias, útil para migraciones)
            if (preg_match('/@(?:action_name|actionName|display_name|displayName)\s+(.+)/i', $line, $matches)) {
                $value = trim($matches[1]);
                $value = trim($value, "\"'"); // permitir comillas opcionales
                if ($value !== '') {
                    $metadata['action_name'] = $value;
                }
            }

            // @entity Turnos (también acepta @category para compatibilidad temporal durante migración)
            if (preg_match('/@(?:entity|category)\s+(.+)/i', $line, $matches)) {
                $metadata['entity'] = trim($matches[1]);
            }

            // @tags licencia,permiso,vacaciones
            if (preg_match('/@tags\s+(.+)/i', $line, $matches)) {
                $tags = array_map('trim', explode(',', $matches[1]));
                $metadata['tags'] = array_values(array_filter($tags, static function ($tag) {
                    return !empty($tag);
                }));
            }

            // @keywords buscar,listar,filtrar
            if (preg_match('/@keywords\s+(.+)/i', $line, $matches)) {
                $keywords = array_map('trim', explode(',', $matches[1]));
                $metadata['keywords'] = array_values(array_filter($keywords, static function ($keyword) {
                    return !empty($keyword);
                }));
            }

            // @synonyms permisos,licencias médicas
            if (preg_match('/@synonyms\s+(.+)/i', $line, $matches)) {
                $synonyms = array_map('trim', explode(',', $matches[1]));
                $metadata['synonyms'] = array_values(array_filter($synonyms, static function ($synonym) {
                    return !empty($synonym);
                }));
            }

            // @native_ui_path /ruta/explicita (opcional; default Url::to canonical)
            if (preg_match('/@native_ui_path\s+(.+)/i', $line, $matches)) {
                $value = trim($matches[1]);
                $value = trim($value, "\"'");
                if ($value !== '') {
                    $metadata['native_ui_path'] = $value;
                }
            }

            // @spa_presentation inline|fullscreen
            if (preg_match('/@spa_presentation\s+(.+)/i', $line, $matches)) {
                $value = strtolower(trim(trim($matches[1]), "\"'"));
                if ($value === 'inline' || $value === 'fullscreen') {
                    $metadata['spa_presentation'] = $value;
                }
            }

            // @native_assets_css /css/a.css,/css/b.css
            if (preg_match('/@native_assets_css\s+(.+)/i', $line, $matches)) {
                $arr = array_map('trim', explode(',', $matches[1]));
                $metadata['native_assets_css'] = array_values(array_filter($arr, static function ($v) {
                    return is_string($v) && trim($v) !== '';
                }));
            }

            // @native_assets_js /js/a.js,/js/b.js
            if (preg_match('/@native_assets_js\s+(.+)/i', $line, $matches)) {
                $arr = array_map('trim', explode(',', $matches[1]));
                $metadata['native_assets_js'] = array_values(array_filter($arr, static function ($v) {
                    return is_string($v) && trim($v) !== '';
                }));
            }

            // @mobile_screen_id agenda.crear
            if (preg_match('/@mobile_screen_id\s+(.+)/i', $line, $matches)) {
                $value = trim($matches[1]);
                $value = trim($value, "\"'");
                if ($value !== '') {
                    $metadata['mobile_screen_id'] = strtolower($value);
                }
            }
        }

        // Si no se encontraron metadatos en docblock, inferir
        if (empty($metadata['entity'])) {
            $metadata['entity'] = self::inferEntity($controllerName);
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
     * Inferir entity desde el nombre del controlador si no está especificada
     * @param string $controllerName
     * @return string
     */
    private static function inferEntity($controllerName)
    {
        $entityMapping = [
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

        foreach ($entityMapping as $key => $entity) {
            if (stripos($controllerLower, $key) !== false) {
                return $entity;
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
        if (stripos($controllerLower, 'agenda') !== false) {
            $tags[] = 'agenda';
            $tags[] = 'rrhh';
            $tags[] = 'laboral';
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

        return array_values(array_unique($tags));
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

        return array_values(array_unique($keywords));
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

