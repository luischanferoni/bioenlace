<?php

namespace common\components;

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
            
            // Convertir nombre de acción de kebab-case (crear-mi-turno) a camelCase (crearMiTurno)
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
            // que no están en la firma del método (como servicio_actual que viene por POST)
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
            
            // @paramOption servicio_actual select servicios|efector_servicios
            if (preg_match('/@paramOption\s+(\w+)\s+(\w+)\s+(.+)/i', $line, $matches)) {
                $paramName = trim($matches[1]);
                $optionType = trim($matches[2]);
                $optionConfig = trim($matches[3]);
                
                // Parsear configuración: "servicios|efector_servicios"
                $parts = explode('|', $optionConfig);
                
                $paramInfo = [
                    'name' => $paramName,
                    'required' => true, // Los parámetros con @paramOption se consideran requeridos
                    'type' => self::inferParameterTypeFromName($paramName),
                    'source' => 'docblock',
                    'option_type' => $optionType,
                    'option_config' => [
                        'source' => $parts[0], // servicios, efectores, personas, etc.
                        'filter' => $parts[1] ?? null, // efector_servicios, user_efectores, etc.
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
        if (stripos($name, 'servicio') !== false) {
            return 'integer'; // id_servicio
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
            } elseif (stripos($paramName, 'servicio') !== false) {
                // Mapeo específico para servicios
                // Puede venir como "servicio", "servicio_actual", "id_servicio", etc.
                $value = self::mapServicioFromExtractedData($extractedData, $paramName);
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
     * Mapear servicio desde datos extraídos
     * Busca nombres de servicios (como "odontologo") y los convierte a id_servicio
     * 
     * @param array $extractedData
     * @param string $paramName Nombre del parámetro (servicio_actual, id_servicio, etc.)
     * @return int|null ID del servicio encontrado
     */
    private static function mapServicioFromExtractedData($extractedData, $paramName)
    {
        $servicioValue = null;
        
        // 1. Buscar directamente en extractedData
        if (isset($extractedData['servicio'])) {
            $servicioValue = $extractedData['servicio'];
        } elseif (isset($extractedData['id_servicio'])) {
            // Si ya viene como ID, devolverlo directamente
            return is_numeric($extractedData['id_servicio']) ? (int)$extractedData['id_servicio'] : null;
        } elseif (isset($extractedData[$paramName])) {
            $servicioValue = $extractedData[$paramName];
        }
        
        // 2. Buscar en raw data
        if ($servicioValue === null && isset($extractedData['raw'])) {
            if (isset($extractedData['raw']['servicio'])) {
                $servicioValue = $extractedData['raw']['servicio'];
            } elseif (isset($extractedData['raw']['names'])) {
                // Buscar en nombres extraídos (puede venir como "odontologo" en names)
                foreach ($extractedData['raw']['names'] as $name) {
                    $servicioId = self::findServicioByName($name);
                    if ($servicioId !== null) {
                        return $servicioId;
                    }
                }
            }
        }
        
        // 3. Si el valor es un número, asumir que es un ID
        if ($servicioValue !== null && is_numeric($servicioValue)) {
            return (int)$servicioValue;
        }
        
        // 4. Si el valor es texto, buscar el servicio por nombre
        if ($servicioValue !== null && is_string($servicioValue)) {
            return self::findServicioByName($servicioValue);
        }
        
        return null;
    }
    
    /**
     * Buscar servicio por nombre (soporta búsqueda parcial y sinónimos)
     * 
     * @param string $nombre Nombre del servicio (ej: "odontologo", "odontología", "ODONTOLOGIA")
     * @return int|null ID del servicio encontrado
     */
    private static function findServicioByName($nombre)
    {
        if (empty($nombre)) {
            return null;
        }
        
        // Normalizar nombre: convertir a mayúsculas y limpiar
        $nombreNormalizado = strtoupper(trim($nombre));
        
        // Mapeo de sinónimos comunes
        $sinonimos = [
            'odontologo' => 'ODONTOLOGIA',
            'odontología' => 'ODONTOLOGIA',
            'odontologia' => 'ODONTOLOGIA',
            'dental' => 'ODONTOLOGIA',
            'dentista' => 'ODONTOLOGIA',
            'pediatra' => 'PEDIATRIA',
            'pediatría' => 'PEDIATRIA',
            'ginecologo' => 'GINECOLOGIA',
            'ginecología' => 'GINECOLOGIA',
            'ginecologia' => 'GINECOLOGIA',
            'medico' => 'MED GENERAL',
            'médico' => 'MED GENERAL',
            'medico general' => 'MED GENERAL',
            'medico familiar' => 'MED FAMILIAR',
            'medico clinica' => 'MED CLINICA',
            'médico clínica' => 'MED CLINICA',
            'clinica' => 'MED CLINICA',
            'clínica' => 'MED CLINICA',
            'psicologo' => 'PSICOLOGIA',
            'psicología' => 'PSICOLOGIA',
            'psicologia' => 'PSICOLOGIA',
            'kinesiologo' => 'KINESIOLOGIA',
            'kinesiología' => 'KINESIOLOGIA',
            'kinesiologia' => 'KINESIOLOGIA',
            'kinesio' => 'KINESIOLOGIA',
        ];
        
        // Verificar si hay un sinónimo directo
        $nombreLower = strtolower($nombreNormalizado);
        if (isset($sinonimos[$nombreLower])) {
            $nombreNormalizado = $sinonimos[$nombreLower];
        }
        
        // Buscar en la base de datos
        try {
            // Primero intentar búsqueda exacta
            $servicio = \common\models\Servicio::find()
                ->where(['nombre' => $nombreNormalizado])
                ->one();
            
            if ($servicio) {
                return (int)$servicio->id_servicio;
            }
            
            // Si no se encuentra exacto, intentar búsqueda con LIKE
            $servicio = \common\models\Servicio::find()
                ->where(['LIKE', 'nombre', $nombreNormalizado])
                ->one();
            
            if ($servicio) {
                return (int)$servicio->id_servicio;
            }
            
            // Último intento: buscar sinónimos en la base de datos
            foreach ($sinonimos as $sinonimo => $nombreServicio) {
                if (stripos($nombreNormalizado, $sinonimo) !== false || stripos($sinonimo, $nombreNormalizado) !== false) {
                    $servicio = \common\models\Servicio::find()
                        ->where(['nombre' => $nombreServicio])
                        ->one();
                    
                    if ($servicio) {
                        return (int)$servicio->id_servicio;
                    }
                }
            }
        } catch (\Exception $e) {
            Yii::error("Error buscando servicio por nombre '{$nombre}': " . $e->getMessage(), 'action-parameter-analyzer');
        }
        
        return null;
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
                'endpoint' => '/efectores/search',
                'input_type' => 'autocomplete',
            ],
            'servicios' => [
                'endpoint' => '/servicios/search',
                'input_type' => 'autocomplete',
            ],
            'personas' => [
                'endpoint' => '/personas/autocomplete',
                'input_type' => 'autocomplete',
            ],
            'rrhh' => [
                'endpoint' => '/rrhh/rrhh-autocomplete',
                'input_type' => 'autocomplete',
            ],
            'especialidades' => [
                'endpoint' => '/especialidades/search',
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
                'endpoint' => '/efectores/search',
            ];
        } elseif (stripos($paramNameLower, 'servicio') !== false || stripos($paramNameLower, 'id_servicio') !== false) {
            return [
                'type' => 'autocomplete',
                'endpoint' => '/servicios/search',
            ];
        } elseif (stripos($paramNameLower, 'persona') !== false || stripos($paramNameLower, 'id_persona') !== false) {
            return [
                'type' => 'autocomplete',
                'endpoint' => '/personas/autocomplete',
            ];
        } elseif (stripos($paramNameLower, 'rrhh') !== false || stripos($paramNameLower, 'id_rrhh') !== false) {
            return [
                'type' => 'autocomplete',
                'endpoint' => '/rrhh/rrhh-autocomplete',
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
