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
        
        // Prompt optimizado (reducido 35% para reducir costos)
        $prompt = <<<PROMPT
Analiza consulta y genera criterios de búsqueda.

Contexto: Usuario: {$userContext['name']}, Fecha: {$userContext['current_date']}, Categorías: {$categoriesText}

Consulta: "{$userQuery}"

Responde JSON:
{
  "intent": "descripción breve",
  "search_keywords": ["palabra1", "palabra2"],
  "entity_types": ["tipo1"],
  "category": "categoría_o_null",
  "operation_hints": ["operación"],
  "extracted_data": {"dni": "valor_o_null", "fecha": "valor_o_null", "nombre": "valor_o_null"},
  "filters": {"user_owned": true/false, "date_range": "mes|año|día|null"},
  "query_type": "list_all|search|create|update|delete|count|view|unknown"
}

Reglas: 7-8 dígitos=DNI, "mis"/"mías"=user_owned:true, "cuántos"/"cantidad"=count, "qué puedo"=list_all
PROMPT;

        $iaResponse = self::callIA($prompt);
        
        if (empty($iaResponse)) {
            Yii::error("UniversalQueryAgent: callIA devolvió respuesta vacía. Consulta: '{$userQuery}', Prompt length: " . strlen($prompt), 'universal-query-agent');
            return [
                'success' => false,
                'error' => 'No se pudo analizar la consulta',
            ];
        }

        $parsed = self::parseJSONResponse($iaResponse);
        
        if (!$parsed) {
            // Log detallado para debugging
            $jsonError = json_last_error();
            $jsonErrorMsg = json_last_error_msg();
            
            Yii::error("UniversalQueryAgent: No se pudo parsear la respuesta de la IA. " .
                "Consulta: '{$userQuery}'. " .
                "Respuesta recibida completa: {$iaResponse}. " .
                "Longitud total: " . strlen($iaResponse) . " caracteres. " .
                "JSON Error Code: {$jsonError}. " .
                "JSON Error Message: {$jsonErrorMsg}", 
                'universal-query-agent');
            
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
        // Obtener todas las acciones disponibles para el usuario logueado (ya filtradas por permisos)
        $allActions = ActionMappingService::getAvailableActionsForUser();
        
        // Log para debugging
        $currentUserId = Yii::$app->user->id ?? 'no-autenticado';
        Yii::info("UniversalQueryAgent::findActionsByCriteria - userId: {$currentUserId}, query_type: {$criteria['query_type']}, acciones encontradas: " . count($allActions), 'universal-query-agent');
        
        if (empty($allActions)) {
            Yii::warning("UniversalQueryAgent::findActionsByCriteria - No se encontraron acciones para userId: {$currentUserId}", 'universal-query-agent');
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
            $allUserActions = ActionMappingService::getAvailableActionsForUser();
            
            // Filtrar acciones comunes relacionadas con la consulta
            $suggestedActions = self::suggestRelatedActions($userQuery, $allUserActions, $criteria);
            
            // Si hay sugerencias, incluirlas
            if (!empty($suggestedActions)) {
                $suggestedSlice = array_slice($suggestedActions, 0, 5);
                return [
                    'success' => true, // Es una respuesta válida del sistema, no un error
                    'explanation' => 'No encontré acciones específicas para tu consulta, pero aquí tienes algunas opciones que podrían ayudarte:',
                    'actions' => self::formatActionsForResponse($suggestedSlice),
                    'suggested_query' => '¿qué puedo hacer?',
                ];
            }
            
            return [
                'success' => true, // Es una respuesta válida del sistema, no un error
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
        
        // Formatear acciones antes de devolver (usar las acciones originales, no las del LLM)
        $formattedActions = self::formatActionsForResponse(array_slice($actions, 0, 10));
        
        if ($parsed) {
            return [
                'success' => true,
                'explanation' => $parsed['explanation'] ?? 'Encontré estas acciones relacionadas con tu consulta.',
                'actions' => $formattedActions,
                'count' => $parsed['count'] ?? count($actions),
                'query_type' => $criteria['query_type'] ?? 'unknown',
            ];
        }

        // Fallback: respuesta básica
        return [
            'success' => true,
            'explanation' => 'Encontré ' . count($actions) . ' acciones relacionadas con tu consulta.',
            'actions' => $formattedActions,
            'count' => count($actions),
            'query_type' => $criteria['query_type'] ?? 'unknown',
        ];
    }

    /**
     * Formatear acciones para respuesta (solo campos necesarios para UI)
     * @param array|array[] $actions Una acción o array de acciones
     * @return array|array[] Acción o array de acciones formateadas
     */
    private static function formatActionsForResponse($actions)
    {
        // Si está vacío, retornar array vacío
        if (empty($actions)) {
            return [];
        }
        
        // Si es una sola acción (no array de arrays - verificar si tiene claves asociativas pero no índice numérico)
        if (isset($actions['action_id']) || (isset($actions['route']) && !isset($actions[0]))) {
            return [
                'action_id' => $actions['action_id'] ?? self::generateActionId($actions),
                'display_name' => $actions['display_name'] ?? '',
                'description' => $actions['description'] ?? '',
                'category' => $actions['category'] ?? null,
            ];
        }
        
        // Si es array de acciones (tiene índice numérico)
        $formatted = [];
        foreach ($actions as $action) {
            if (is_array($action)) {
                $formatted[] = [
                    'action_id' => $action['action_id'] ?? self::generateActionId($action),
                    'display_name' => $action['display_name'] ?? '',
                    'description' => $action['description'] ?? '',
                    'category' => $action['category'] ?? null,
                ];
            }
        }
        
        return $formatted;
    }

    /**
     * Generar action_id desde una acción si no existe
     * @param array $action
     * @return string
     */
    private static function generateActionId($action)
    {
        // Si ya tiene action_id, usarlo
        if (!empty($action['action_id'])) {
            return $action['action_id'];
        }
        
        // Generar desde controller y action
        $controller = $action['controller'] ?? '';
        $actionName = $action['action'] ?? '';
        
        if ($controller && $actionName) {
            return strtolower($controller . '.' . $actionName);
        }
        
        // Fallback: usar route si existe
        if (!empty($action['route'])) {
            $route = trim($action['route'], '/');
            $parts = explode('/', $route);
            if (count($parts) >= 3) {
                return strtolower($parts[count($parts) - 2] . '.' . $parts[count($parts) - 1]);
            }
        }
        
        return 'unknown';
    }

    /**
     * Formatear respuesta para "listar todos los permisos"
     * @param array $actions
     * @return array
     */
    private static function formatListAllResponse($actions)
    {
        // Formatear acciones (solo campos necesarios)
        $formattedActions = self::formatActionsForResponse($actions);
        
        // Agrupar por controlador (necesitamos el controller original para agrupar)
        $grouped = [];
        foreach ($actions as $index => $action) {
            $controller = $action['controller'] ?? 'General';
            if (!isset($grouped[$controller])) {
                $grouped[$controller] = [];
            }
            // Usar la acción formateada correspondiente
            if (isset($formattedActions[$index])) {
                $grouped[$controller][] = $formattedActions[$index];
            }
        }

        return [
            'success' => true,
            'explanation' => 'Aquí tienes todas las acciones que tienes permitido realizar en el sistema:',
            'actions' => $formattedActions,
            'grouped_by_controller' => $grouped,
            'total_count' => count($formattedActions),
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
                    'action_id' => 'personas.view',
                    'display_name' => "Ver detalles de {$nombreCompleto}",
                    'description' => "Ver información completa y historial de la persona",
                    'category' => 'Personas',
                    // Mantener params para ejecución
                    'params' => ['id' => $persona->id_persona],
                ],
                'data' => [
                    'nombre' => $nombreCompleto,
                    'dni' => $persona->documento,
                ],
                'alternative_actions' => self::formatActionsForResponse($personActions),
            ];
        }

        return [
            'success' => false,
            'explanation' => "No se encontró ninguna persona con DNI {$dni}.",
            'suggested_actions' => self::formatActionsForResponse($personActions),
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
            
            if (empty($proveedorIA)) {
                Yii::error("UniversalQueryAgent: No se pudo obtener proveedor de IA", 'universal-query-agent');
                return null;
            }
            
            IAManager::asignarPromptAConfiguracion($proveedorIA, $prompt);
            
            // Log del payload antes de enviar (especialmente para Google)
            if ($proveedorIA['tipo'] === 'google') {
                $maxOutputTokens = $proveedorIA['payload']['generationConfig']['maxOutputTokens'] ?? 'no configurado';
                Yii::info("UniversalQueryAgent: Enviando request a Google con maxOutputTokens: {$maxOutputTokens}. Payload completo: " . json_encode($proveedorIA['payload'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 'universal-query-agent');
            }
            
            $client = new \yii\httpclient\Client();
            $request = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($proveedorIA['endpoint'])
                ->addHeaders($proveedorIA['headers'])
                ->setContent(json_encode($proveedorIA['payload']));
            
            $response = $request->send();

            if ($response->isOk) {
                $processedResponse = IAManager::procesarRespuestaProveedor($response, $proveedorIA['tipo']);
                
                // Log de lo que devuelve procesarRespuestaProveedor antes de pasarlo a parseJSONResponse
                $processedResponseType = gettype($processedResponse);
                $processedResponseLength = is_string($processedResponse) ? strlen($processedResponse) : 'N/A';
                $processedResponsePreview = is_string($processedResponse) ? $processedResponse : (is_array($processedResponse) ? json_encode($processedResponse, JSON_UNESCAPED_UNICODE) : (string)$processedResponse);
                Yii::info("UniversalQueryAgent::callIA - Respuesta procesada recibida. Tipo: {$processedResponseType}, Longitud: {$processedResponseLength}, Contenido: {$processedResponsePreview}", 'universal-query-agent');
                
                if (empty($processedResponse)) {
                    Yii::error("UniversalQueryAgent: procesarRespuestaProveedor devolvió vacío. Status: {$response->statusCode}, Tipo: {$proveedorIA['tipo']}, Response body: " . substr($response->content, 0, 500), 'universal-query-agent');
                }
                
                return $processedResponse;
            } else {
                Yii::error("UniversalQueryAgent: Respuesta HTTP no OK. Status: {$response->statusCode}, Endpoint: {$proveedorIA['endpoint']}, Response: " . substr($response->content, 0, 500), 'universal-query-agent');
            }
        } catch (\Exception $e) {
            Yii::error("UniversalQueryAgent: Excepción llamando a IA. Mensaje: {$e->getMessage()}, Trace: " . $e->getTraceAsString(), 'universal-query-agent');
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
        // Log de la respuesta antes de intentar parsear
        Yii::info("UniversalQueryAgent: Respuesta recibida para parsear. Longitud: " . strlen($response) . " caracteres. Contenido: {$response}", 'universal-query-agent');
        
        // Paso 1: Intentar extraer JSON de code blocks markdown (```json ... ```)
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $response, $matches)) {
            $jsonContent = trim($matches[1]);
            $json = json_decode($jsonContent, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                Yii::info("UniversalQueryAgent: JSON extraído de code block markdown", 'universal-query-agent');
                return $json;
            }
        }
        
        // Paso 2: Buscar JSON balanceado (que comience con { y termine con })
        // Usar un método más robusto que cuente las llaves
        $startPos = strpos($response, '{');
        if ($startPos !== false) {
            $depth = 0;
            $endPos = $startPos;
            $inString = false;
            $escapeNext = false;
            
            for ($i = $startPos; $i < strlen($response); $i++) {
                $char = $response[$i];
                
                if ($escapeNext) {
                    $escapeNext = false;
                    continue;
                }
                
                if ($char === '\\') {
                    $escapeNext = true;
                    continue;
                }
                
                if ($char === '"' && !$escapeNext) {
                    $inString = !$inString;
                    continue;
                }
                
                if (!$inString) {
                    if ($char === '{') {
                        $depth++;
                    } elseif ($char === '}') {
                        $depth--;
                        if ($depth === 0) {
                            $endPos = $i;
                            break;
                        }
                    }
                }
            }
            
            if ($depth === 0 && $endPos > $startPos) {
                $jsonContent = substr($response, $startPos, $endPos - $startPos + 1);
                $json = json_decode($jsonContent, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    Yii::info("UniversalQueryAgent: JSON extraído con balanceo de llaves", 'universal-query-agent');
                    return $json;
                }
            }
        }
        
        // Paso 3: Intentar extraer JSON con regex (método anterior como fallback)
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $response, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                Yii::info("UniversalQueryAgent: JSON extraído con regex", 'universal-query-agent');
                return $json;
            }
        }

        // Paso 4: Intentar parsear toda la respuesta como JSON
        $json = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            Yii::info("UniversalQueryAgent: JSON parseado directamente de la respuesta completa", 'universal-query-agent');
            return $json;
        }

        Yii::warning("UniversalQueryAgent: No se pudo parsear JSON de ninguna forma. JSON Error: " . json_last_error_msg(), 'universal-query-agent');
        return null;
    }
}
