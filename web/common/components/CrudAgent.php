<?php

namespace common\components;

use Yii;
use yii\db\ActiveRecord;
use yii\httpclient\Client;

/**
 * Agente CRUD que procesa consultas en lenguaje natural y genera operaciones CRUD
 * Respeta permisos del usuario y solo trabaja con entidades existentes
 */
class CrudAgent
{
    /**
     * Procesar consulta CRUD
     * @param string $userQuery Consulta del usuario en lenguaje natural
     * @param int|null $userId ID del usuario (null para usuario actual)
     * @return array Respuesta con explicación y acciones/formularios
     */
    public static function processCrudQuery($userQuery, $userId = null)
    {
        if (empty($userQuery)) {
            return [
                'success' => false,
                'error' => 'La consulta no puede estar vacía',
            ];
        }

        try {
            // Paso 1: Analizar la consulta con IA para detectar intención CRUD
            $intention = self::analyzeIntention($userQuery);
            
            if (!$intention['success']) {
                return $intention;
            }

            $entityName = $intention['entity'];
            $operation = $intention['operation'];
            $extractedParams = $intention['params'];

            // Paso 2: Buscar modelo correspondiente
            $model = ModelDiscoveryService::findModelByName($entityName);
            
            if (!$model) {
                // Modelo no encontrado - buscar alternativas
                return self::handleEntityNotFound($entityName, $userId);
            }

            // Paso 3: Validar permisos para la operación
            $hasPermission = self::validatePermission($model, $operation, $userId);
            
            if (!$hasPermission) {
                return [
                    'success' => false,
                    'error' => 'No tienes permisos para realizar esta operación',
                ];
            }

            // Paso 4: Procesar según la operación
            switch ($operation) {
                case 'create':
                    return self::handleCreate($model, $extractedParams);
                
                case 'read':
                case 'view':
                case 'list':
                    return self::handleRead($model, $extractedParams);
                
                case 'update':
                case 'edit':
                    return self::handleUpdate($model, $extractedParams);
                
                case 'delete':
                case 'remove':
                    return self::handleDelete($model, $extractedParams);
                
                default:
                    return [
                        'success' => false,
                        'error' => 'Operación no reconocida',
                    ];
            }

        } catch (\Exception $e) {
            Yii::error("Error procesando consulta CRUD: " . $e->getMessage(), 'crud-agent');
            return [
                'success' => false,
                'error' => 'Error al procesar la consulta. Por favor, intente nuevamente.',
            ];
        }
    }

    /**
     * Analizar intención de la consulta usando IA
     * @param string $userQuery
     * @return array
     */
    private static function analyzeIntention($userQuery)
    {
        // Obtener modelos disponibles para contexto
        $availableModels = ModelDiscoveryService::discoverAllModels();
        $modelNames = array_map(function($m) { return $m['name']; }, $availableModels);
        $modelsList = implode(', ', array_slice($modelNames, 0, 50)); // Limitar para el prompt

        // Construir prompt para IA
        $prompt = self::buildIntentionPrompt($userQuery, $modelsList);
        
        // Llamar a IA
        $iaResponse = self::callIA($prompt);
        
        if (empty($iaResponse)) {
            return [
                'success' => false,
                'error' => 'No se pudo analizar la consulta',
            ];
        }

        // Parsear respuesta
        $parsed = self::parseIntentionResponse($iaResponse);
        
        return $parsed;
    }

    /**
     * Construir prompt para análisis de intención
     * @param string $userQuery
     * @param string $modelsList
     * @return string
     */
    private static function buildIntentionPrompt($userQuery, $modelsList)
    {
        $prompt = <<<PROMPT
Eres un asistente para un sistema de gestión hospitalaria. Analiza la siguiente consulta del usuario y determina:
1. Qué entidad/modelo menciona (si existe en la lista)
2. Qué operación CRUD quiere realizar (create, read, update, delete)
3. Qué parámetros extrae de la consulta

Entidades disponibles en el sistema: {$modelsList}

Consulta del usuario: "{$userQuery}"

Responde ÚNICAMENTE con un JSON válido en este formato:
{
  "entity": "nombre_del_modelo_o_null",
  "operation": "create|read|update|delete",
  "params": {
    "campo1": "valor_extraido",
    "campo2": "valor_extraido"
  },
  "confidence": 0.0-1.0
}

IMPORTANTE:
- Si la entidad no está en la lista, usa "entity": null
- Los parámetros deben extraerse del texto (ej: "mañana" → fecha, "5 días" → duracion_dias)
- La operación debe ser una de: create, read, update, delete
PROMPT;

        return $prompt;
    }

    /**
     * Llamar a IA usando IAManager
     * @param string $prompt
     * @return string|null
     */
    private static function callIA($prompt)
    {
        try {
            $proveedorIA = IAManager::getProveedorIA();
            IAManager::asignarPromptAConfiguracion($proveedorIA, $prompt);
            
            $client = new \yii\httpclient\Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($proveedorIA['endpoint'])
                ->addHeaders($proveedorIA['headers'])
                ->setContent(json_encode($proveedorIA['payload']))
                ->send();

            if ($response->isOk) {
                return IAManager::procesarRespuestaProveedor($response, $proveedorIA['tipo']);
            }
        } catch (\Exception $e) {
            Yii::error("Error llamando a IA: " . $e->getMessage(), 'crud-agent');
        }

        return null;
    }

    /**
     * Parsear respuesta de intención de IA
     * @param string $iaResponse
     * @return array
     */
    private static function parseIntentionResponse($iaResponse)
    {
        // Extraer JSON de la respuesta
        $json = self::extractJSONFromResponse($iaResponse);
        
        if (!$json) {
            return [
                'success' => false,
                'error' => 'No se pudo interpretar la respuesta',
            ];
        }

        // Validar estructura
        if (!isset($json['operation']) || !in_array($json['operation'], ['create', 'read', 'update', 'delete', 'view', 'list', 'edit', 'remove'])) {
            return [
                'success' => false,
                'error' => 'Operación no válida',
            ];
        }

        // Normalizar operación
        $operation = $json['operation'];
        if (in_array($operation, ['view', 'list'])) {
            $operation = 'read';
        }
        if ($operation === 'edit') {
            $operation = 'update';
        }
        if ($operation === 'remove') {
            $operation = 'delete';
        }

        return [
            'success' => true,
            'entity' => $json['entity'] ?? null,
            'operation' => $operation,
            'params' => $json['params'] ?? [],
            'confidence' => $json['confidence'] ?? 0.5,
        ];
    }

    /**
     * Extraer JSON de respuesta de IA
     * @param string $response
     * @return array|null
     */
    private static function extractJSONFromResponse($response)
    {
        // Buscar JSON en la respuesta
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $response, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        // Intentar parsear toda la respuesta como JSON
        $json = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }

        return null;
    }

    /**
     * Manejar creación
     * @param array $model
     * @param array $params
     * @return array
     */
    private static function handleCreate($model, $params)
    {
        $modelClass = $model['class'];
        $form = DynamicFormGenerator::generateForm($modelClass, 'create', $params);

        if (!$form['success']) {
            return $form;
        }

        return [
            'success' => true,
            'intention' => 'create',
            'entity' => $model['name'],
            'explanation' => "Voy a ayudarte a crear un nuevo registro de {$model['name']}. Completa el siguiente formulario:",
            'form' => $form,
        ];
    }

    /**
     * Manejar lectura
     * @param array $model
     * @param array $params
     * @return array
     */
    private static function handleRead($model, $params)
    {
        $modelClass = $model['class'];
        $route = self::getRouteForOperation($model, 'index');

        return [
            'success' => true,
            'intention' => 'read',
            'entity' => $model['name'],
            'explanation' => "Te mostraré la lista de registros de {$model['name']}.",
            'action' => [
                'route' => $route,
                'type' => 'navigate',
            ],
        ];
    }

    /**
     * Manejar actualización
     * @param array $model
     * @param array $params
     * @return array
     */
    private static function handleUpdate($model, $params)
    {
        // Necesitamos el ID del registro a actualizar
        $id = self::extractId($params, $model);
        
        if (!$id) {
            return [
                'success' => false,
                'error' => 'Necesito el ID o identificador del registro que quieres actualizar',
            ];
        }

        $modelClass = $model['class'];
        $form = DynamicFormGenerator::generateForm($modelClass, 'update', $params);

        if (!$form['success']) {
            return $form;
        }

        return [
            'success' => true,
            'intention' => 'update',
            'entity' => $model['name'],
            'entity_id' => $id,
            'explanation' => "Voy a ayudarte a actualizar el registro {$id} de {$model['name']}.",
            'form' => $form,
        ];
    }

    /**
     * Manejar eliminación
     * @param array $model
     * @param array $params
     * @return array
     */
    private static function handleDelete($model, $params)
    {
        $id = self::extractId($params, $model);
        
        if (!$id) {
            return [
                'success' => false,
                'error' => 'Necesito el ID o identificador del registro que quieres eliminar',
            ];
        }

        $route = self::getRouteForOperation($model, 'delete');

        return [
            'success' => true,
            'intention' => 'delete',
            'entity' => $model['name'],
            'entity_id' => $id,
            'explanation' => "¿Estás seguro de que quieres eliminar el registro {$id} de {$model['name']}? Esta acción no se puede deshacer.",
            'action' => [
                'route' => $route,
                'type' => 'delete',
                'requires_confirmation' => true,
            ],
        ];
    }

    /**
     * Manejar caso cuando entidad no existe
     * @param string $entityName
     * @param int|null $userId
     * @return array
     */
    private static function handleEntityNotFound($entityName, $userId)
    {
        // Buscar acciones relacionadas
        $availableActions = ActionMappingService::getAvailableActionsForUser($userId);
        
        // Buscar acciones que puedan ser relevantes
        $suggestedActions = [];
        $entityLower = strtolower($entityName);
        
        foreach ($availableActions as $action) {
            $routeLower = strtolower($action['route']);
            $nameLower = strtolower($action['display_name']);
            
            if (stripos($routeLower, $entityLower) !== false || 
                stripos($nameLower, $entityLower) !== false) {
                $suggestedActions[] = $action;
            }
        }

        return [
            'success' => false,
            'error' => "No encontré una entidad llamada '{$entityName}' en el sistema.",
            'suggested_actions' => array_slice($suggestedActions, 0, 5),
            'message' => 'No se puede crear nuevas entidades. Aquí tienes algunas acciones relacionadas que podrían ayudarte:',
        ];
    }

    /**
     * Validar permisos para operación
     * @param array $model
     * @param string $operation
     * @param int|null $userId
     * @return bool
     */
    private static function validatePermission($model, $operation, $userId)
    {
        if (!$model['controller']) {
            return false;
        }

        $availableActions = ActionMappingService::getAvailableActionsForUser($userId);
        
        // Buscar acción correspondiente
        $actionName = $operation === 'read' ? 'index' : $operation;
        $route = self::getRouteForOperation($model, $actionName);

        foreach ($availableActions as $action) {
            if ($action['route'] === $route) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtener ruta para operación
     * @param array $model
     * @param string $operation
     * @return string
     */
    private static function getRouteForOperation($model, $operation)
    {
        $path = Yii::$app->params['path'] ?? '';
        $path = trim($path, '/');
        
        $controller = $model['controller'] ?? strtolower($model['name']);
        
        return '/' . $path . '/' . $controller . '/' . $operation;
    }

    /**
     * Extraer ID de parámetros
     * @param array $params
     * @param array $model
     * @return mixed|null
     */
    private static function extractId($params, $model)
    {
        // Buscar campos que puedan ser IDs
        $idFields = ['id', 'id_' . strtolower($model['name']), $model['table_name'] . '_id'];
        
        foreach ($idFields as $field) {
            if (isset($params[$field])) {
                return $params[$field];
            }
        }

        // Buscar cualquier parámetro numérico que pueda ser ID
        foreach ($params as $key => $value) {
            if (is_numeric($value) && (stripos($key, 'id') !== false || is_numeric($key))) {
                return $value;
            }
        }

        return null;
    }
}

