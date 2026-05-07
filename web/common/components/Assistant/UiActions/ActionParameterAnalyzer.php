<?php

namespace common\components\Assistant\UiActions;

use Yii;
use ReflectionMethod;
use ReflectionParameter;
use yii\helpers\Inflector;

/**
 * Servicio para analizar parámetros de acciones y generar formularios dinámicos
 * Detecta parámetros faltantes y genera opciones usando metadatos de docblocks
 */
class ActionParameterAnalyzer
{
    /**
     * Analizar acción y determinar parámetros faltantes
     * @param array $action Acción detectada
     * @param array $extractedData Datos extraídos por la IA
     * @param int|null $userId
     * @return array Estructura para formulario dinámico
     */
    public static function analyzeActionParameters($action, $extractedData, $userId = null)
    {
        // Obtener parámetros requeridos del método
        $requiredParams = self::getActionParameters($action);
        
        // Mapear datos extraídos a parámetros
        $mappedParams = self::mapExtractedDataToParameters($extractedData, $requiredParams);
        
        // Identificar parámetros faltantes
        $missingParams = self::findMissingParameters($requiredParams, $mappedParams);
        
        // Generar opciones para parámetros faltantes usando metadatos
        $options = self::generateOptionsForMissingParams($missingParams, $action, $extractedData, $userId);
        
        return [
            'action_id' => $action['action_id'] ?? null,
            'action_name' => $action['display_name'] ?? '',
            'route' => $action['route'] ?? '',
            'parameters' => [
                'provided' => $mappedParams,
                'missing' => $missingParams,
                'all_required' => $requiredParams,
            ],
            'options' => $options,
            'ready_to_execute' => empty($missingParams),
            'form_config' => self::generateFormConfig($requiredParams, $mappedParams, $options),
        ];
    }
    
    /**
     * Obtener parámetros de una acción usando reflexión
     * También detecta parámetros documentados en el docblock con @paramOption
     */
    private static function getActionParameters($action)
    {
        $params = [];
        
        try {
            $controllerClass = 'frontend\\controllers\\' . ucfirst($action['controller']) . 'Controller';
            
            if (!class_exists($controllerClass)) {
                return $params;
            }
            
            // Convertir nombre de acción de kebab-case a camelCase
            $actionName = $action['action'];
            $actionCamelCase = Inflector::id2camel($actionName, '-');
            $methodName = 'action' . $actionCamelCase;
            
            if (!method_exists($controllerClass, $methodName)) {
                Yii::warning("Método no encontrado: {$methodName} en {$controllerClass}", 'action-parameter-analyzer');
                return $params;
            }
            
            $reflection = new ReflectionMethod($controllerClass, $methodName);
            $docComment = $reflection->getDocComment();
            
            // Obtener parámetros de la firma del método
            $methodParamNames = [];
            foreach ($reflection->getParameters() as $param) {
                $paramInfo = [
                    'name' => $param->getName(),
                    'required' => !$param->isOptional(),
                    'type' => self::inferParameterType($param),
                ];
                
                $methodParamNames[] = $param->getName();
                
                if ($param->isOptional()) {
                    try {
                        $paramInfo['default'] = $param->getDefaultValue();
                    } catch (\ReflectionException $e) {
                        // No se puede obtener el valor por defecto
                    }
                }
                
                // Extraer metadatos desde docblock
                $paramMetadata = self::extractParameterMetadata($docComment, $param->getName());
                $paramInfo = array_merge($paramInfo, $paramMetadata);
                
                $params[] = $paramInfo;
            }
            
            // NUEVO: Detectar parámetros documentados en docblock con @paramOption
            // que no están en la firma del método (parámetros enviados por request)
            if ($docComment) {
                $docblockParams = self::extractParametersFromDocblock($docComment);
                foreach ($docblockParams as $docblockParam) {
                    // Solo agregar si no existe ya en los parámetros del método
                    if (!in_array($docblockParam['name'], $methodParamNames)) {
                        // Verificar si ya existe en params (por si hay duplicado)
                        $exists = false;
                        foreach ($params as $existingParam) {
                            if ($existingParam['name'] === $docblockParam['name']) {
                                $exists = true;
                                break;
                            }
                        }
                        
                        if (!$exists) {
                            // Los parámetros documentados con @paramOption se consideran requeridos
                            $docblockParam['required'] = true;
                            $params[] = $docblockParam;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Yii::error("Error obteniendo parámetros de acción: " . $e->getMessage(), 'action-parameter-analyzer');
        }
        
        return $params;
    }
    
    /**
     * Extraer parámetros documentados en el docblock con @paramOption
     * 
     * @param string $docComment
     * @return array
     */
    private static function extractParametersFromDocblock($docComment)
    {
        $params = [];
        
        if (!$docComment) {
            return $params;
        }
        
        $lines = explode("\n", $docComment);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // @paramOption <param> <tipo> <source>[|<filter>]
            if (preg_match('/@paramOption\s+(\w+)\s+(\w+)\s+(.+)/i', $line, $matches)) {
                $paramName = trim($matches[1]);
                $optionType = trim($matches[2]);
                $optionConfig = trim($matches[3]);
                
                // Parsear configuración: "<source>|<filter>"
                $parts = explode('|', $optionConfig);
                
                $paramInfo = [
                    'name' => $paramName,
                    'required' => true, // Los parámetros con @paramOption se consideran requeridos
                    'type' => self::inferParameterTypeFromName($paramName),
                    'source' => 'docblock',
                    'option_type' => $optionType,
                    'option_config' => [
                        'source' => $parts[0],
                        'filter' => $parts[1] ?? null,
                    ],
                ];
                
                // Extraer otros metadatos si existen
                $paramMetadata = self::extractParameterMetadata($docComment, $paramName);
                $paramInfo = array_merge($paramInfo, $paramMetadata);
                
                $params[] = $paramInfo;
            }
        }
        
        return $params;
    }
    
    /**
     * Inferir tipo de parámetro desde su nombre
     */
    private static function inferParameterTypeFromName($paramName)
    {
        $name = strtolower($paramName);
        
        if (strpos($name, 'id') === 0 || strpos($name, 'id_') === 0) {
            return 'integer';
        }
        if (stripos($name, 'fecha') !== false || stripos($name, 'date') !== false) {
            return 'date';
        }
        if (stripos($name, 'hora') !== false || stripos($name, 'time') !== false) {
            return 'time';
        }
        
        return 'string';
    }
    
    /**
     * Extraer metadatos de un parámetro desde docblock
     * Soporta anotaciones:
     * @paramOption nombre_param tipo opciones
     * @paramFilter nombre_param filtro valor
     * @paramDepends nombre_param depende_de otro_param
     * @paramEndpoint nombre_param endpoint /ruta/api
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
            
            // @paramOption <param> select <endpoint_absoluto_o_path>[|<filtro>]
            if (preg_match('/@paramOption\s+' . preg_quote($paramName) . '\s+(\w+)\s+(.+)/i', $line, $matches)) {
                $metadata['option_type'] = trim($matches[1]); // select, autocomplete, date, etc.
                $optionConfig = trim($matches[2]);
                
                // Parsear configuración: "<source>|<filter>"
                $parts = explode('|', $optionConfig);
                $metadata['option_config'] = [
                    'source' => $parts[0],
                    'filter' => $parts[1] ?? null,
                ];
            }
            
            // @paramFilter <param> <tipo_filtro> <valor>
            if (preg_match('/@paramFilter\s+' . preg_quote($paramName) . '\s+(\w+)\s+(.+)/i', $line, $matches)) {
                $filterType = trim($matches[1]);
                $filterValue = trim($matches[2]);
                $metadata['filters'][] = [
                    'type' => $filterType,
                    'value' => $filterValue,
                ];
            }
            
            // @paramDepends <param> <depende_de>
            if (preg_match('/@paramDepends\s+' . preg_quote($paramName) . '\s+(\w+)/i', $line, $matches)) {
                $metadata['depends_on'] = trim($matches[1]);
            }
            
            // @paramEndpoint <param> <endpoint>
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
     * Inferir tipo de parámetro
     */
    private static function inferParameterType($param)
    {
        // Intentar obtener tipo desde type hint
        if ($param->hasType()) {
            $type = $param->getType();
            if (!$type->isBuiltin()) {
                return $type->getName();
            }
            return $type->getName();
        }
        
        // Inferir desde nombre
        $name = strtolower($param->getName());
        
        if (strpos($name, 'id') === 0) {
            return 'integer';
        }
        if (stripos($name, 'fecha') !== false || stripos($name, 'date') !== false) {
            return 'date';
        }
        if (stripos($name, 'email') !== false) {
            return 'email';
        }
        
        return 'string';
    }
    
    /** Mapear datos extraídos a parámetros (agnóstico de dominio). */
    private static function mapExtractedDataToParameters($extractedData, $requiredParams)
    {
        $mapped = [];
        
        foreach ($requiredParams as $param) {
            $paramName = $param['name'];
            $value = null;

            // 1) Match directo por nombre de parámetro.
            if (is_array($extractedData) && array_key_exists($paramName, $extractedData)) {
                $value = $extractedData[$paramName];
            }

            // 2) Heurística genérica: fechas.
            if ($value === null && (stripos($paramName, 'fecha') !== false || stripos($paramName, 'date') !== false)) {
                if (is_array($extractedData) && isset($extractedData['raw']['dates']) && is_array($extractedData['raw']['dates'])) {
                    $value = $extractedData['raw']['dates'][0] ?? null;
                }
            }
            
            if ($value !== null) {
                $mapped[$paramName] = [
                    'value' => $value,
                    'source' => 'extracted',
                ];
            }
        }
        
        return $mapped;
    }
    
    /**
     * Encontrar parámetros faltantes
     */
    private static function findMissingParameters($requiredParams, $mappedParams)
    {
        $missing = [];
        
        foreach ($requiredParams as $param) {
            if ($param['required'] && !isset($mappedParams[$param['name']])) {
                $missing[] = $param;
            }
        }
        
        return $missing;
    }
    
    /**
     * Generar opciones para parámetros faltantes usando metadatos
     */
    private static function generateOptionsForMissingParams($missingParams, $action, $extractedData, $userId = null)
    {
        $options = [];
        
        foreach ($missingParams as $param) {
            $paramName = $param['name'];
            $metadata = $param['option_config'] ?? null;
            
            // Si hay metadatos de opciones, usarlos
            if ($metadata && isset($metadata['option_type'])) {
                $options[$paramName] = self::generateOptionsFromMetadata($param, $metadata, $extractedData, $userId);
            } else {
                // Fallback: inferir desde nombre
                $options[$paramName] = self::inferOptionsFromName($paramName, $userId);
            }
        }
        
        return $options;
    }
    
    /**
     * Generar opciones desde metadatos
     */
    private static function generateOptionsFromMetadata($param, $metadata, $extractedData, $userId = null)
    {
        $optionType = $metadata['option_type'];
        $source = $metadata['source'] ?? null;
        $filter = $metadata['filter'] ?? null;
        $dependsOn = $param['depends_on'] ?? null;
        $endpoint = $param['endpoint'] ?? null;
        $filters = $param['filters'] ?? [];
        
        // Si depende de otro parámetro, verificar si está disponible
        if ($dependsOn) {
            $dependsValue = $extractedData[$dependsOn] ?? null;
            if (!$dependsValue) {
                return [
                    'type' => $optionType,
                    'depends_on' => $dependsOn,
                    'message' => "Primero debe seleccionar {$dependsOn}",
                ];
            }
        }
        
        // Resolver endpoint según la fuente
        $resolved = self::resolveSourceEndpoint($source, $filter, $filters, $dependsOn, $extractedData);
        
        if ($endpoint) {
            // Usar endpoint explícito
            $options = [
                'type' => $optionType === 'select' ? 'autocomplete' : $optionType,
                'endpoint' => $endpoint,
            ];
        } elseif ($resolved['type'] === 'endpoint') {
            $options = [
                'type' => $resolved['input_type'] ?? $optionType,
                'endpoint' => $resolved['endpoint'],
            ];
            
            // Agregar parámetros de filtro si existen
            if (!empty($resolved['params'])) {
                $options['params'] = $resolved['params'];
            }
        } else {
            // Fallback a tipo básico
            $options = [
                'type' => $optionType,
            ];
        }
        
        // Agregar información de dependencia
        if ($dependsOn) {
            $options['depends_on'] = $dependsOn;
        }
        
        return $options;
    }
    
    /**
     * Resolver endpoint o método según la fuente
     */
    private static function resolveSourceEndpoint($source, $filter, $filters, $dependsOn, $extractedData)
    {
        // Agnóstico de dominio: `source` debe ser un endpoint explícito o la regla no se puede resolver aquí.
        $endpoint = is_string($source) ? trim($source) : '';
        if ($endpoint === '' || $endpoint[0] !== '/') {
            return ['type' => 'endpoint', 'endpoint' => null];
        }
        $params = [];
        
        // Si depende de otro parámetro, agregarlo a los params
        if ($dependsOn && isset($extractedData[$dependsOn])) {
            $params[$dependsOn] = $extractedData[$dependsOn];
        }
        
        // Agregar filtros como parámetros
        foreach ($filters as $filterConfig) {
            $params[$filterConfig['type']] = $filterConfig['value'];
        }
        
        return [
            'type' => 'endpoint',
            'endpoint' => $endpoint,
            'input_type' => 'autocomplete',
            'params' => $params,
        ];
    }
    
    /**
     * Inferir opciones desde nombre del parámetro (fallback)
     */
    private static function inferOptionsFromName($paramName, $userId = null)
    {
        $paramNameLower = strtolower($paramName);
        
        if (stripos($paramNameLower, 'fecha') !== false || stripos($paramNameLower, 'date') !== false) {
            return [
                'type' => 'date',
                'format' => 'YYYY-MM-DD',
            ];
        } elseif (stripos($paramNameLower, 'id') === 0) {
            return [
                'type' => 'number',
                'min' => 1,
            ];
        }
        
        return [
            'type' => 'text',
        ];
    }
    
    /**
     * Generar configuración de formulario
     */
    private static function generateFormConfig($requiredParams, $mappedParams, $options)
    {
        $fields = [];
        
        foreach ($requiredParams as $param) {
            $field = [
                'name' => $param['name'],
                'label' => ucwords(str_replace('_', ' ', $param['name'])),
                'type' => $options[$param['name']]['type'] ?? 'text',
                'required' => $param['required'],
                'value' => $mappedParams[$param['name']]['value'] ?? null,
                'description' => $param['description'] ?? '',
            ];
            
            // Agregar opciones específicas
            if (isset($options[$param['name']])) {
                $option = $options[$param['name']];
                
                if (isset($option['endpoint'])) {
                    $field['endpoint'] = $option['endpoint'];
                }
                if (isset($option['params'])) {
                    $field['params'] = $option['params'];
                }
                if (isset($option['format'])) {
                    $field['format'] = $option['format'];
                }
                if (isset($option['depends_on'])) {
                    $field['depends_on'] = $option['depends_on'];
                }
                if (isset($option['message'])) {
                    $field['message'] = $option['message'];
                }
            }
            
            $fields[] = $field;
        }
        
        return [
            'fields' => $fields,
            'submit_label' => 'Ejecutar',
        ];
    }
}
