<?php

namespace common\components;

use Yii;

/**
 * Agente CRUD completamente genérico y dinámico
 * Puede procesar CUALQUIER consulta sin patrones predefinidos
 * Usa metadatos de docblocks para mejorar la precisión
 */
class UniversalQueryAgent
{
    /**
     * Procesar cualquier consulta del usuario
     * @param string $userQuery Cualquier consulta en lenguaje natural
     * @param int|null $userId ID del usuario
     * @return array Respuesta con acciones o datos
     */
    public static function processQuery($userQuery, $userId = null)
    {
        if (empty($userQuery)) {
            return [
                'success' => false,
                'error' => 'La consulta no puede estar vacía',
            ];
        }

        try {
            // Fase 1: IA entiende la intención y genera criterios de búsqueda
            $searchCriteria = self::understandIntent($userQuery);
            
            if (!$searchCriteria['success']) {
                return $searchCriteria;
            }

            // Fase 2: Buscar acciones relevantes usando criterios
            $relevantActions = self::findActionsByCriteria($searchCriteria, $userId);
            
            // Fase 3: Si hay muchas acciones, usar IA para priorizar
            if (count($relevantActions) > 10) {
                $relevantActions = self::prioritizeActions($userQuery, $relevantActions, 10);
            }

            // Fase 4: Generar respuesta natural usando IA
            $response = self::generateNaturalResponse($userQuery, $relevantActions, $searchCriteria, $userId);
            
            return $response;

        } catch (\Exception $e) {
            Yii::error("Error procesando consulta universal: " . $e->getMessage(), 'universal-query-agent');
            return [
                'success' => false,
                'error' => 'Error al procesar la consulta. Por favor, intente nuevamente.',
            ];
        }
    }

    /**
     * Fase 1: Entender intención y generar criterios de búsqueda
     * La IA analiza la consulta SIN ver todas las acciones
     * @param string $userQuery
     * @return array
     */
    private static function understandIntent($userQuery)
    {
        $userContext = self::getUserContext();
        
        // Obtener categorías disponibles desde acciones descubiertas
        $categories = self::getAvailableCategories();
        $categoriesText = !empty($categories) ? implode(', ', $categories) : 'Ninguna categoría específica';
        
        $prompt = <<<PROMPT
Eres un asistente para un sistema de gestión hospitalaria. Analiza la siguiente consulta del usuario y determina qué está buscando.

CONTEXTO:
- Usuario: {$userContext['name']}
- Fecha actual: {$userContext['current_date']}
- Categorías disponibles en el sistema: {$categoriesText}

CONSULTA DEL USUARIO: "{$userQuery}"

Tu tarea es entender la intención y generar criterios de búsqueda para encontrar acciones relevantes en el sistema.

Responde ÚNICAMENTE con un JSON válido:
{
  "intent": "descripción breve de lo que el usuario quiere hacer",
  "search_keywords": ["palabra1", "palabra2", "palabra3"],
  "entity_types": ["tipo1", "tipo2"],
  "category": "categoría_más_relevante_o_null",
  "operation_hints": ["operación1", "operación2"],
  "extracted_data": {
    "dni": "valor_si_existe_o_null",
    "fecha": "valor_si_existe_o_null",
    "nombre": "valor_si_existe_o_null",
    "numero": "valor_si_existe_o_null"
  },
  "filters": {
    "user_owned": true/false,
    "date_range": "mes|año|día|null",
    "date_value": "valor_ISO_o_null"
  },
  "query_type": "list_all|search|create|update|delete|count|view|unknown"
}

INSTRUCCIONES:
- "search_keywords": palabras clave relevantes para buscar acciones (mínimo 3, máximo 10)
- "entity_types": tipos de entidades mencionadas (ej: "persona", "consulta", "licencia", "turno")
- "category": si puedes identificar una categoría de la lista, úsala
- Si la consulta es solo un número de 7-8 dígitos, es búsqueda por DNI
- Si menciona "mis", "mías", "del usuario", "voy atendiendo" → user_owned: true
- Si menciona "cuántos", "cantidad", "total", "contar" → query_type: "count"
- Si pregunta qué puede hacer o qué tiene permitido → query_type: "list_all"
PROMPT;

        $iaResponse = self::callIA($prompt);
        
        if (empty($iaResponse)) {
            return [
                'success' => false,
                'error' => 'No se pudo analizar la consulta',
            ];
        }

        $parsed = self::parseJSONResponse($iaResponse);
        
        if (!$parsed) {
            return [
                'success' => false,
                'error' => 'No se pudo interpretar la respuesta',
            ];
        }

        return [
            'success' => true,
            'intent' => $parsed['intent'] ?? 'consulta general',
            'search_keywords' => $parsed['search_keywords'] ?? [],
            'entity_types' => $parsed['entity_types'] ?? [],
            'category' => $parsed['category'] ?? null,
            'operation_hints' => $parsed['operation_hints'] ?? [],
            'extracted_data' => $parsed['extracted_data'] ?? [],
            'filters' => $parsed['filters'] ?? [],
            'query_type' => $parsed['query_type'] ?? 'unknown',
        ];
    }

    /**
     * Fase 2: Buscar acciones usando criterios (búsqueda local inteligente)
     * @param array $criteria
     * @param int|null $userId
     * @return array
     */
    private static function findActionsByCriteria($criteria, $userId = null)
    {
        // Obtener todas las acciones disponibles para el usuario (ya filtradas por permisos)
        $allActions = ActionMappingService::getAvailableActionsForUser($userId);
        
        if (empty($allActions)) {
            return [];
        }

        // Caso especial: listar todos los permisos
        if ($criteria['query_type'] === 'list_all') {
            return $allActions;
        }

        // Caso especial: búsqueda por DNI
        if (!empty($criteria['extracted_data']['dni'])) {
            // Buscar acciones relacionadas con búsqueda de personas
            return self::findPersonSearchActions($allActions, $criteria['extracted_data']['dni']);
        }

        // Búsqueda semántica usando scoring mejorado con metadatos
        $scoredActions = [];
        
        foreach ($allActions as $action) {
            $score = self::calculateSemanticScore($action, $criteria);
            
            if ($score > 0) {
                $scoredActions[] = [
                    'action' => $action,
                    'score' => $score,
                ];
            }
        }

        // Ordenar por score
        usort($scoredActions, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // Retornar acciones con score > 0
        return array_map(function($item) {
            return $item['action'];
        }, $scoredActions);
    }

    /**
     * Calcular score semántico mejorado usando metadatos de docblocks
     * @param array $action
     * @param array $criteria
     * @return float
     */
    private static function calculateSemanticScore($action, $criteria)
    {
        $score = 0.0;
        
        // Texto completo de la acción para búsqueda básica
        $actionText = strtolower(
            $action['display_name'] . ' ' . 
            $action['description'] . ' ' . 
            $action['controller'] . ' ' . 
            $action['action'] . ' ' .
            $action['route']
        );

        // Score por keywords básicas (búsqueda en texto)
        $keywords = $criteria['search_keywords'] ?? [];
        foreach ($keywords as $keyword) {
            $keyword = strtolower(trim($keyword));
            if (strlen($keyword) < 3) continue;
            
            // Coincidencia exacta en texto
            if (stripos($actionText, $keyword) !== false) {
                $score += 2.0;
            }
        }

        // Score por categoría (muy alto si coincide)
        if (!empty($criteria['category']) && !empty($action['category'])) {
            if (strtolower($action['category']) === strtolower($criteria['category'])) {
                $score += 15.0; // Bonus muy alto por coincidencia de categoría
            }
        }

        // Score por tags del docblock
        if (!empty($action['tags']) && is_array($action['tags'])) {
            foreach ($keywords as $keyword) {
                $keyword = strtolower(trim($keyword));
                foreach ($action['tags'] as $tag) {
                    $tag = strtolower(trim($tag));
                    if ($tag === $keyword) {
                        $score += 8.0; // Coincidencia exacta en tag
                    } elseif (stripos($tag, $keyword) !== false || stripos($keyword, $tag) !== false) {
                        $score += 5.0; // Coincidencia parcial en tag
                    }
                }
            }
        }

        // Score por keywords específicas del docblock
        if (!empty($action['keywords']) && is_array($action['keywords'])) {
            foreach ($keywords as $keyword) {
                $keyword = strtolower(trim($keyword));
                foreach ($action['keywords'] as $actionKeyword) {
                    $actionKeyword = strtolower(trim($actionKeyword));
                    if ($actionKeyword === $keyword) {
                        $score += 10.0; // Coincidencia exacta en keyword
                    } elseif (stripos($actionKeyword, $keyword) !== false) {
                        $score += 6.0; // Coincidencia parcial en keyword
                    }
                }
            }
        }

        // Score por entity types
        $entityTypes = $criteria['entity_types'] ?? [];
        foreach ($entityTypes as $entity) {
            $entity = strtolower($entity);
            
            // Búsqueda básica en texto
            if (stripos($actionText, $entity) !== false) {
                $score += 5.0;
            }
            
            // Búsqueda en nombre del controlador
            if (stripos($action['controller'], $entity) !== false) {
                $score += 4.0;
            }
            
            // Score por sinónimos del docblock
            if (!empty($action['synonyms']) && is_array($action['synonyms'])) {
                foreach ($action['synonyms'] as $synonym) {
                    $synonym = strtolower(trim($synonym));
                    if ($synonym === $entity) {
                        $score += 9.0; // Coincidencia exacta con sinónimo
                    } elseif (stripos($synonym, $entity) !== false || stripos($entity, $synonym) !== false) {
                        $score += 6.0; // Coincidencia parcial con sinónimo
                    }
                }
            }
        }

        // Score por operation hints
        $operationHints = $criteria['operation_hints'] ?? [];
        $actionName = strtolower($action['action']);
        foreach ($operationHints as $hint) {
            $hint = strtolower($hint);
            if (stripos($actionName, $hint) !== false) {
                $score += 4.0;
            }
            if (stripos($actionText, $hint) !== false) {
                $score += 2.0;
            }
        }

        // Bonus por query_type
        $queryType = $criteria['query_type'] ?? 'unknown';
        $queryTypeMapping = [
            'list' => ['index', 'list', 'listar', 'ver todos'],
            'search' => ['search', 'buscar', 'find', 'filter'],
            'create' => ['create', 'crear', 'new', 'nuevo'],
            'update' => ['update', 'editar', 'edit', 'modificar'],
            'delete' => ['delete', 'eliminar', 'remove'],
            'view' => ['view', 'ver', 'show', 'detalle'],
            'count' => ['count', 'contar', 'total', 'cantidad', 'estadistica'],
        ];

        if (isset($queryTypeMapping[$queryType])) {
            foreach ($queryTypeMapping[$queryType] as $opKeyword) {
                if (stripos($actionName, $opKeyword) !== false) {
                    $score += 6.0;
                    break;
                }
            }
        }

        return $score;
    }

    /**
     * Buscar acciones relacionadas con búsqueda de personas por DNI
     * @param array $allActions
     * @param string $dni
     * @return array
     */
    private static function findPersonSearchActions($allActions, $dni)
    {
        $personActions = [];
        
        foreach ($allActions as $action) {
            $actionText = strtolower(
                $action['display_name'] . ' ' . 
                $action['description'] . ' ' . 
                $action['controller']
            );
            
            if (stripos($actionText, 'persona') !== false || 
                stripos($action['controller'], 'persona') !== false ||
                (!empty($action['category']) && stripos($action['category'], 'Pacientes') !== false)) {
                $personActions[] = $action;
            }
        }

        return $personActions;
    }

    /**
     * Fase 3: Priorizar acciones usando IA (si hay muchas)
     * @param string $userQuery
     * @param array $actions
     * @param int $limit
     * @return array
     */
    private static function prioritizeActions($userQuery, $actions, $limit = 10)
    {
        // Preparar resumen de acciones para la IA (solo las primeras 20 para no saturar)
        $actionsSample = array_slice($actions, 0, 20);
        $actionsSummary = [];
        
        foreach ($actionsSample as $action) {
            $actionsSummary[] = [
                'route' => $action['route'],
                'name' => $action['display_name'],
                'description' => $action['description'],
            ];
        }

        $actionsJson = json_encode($actionsSummary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
El usuario hizo esta consulta: "{$userQuery}"

Tengo estas acciones disponibles. Selecciona las {$limit} más relevantes para la consulta del usuario.

Acciones disponibles:
{$actionsJson}

Responde ÚNICAMENTE con un JSON que contenga un array de rutas (solo las rutas):
{
  "selected_routes": ["/ruta1", "/ruta2", "/ruta3"]
}

Solo incluye las rutas más relevantes para la consulta del usuario.
PROMPT;

        $iaResponse = self::callIA($prompt);
        $parsed = self::parseJSONResponse($iaResponse);
        
        if ($parsed && isset($parsed['selected_routes'])) {
            $selectedRoutes = $parsed['selected_routes'];
            $prioritized = [];
            
            foreach ($actions as $action) {
                if (in_array($action['route'], $selectedRoutes)) {
                    $prioritized[] = $action;
                }
            }
            
            // Si la IA no seleccionó suficientes, completar con las mejores
            if (count($prioritized) < $limit) {
                $remaining = array_slice($actions, count($prioritized), $limit - count($prioritized));
                $prioritized = array_merge($prioritized, $remaining);
            }
            
            return array_slice($prioritized, 0, $limit);
        }

        // Si falla, retornar las primeras N
        return array_slice($actions, 0, $limit);
    }

    /**
     * Fase 4: Generar respuesta natural usando IA
     * @param string $userQuery
     * @param array $actions
     * @param array $criteria
     * @param int|null $userId
     * @return array
     */
    private static function generateNaturalResponse($userQuery, $actions, $criteria, $userId = null)
    {
        // Caso especial: búsqueda por DNI
        if (!empty($criteria['extracted_data']['dni'])) {
            return self::handleDniSearch($criteria['extracted_data']['dni'], $actions);
        }

        // Caso especial: listar todos los permisos
        if ($criteria['query_type'] === 'list_all') {
            return self::formatListAllResponse($actions);
        }

        // Si no hay acciones, intentar sugerir acciones relacionadas o comunes
        if (empty($actions)) {
            // Obtener algunas acciones comunes del usuario como sugerencias
            $allUserActions = ActionMappingService::getAvailableActionsForUser($userId);
            
            // Filtrar acciones comunes relacionadas con la consulta
            $suggestedActions = self::suggestRelatedActions($userQuery, $allUserActions, $criteria);
            
            // Si hay sugerencias, incluirlas
            if (!empty($suggestedActions)) {
                return [
                    'success' => false,
                    'explanation' => 'No encontré acciones específicas para tu consulta, pero aquí tienes algunas opciones que podrían ayudarte:',
                    'actions' => array_slice($suggestedActions, 0, 5),
                    'suggested_query' => '¿qué puedo hacer?',
                ];
            }
            
            return [
                'success' => false,
                'explanation' => 'No encontré acciones específicas para tu consulta. Puedes preguntar "¿qué puedo hacer?" para ver todas tus opciones disponibles.',
                'suggested_query' => '¿qué puedo hacer?',
            ];
        }

        // Preparar resumen de acciones para la IA
        $actionsSummary = [];
        foreach (array_slice($actions, 0, 10) as $action) {
            $actionsSummary[] = [
                'route' => $action['route'],
                'name' => $action['display_name'],
                'description' => $action['description'],
            ];
        }

        $actionsJson = json_encode($actionsSummary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
El usuario hizo esta consulta: "{$userQuery}"

Encontré estas acciones relevantes en el sistema:
{$actionsJson}

Genera una respuesta natural y amigable en español que:
1. Explique qué encontré relacionado con su consulta
2. Liste las acciones más relevantes con sus rutas
3. Sea concisa pero informativa

Responde ÚNICAMENTE con un JSON:
{
  "explanation": "Explicación natural de lo que encontré",
  "actions": [
    {
      "route": "/ruta/exacta",
      "name": "Nombre de la acción",
      "description": "Por qué es relevante"
    }
  ],
  "count": número_total_de_acciones
}
PROMPT;

        $iaResponse = self::callIA($prompt);
        $parsed = self::parseJSONResponse($iaResponse);
        
        if ($parsed) {
            return [
                'success' => true,
                'explanation' => $parsed['explanation'] ?? 'Encontré estas acciones relacionadas con tu consulta.',
                'actions' => $parsed['actions'] ?? $actionsSummary,
                'count' => $parsed['count'] ?? count($actions),
                'query_type' => $criteria['query_type'],
            ];
        }

        // Fallback: respuesta básica
        return [
            'success' => true,
            'explanation' => 'Encontré ' . count($actions) . ' acciones relacionadas con tu consulta.',
            'actions' => array_slice($actions, 0, 10),
            'count' => count($actions),
            'query_type' => $criteria['query_type'],
        ];
    }

    /**
     * Formatear respuesta para "listar todos los permisos"
     * @param array $actions
     * @return array
     */
    private static function formatListAllResponse($actions)
    {
        // Agrupar por controlador
        $grouped = [];
        foreach ($actions as $action) {
            $controller = $action['controller'];
            if (!isset($grouped[$controller])) {
                $grouped[$controller] = [];
            }
            $grouped[$controller][] = $action;
        }

        return [
            'success' => true,
            'explanation' => 'Aquí tienes todas las acciones que tienes permitido realizar en el sistema:',
            'actions' => $actions,
            'grouped_by_controller' => $grouped,
            'total_count' => count($actions),
            'query_type' => 'list_all',
        ];
    }

    /**
     * Manejar búsqueda por DNI
     * @param string $dni
     * @param array $personActions
     * @return array
     */
    private static function handleDniSearch($dni, $personActions)
    {
        // Intentar buscar persona directamente
        /** @var \common\models\Persona|null $persona */
        $persona = \common\models\Persona::find()
            ->where(['documento' => $dni])
            ->one();

        if ($persona !== null) {
            $nombreCompleto = $persona->getNombreCompleto(\common\models\Persona::FORMATO_NOMBRE_A_N);
            
            return [
                'success' => true,
                'explanation' => "Encontré una persona con DNI {$dni}.",
                'action' => [
                    'route' => '/personas/view',
                    'type' => 'navigate',
                    'params' => ['id' => $persona->id_persona],
                    'name' => "Ver detalles de {$nombreCompleto}",
                    'description' => "Ver información completa y historial de la persona",
                ],
                'data' => [
                    'nombre' => $nombreCompleto,
                    'dni' => $persona->documento,
                ],
                'alternative_actions' => $personActions,
            ];
        }

        return [
            'success' => false,
            'explanation' => "No se encontró ninguna persona con DNI {$dni}.",
            'suggested_actions' => $personActions,
            'suggested_query' => 'Puedes usar las acciones de búsqueda de personas para buscar por otros criterios.',
        ];
    }

    /**
     * Sugerir acciones relacionadas cuando no se encuentran acciones específicas
     * @param string $userQuery
     * @param array $allActions
     * @param array $criteria
     * @return array
     */
    private static function suggestRelatedActions($userQuery, $allActions, $criteria)
    {
        $suggested = [];
        $queryLower = strtolower($userQuery);
        
        // Buscar acciones que puedan estar relacionadas
        foreach ($allActions as $action) {
            $score = 0.0;
            $actionText = strtolower(
                $action['display_name'] . ' ' . 
                $action['description'] . ' ' . 
                $action['controller']
            );
            
            // Buscar palabras clave comunes en la consulta
            $commonWords = ['licencia', 'permiso', 'ausencia', 'vacaciones', 'crear', 'nuevo', 'solicitar'];
            foreach ($commonWords as $word) {
                if (stripos($queryLower, $word) !== false) {
                    // Si la acción también menciona algo relacionado
                    if (stripos($actionText, $word) !== false || 
                        stripos($actionText, 'crear') !== false || 
                        stripos($actionText, 'nuevo') !== false ||
                        stripos($actionText, 'solicitar') !== false) {
                        $score += 3.0;
                    }
                }
            }
            
            // Priorizar acciones de creación si la consulta menciona "necesito", "quiero", "solicitar"
            if (preg_match('/\b(necesito|quiero|solicitar|pedir)\b/i', $queryLower)) {
                if (stripos($actionText, 'crear') !== false || 
                    stripos($actionText, 'nuevo') !== false ||
                    stripos($action['action'], 'create') !== false) {
                    $score += 5.0;
                }
            }
            
            if ($score > 0) {
                $suggested[] = [
                    'action' => $action,
                    'score' => $score,
                ];
            }
        }
        
        // Ordenar por score
        usort($suggested, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Retornar solo las acciones
        return array_map(function($item) {
            return $item['action'];
        }, $suggested);
    }

    /**
     * Obtener categorías disponibles desde acciones descubiertas
     * @return array
     */
    private static function getAvailableCategories()
    {
        $actions = ActionDiscoveryService::discoverAllActions();
        $categories = [];
        
        foreach ($actions as $action) {
            if (!empty($action['category']) && is_string($action['category'])) {
                $categories[$action['category']] = true;
            }
        }
        
        return array_keys($categories);
    }

    /**
     * Obtener contexto del usuario
     * @return array
     */
    private static function getUserContext()
    {
        $userId = Yii::$app->user->id ?? null;
        $user = null;
        
        if ($userId) {
            $user = \webvimark\modules\UserManagement\models\User::findOne($userId);
        }

        return [
            'id' => $userId,
            'name' => $user->username ?? 'usuario',
            'current_date' => date('Y-m-d'),
            'current_month' => date('Y-m'),
            'current_year' => date('Y'),
        ];
    }

    /**
     * Llamar a IA
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
            Yii::error("Error llamando a IA: " . $e->getMessage(), 'universal-query-agent');
        }

        return null;
    }

    /**
     * Parsear respuesta JSON de IA
     * @param string $response
     * @return array|null
     */
    private static function parseJSONResponse($response)
    {
        // Buscar JSON en la respuesta
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $response, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        // Intentar parsear toda la respuesta
        $json = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }

        return null;
    }
}
