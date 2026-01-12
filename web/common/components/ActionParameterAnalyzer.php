<?php

namespace common\components;

use Yii;
use ReflectionMethod;
use ReflectionParameter;

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
     */
    private static function getActionParameters($action)
    {
        $params = [];
        
        try {
            $controllerClass = 'frontend\\controllers\\' . ucfirst($action['controller']) . 'Controller';
            
            if (!class_exists($controllerClass)) {
                return $params;
            }
            
            $methodName = 'action' . ucfirst($action['action']);
            if (!method_exists($controllerClass, $methodName)) {
                return $params;
            }
            
            $reflection = new ReflectionMethod($controllerClass, $methodName);
            $docComment = $reflection->getDocComment();
            
            foreach ($reflection->getParameters() as $param) {
                $paramInfo = [
                    'name' => $param->getName(),
                    'required' => !$param->isOptional(),
                    'type' => self::inferParameterType($param),
                ];
                
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
        } catch (\Exception $e) {
            Yii::error("Error obteniendo parámetros de acción: " . $e->getMessage(), 'action-parameter-analyzer');
        }
        
        return $params;
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
    
    /**
     * Mapear datos extraídos a parámetros
     */
    private static function mapExtractedDataToParameters($extractedData, $requiredParams)
    {
        $mapped = [];
        
        foreach ($requiredParams as $param) {
            $paramName = $param['name'];
            $value = null;
            
            // Mapeo inteligente basado en nombre del parámetro
            if (stripos($paramName, 'id') !== false) {
                // Buscar IDs en extracted_data
                if (isset($extractedData['dni'])) {
                    $value = $extractedData['dni'];
                } elseif (isset($extractedData['raw']['identifiers'])) {
                    $value = $extractedData['raw']['identifiers'][0] ?? null;
                }
            } elseif (stripos($paramName, 'fecha') !== false || stripos($paramName, 'date') !== false) {
                if (isset($extractedData['fecha'])) {
                    $value = $extractedData['fecha'];
                } elseif (isset($extractedData['raw']['dates'])) {
                    $value = $extractedData['raw']['dates'][0] ?? null;
                }
            } elseif (stripos($paramName, 'nombre') !== false || stripos($paramName, 'name') !== false) {
                if (isset($extractedData['nombre'])) {
                    $value = $extractedData['nombre'];
                } elseif (isset($extractedData['raw']['names'])) {
                    $value = implode(' ', $extractedData['raw']['names']);
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
        $mapping = [
            'efectores' => [
                'endpoint' => '/frontend/efectores/search',
                'input_type' => 'autocomplete',
            ],
            'servicios' => [
                'endpoint' => '/frontend/servicios/search',
                'input_type' => 'autocomplete',
            ],
            'personas' => [
                'endpoint' => '/frontend/personas/autocomplete',
                'input_type' => 'autocomplete',
            ],
            'rrhh' => [
                'endpoint' => '/frontend/rrhh/rrhh-autocomplete',
                'input_type' => 'autocomplete',
            ],
            'especialidades' => [
                'endpoint' => '/frontend/especialidades/search',
                'input_type' => 'autocomplete',
            ],
        ];
        
        if (!isset($mapping[$source])) {
            return ['type' => 'endpoint', 'endpoint' => null];
        }
        
        $config = $mapping[$source];
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
            'endpoint' => $config['endpoint'],
            'input_type' => $config['input_type'],
            'params' => $params,
        ];
    }
    
    /**
     * Inferir opciones desde nombre del parámetro (fallback)
     */
    private static function inferOptionsFromName($paramName, $userId = null)
    {
        $paramNameLower = strtolower($paramName);
        
        if (stripos($paramNameLower, 'efector') !== false || stripos($paramNameLower, 'id_efector') !== false) {
            return [
                'type' => 'autocomplete',
                'endpoint' => '/frontend/efectores/search',
            ];
        } elseif (stripos($paramNameLower, 'servicio') !== false || stripos($paramNameLower, 'id_servicio') !== false) {
            return [
                'type' => 'autocomplete',
                'endpoint' => '/frontend/servicios/search',
            ];
        } elseif (stripos($paramNameLower, 'persona') !== false || stripos($paramNameLower, 'id_persona') !== false) {
            return [
                'type' => 'autocomplete',
                'endpoint' => '/frontend/personas/autocomplete',
            ];
        } elseif (stripos($paramNameLower, 'rrhh') !== false || stripos($paramNameLower, 'id_rrhh') !== false) {
            return [
                'type' => 'autocomplete',
                'endpoint' => '/frontend/rrhh/rrhh-autocomplete',
            ];
        } elseif (stripos($paramNameLower, 'fecha') !== false || stripos($paramNameLower, 'date') !== false) {
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
