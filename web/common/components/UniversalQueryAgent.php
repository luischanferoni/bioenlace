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
     * Método de prueba para testear el matching de acciones con criterios JSON
     * Útil para debugging y testing desde el navegador
     * 
     * @param array $criteria Criterios de búsqueda (formato JSON parseado)
     * @param int|null $userId ID del usuario
     * @return array Resultado detallado con acciones encontradas y scores
     */
    public static function testFindActions($criteria, $userId = null)
    {
        try {
            // Obtener todas las acciones disponibles
            $allActions = \common\components\ActionMappingService::getAvailableActionsForUser($userId);
            
            // Calcular scores para todas las acciones
            $scoredActions = [];
            $debugScores = [];
            $allScores = []; // Para análisis de por qué no hay match
            
            foreach ($allActions as $action) {
                $score = self::calculateSemanticScore($action, $criteria);
                
                // Guardar TODOS los scores para análisis
                $allScores[] = [
                    'action_id' => $action['action_id'] ?? 'N/A',
                    'controller' => $action['controller'] ?? 'N/A',
                    'action' => $action['action'] ?? 'N/A',
                    'display_name' => $action['display_name'] ?? 'N/A',
                    'entity' => $action['entity'] ?? 'N/A',
                    'score' => $score,
                ];
                
                // Guardar información de debugging para todas las acciones de turnos
                if (stripos($action['controller'] ?? '', 'turno') !== false || 
                    stripos($action['route'] ?? '', 'turno') !== false) {
                    $debugScores[] = [
                        'action_id' => $action['action_id'] ?? 'N/A',
                        'controller' => $action['controller'] ?? 'N/A',
                        'action' => $action['action'] ?? 'N/A',
                        'route' => $action['route'] ?? 'N/A',
                        'display_name' => $action['display_name'] ?? 'N/A',
                        'entity' => $action['entity'] ?? 'N/A',
                        'tags' => $action['tags'] ?? [],
                        'keywords' => $action['keywords'] ?? [],
                        'score' => $score,
                    ];
                }
                
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
            
            // Obtener acción encontrada usando el método normal (devuelve un solo elemento o null)
            $foundAction = self::findActionsByCriteria($criteria, $userId);
            
            // Verificar compatibilidad con id_servicio si está presente en los criterios
            $servicioInfo = self::validateServicioInCriteria($criteria);
            $servicioCompatible = true;
            $servicioValidationDetails = [];
            
            if ($servicioInfo['has_servicio'] && $foundAction !== null) {
                // Verificar si la acción encontrada requiere id_servicio
                $requiresServicio = self::actionRequiresServicio($foundAction);
                
                if ($requiresServicio) {
                    // Verificar si el id_servicio es válido
                    if (!$servicioInfo['is_valid']) {
                        $servicioCompatible = false;
                        $servicioValidationDetails = [
                            'message' => 'El id_servicio proporcionado no es válido',
                            'id_servicio_provided' => $servicioInfo['id_servicio'],
                            'servicio_name' => $servicioInfo['servicio_name'],
                            'action_requires_servicio' => true,
                        ];
                    } else {
                        $servicioValidationDetails = [
                            'message' => 'El id_servicio es válido y compatible con la acción encontrada',
                            'id_servicio' => $servicioInfo['id_servicio'],
                            'servicio_name' => $servicioInfo['servicio_name'],
                            'action_requires_servicio' => true,
                        ];
                    }
                }
            }
            
            // Determinar si hay asociación exitosa (considerando servicio si aplica)
            $hasAssociation = $foundAction !== null && $servicioCompatible;
            
            // Analizar por qué no hay asociación si es el caso
            $associationAnalysis = [
                'has_association' => $hasAssociation,
                'reason' => null,
                'details' => [],
            ];
            
            if (!$hasAssociation) {
                // Ordenar todos los scores para ver los mejores
                usort($allScores, function($a, $b) {
                    return $b['score'] <=> $a['score'];
                });
                
                $topScores = array_slice($allScores, 0, 5);
                $maxScore = !empty($topScores) ? $topScores[0]['score'] : 0;
                
                if (empty($allActions)) {
                    $associationAnalysis['reason'] = 'no_actions_available';
                    $associationAnalysis['details'] = [
                        'message' => 'No hay acciones disponibles para el usuario',
                        'user_id' => $userId,
                    ];
                } elseif ($maxScore == 0) {
                    $associationAnalysis['reason'] = 'no_semantic_match';
                    $associationAnalysis['details'] = [
                        'message' => 'Ninguna acción obtuvo score > 0. Los criterios no coinciden con ninguna acción disponible.',
                        'criteria_received' => [
                            'query_type' => $criteria['query_type'] ?? 'N/A',
                            'entity_type' => $criteria['entity_type'] ?? 'N/A',
                            'search_keywords' => $criteria['search_keywords'] ?? [],
                            'entity_types' => $criteria['entity_types'] ?? [],
                            'operation_hints' => $criteria['operation_hints'] ?? [],
                        ],
                        'top_5_actions_checked' => $topScores,
                        'total_actions_checked' => count($allActions),
                    ];
                } else {
                    // Verificar si el problema es el servicio
                    if (!$servicioCompatible && !empty($servicioValidationDetails)) {
                        $associationAnalysis['reason'] = 'invalid_servicio';
                        $associationAnalysis['details'] = array_merge([
                            'message' => 'Algunas acciones obtuvieron score > 0, pero el id_servicio proporcionado no es válido',
                            'max_score_found' => $maxScore,
                            'top_5_actions_with_score' => $topScores,
                            'actions_with_score_count' => count($scoredActions),
                        ], $servicioValidationDetails);
                    } else {
                        $associationAnalysis['reason'] = 'low_score_threshold';
                        $associationAnalysis['details'] = [
                            'message' => 'Algunas acciones obtuvieron score > 0, pero no pasaron el filtro final.',
                            'max_score_found' => $maxScore,
                            'top_5_actions_with_score' => $topScores,
                            'actions_with_score_count' => count($scoredActions),
                        ];
                    }
                }
            } else {
                // Verificar si hay problema con el servicio
                if (!$servicioCompatible && !empty($servicioValidationDetails)) {
                    $associationAnalysis['reason'] = 'invalid_servicio';
                    $associationAnalysis['details'] = array_merge([
                        'message' => 'Se encontraron acciones, pero el id_servicio proporcionado no es válido',
                        'best_match_score' => !empty($scoredActions) ? $scoredActions[0]['score'] : 0,
                    ], $servicioValidationDetails);
                } else {
                    $associationAnalysis['reason'] = 'success';
                    $associationAnalysis['details'] = [
                        'message' => 'Se encontraron acciones asociadas exitosamente',
                        'best_match_score' => !empty($scoredActions) ? $scoredActions[0]['score'] : 0,
                    ];
                    if (!empty($servicioValidationDetails)) {
                        $associationAnalysis['details']['servicio_validation'] = $servicioValidationDetails;
                    }
                }
            }
            
            return [
                'success' => true,
                'criteria' => $criteria,
                'has_association' => $hasAssociation,
                'association_analysis' => $associationAnalysis,
                'total_actions_available' => count($allActions),
                'actions_with_score' => count($scoredActions),
                'actions_found' => $foundAction !== null ? 1 : 0,
                'top_scored_actions' => array_slice($scoredActions, 0, 10),
                'found_actions' => $foundAction !== null ? [[
                    'action_id' => $foundAction['action_id'] ?? 'N/A',
                    'controller' => $foundAction['controller'] ?? 'N/A',
                    'action' => $foundAction['action'] ?? 'N/A',
                    'route' => $foundAction['route'] ?? 'N/A',
                    'display_name' => $foundAction['display_name'] ?? 'N/A',
                    'entity' => $foundAction['entity'] ?? 'N/A',
                    'tags' => $foundAction['tags'] ?? [],
                    'keywords' => $foundAction['keywords'] ?? [],
                ]] : [],
                'debug_turnos_actions' => $debugScores,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'has_association' => false,
                'association_analysis' => [
                    'has_association' => false,
                    'reason' => 'error',
                    'details' => [
                        'message' => 'Error al procesar la consulta',
                        'error' => $e->getMessage(),
                    ],
                ],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
        }
    }

    /**
     * Procesar cualquier consulta del usuario
     * @param string $userQuery Cualquier consulta en lenguaje natural
     * @param int|null $userId ID del usuario
     * @return array Respuesta con acciones o datos
     */
    public static function processQuery($userQuery, $userId = null, $actionId = null)
    {
        if (empty($userQuery) && empty($actionId)) {
            return [
                'success' => false,
                'error' => 'La consulta no puede estar vacía',
            ];
        }

        try {
            // FASE 0: Si viene action_id, buscar directamente por ID (más rápido y preciso)
            if (!empty($actionId)) {
                $action = self::findActionById($actionId, $userId);
                if ($action) {
                    // Ejecutar la acción directamente y devolver el resultado
                    $executionResult = self::executeActionDirectly($action, [], $userId);
                    if ($executionResult['success']) {
                        return [
                            'success' => true,
                            'explanation' => "Listado de {$action['display_name']}",
                            'action' => self::formatActionsForResponse($action),
                            'data' => $executionResult['data'] ?? null,
                            'query_type' => 'direct_action',
                            'matched_by' => 'action_id',
                        ];
                    } else {
                        // Si falla la ejecución, devolver la acción para que se pueda ejecutar manualmente
                        return [
                            'success' => true,
                            'explanation' => "Encontré la acción: {$action['display_name']}",
                            'action' => self::formatActionsForResponse($action),
                            'actions' => [self::formatActionsForResponse($action)],
                            'query_type' => 'direct_action',
                            'matched_by' => 'action_id',
                            'error' => $executionResult['error'] ?? 'Error al ejecutar la acción',
                        ];
                    }
                }
            }

            // FASE 1: Matching semántico ANTES del LLM (más rápido y económico)
            // Solo si hay userQuery (si solo viene action_id, ya se manejó arriba)
            if (!empty($userQuery)) {
                $semanticMatch = self::findActionBySemanticMatch($userQuery, $userId);
                
                if ($semanticMatch !== null) {
                    // Ejecutar la acción directamente y devolver el resultado
                    $executionResult = self::executeActionDirectly($semanticMatch, [], $userId);
                    if ($executionResult['success']) {
                        return [
                            'success' => true,
                            'explanation' => "Listado de {$semanticMatch['display_name']}",
                            'action' => self::formatActionsForResponse($semanticMatch),
                            'data' => $executionResult['data'] ?? null,
                            'query_type' => 'direct_action',
                            'matched_by' => 'semantic',
                        ];
                    } else {
                        // Si falla la ejecución, devolver la acción para que se pueda ejecutar manualmente
                        return [
                            'success' => true,
                            'explanation' => "Encontré la acción: {$semanticMatch['display_name']}",
                            'action' => self::formatActionsForResponse($semanticMatch),
                            'actions' => [self::formatActionsForResponse($semanticMatch)],
                            'query_type' => 'direct_action',
                            'matched_by' => 'semantic',
                            'error' => $executionResult['error'] ?? 'Error al ejecutar la acción',
                        ];
                    }
                }
            }

            // FASE 2: IA entiende la intención y genera criterios de búsqueda
            // Solo si hay userQuery
            if (empty($userQuery)) {
                return [
                    'success' => false,
                    'error' => 'Se requiere una consulta o action_id válido',
                ];
            }
            
            $searchCriteria = self::understandIntent($userQuery);
            
            if (!$searchCriteria['success']) {
                return $searchCriteria;
            }

            // FASE 2.5: Si es data_query, buscar business query primero
            if ($searchCriteria['query_type'] === 'data_query') {
                $businessQuery = \common\queries\BusinessQueryRegistry::findMatchingQuery($searchCriteria, $userQuery);
                
                if ($businessQuery) {
                    // Ejecutar business query
                    return self::executeBusinessQuery($businessQuery, $searchCriteria, $userId);
                }
                
                // Si no hay business query, continuar con acciones normales
                // (podría ser una consulta de datos simple que se resuelve con acciones)
            }

            // FASE 3: Buscar acción relevante usando criterios (devuelve un solo elemento o null)
            $foundAction = self::findActionsByCriteria($searchCriteria, $userId);
            
            // Convertir a array para compatibilidad con métodos que esperan arrays
            // Caso especial: si query_type === 'list_all', findActionsByCriteria devuelve array de todas las acciones
            $relevantActions = [];
            if ($foundAction !== null) {
                // Si es array (caso especial list_all), usar directamente
                if (is_array($foundAction) && isset($foundAction[0]) && is_array($foundAction[0])) {
                    // Es el caso especial list_all que devuelve array de acciones
                    $relevantActions = $foundAction;
                } else {
                    // Es una sola acción, convertir a array
                    $relevantActions = [$foundAction];
                }
            }
            
            // FASE 4: Si hay muchas acciones, usar IA para priorizar (solo si es list_all)
            if (count($relevantActions) > 10) {
                $relevantActions = self::prioritizeActions($userQuery, $relevantActions, 10);
            }

            // FASE 5: Generar respuesta natural usando IA
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
        
        // Obtener metadatos del sistema dinámicamente
        $systemMetadata = self::getSystemMetadata();
        
        // Obtener entidades disponibles desde acciones descubiertas
        $entities = self::getAvailableEntities();
        $entitiesText = !empty($entities) ? implode(', ', $entities) : '';
        
        // Obtener business queries disponibles para contexto
        $businessQueriesInfo = self::getBusinessQueriesInfo();
        
        // Prompt genérico y escalable
        $prompt = <<<PROMPT
Analiza esta consulta de usuario en un sistema de gestión de salud:

"{$userQuery}"

Contexto del sistema:
- Usuario: {$userContext['name']}
- Fecha: {$userContext['current_date']}
- Entidades disponibles: {$entitiesText}
- Metadatos: {$systemMetadata}
{$businessQueriesInfo}

Extrae información de la consulta y responde ÚNICAMENTE con este JSON:

{
  "intent": "descripción de la intención",
  "search_keywords": ["palabras", "relevantes"],
  "entity_types": ["tipos", "de", "entidades"],
  "entity_type": "entidad principal o null",
  "operation_hints": ["operaciones", "detectadas"],
  "extracted_data": {
    "identifiers": [],
    "dates": [],
    "names": [],
    "numbers": []
  },
  "filters": {
    "user_owned": true/false/null,
    "date_range": "rango o null",
    "custom": {}
  },
  "query_type": "list_all|search|create|update|delete|count|view|data_query|unknown"
}

Usa tu conocimiento del lenguaje para extraer información relevante de la consulta.
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
            'entity_type' => $parsed['entity_type'] ?? null,
            'operation_hints' => $parsed['operation_hints'] ?? [],
            'extracted_data' => self::normalizeExtractedData($parsed['extracted_data'] ?? []),
            'filters' => $parsed['filters'] ?? [],
            'query_type' => $parsed['query_type'] ?? 'unknown',
        ];
    }

    /**
     * Ejecutar business query
     * @param array $businessQuery
     * @param array $criteria
     * @param int|null $userId
     * @return array
     */
    private static function executeBusinessQuery($businessQuery, $criteria, $userId = null)
    {
        try {
            // Mapear datos extraídos a parámetros de la query
            $params = self::mapExtractedDataToQueryParams($businessQuery, $criteria, $userId);
            
            // Ejecutar query
            $result = \common\queries\BusinessQueryRegistry::executeQuery($businessQuery, $params);
            
            // Formatear resultado
            $formattedResult = [];
            if (is_array($result)) {
                foreach ($result as $item) {
                    if (is_object($item) && method_exists($item, 'toArray')) {
                        $formattedResult[] = $item->toArray();
                    } elseif (is_array($item)) {
                        $formattedResult[] = $item;
                    } else {
                        $formattedResult[] = ['data' => $item];
                    }
                }
            } else {
                $formattedResult = $result;
            }
            
            return [
                'success' => true,
                'explanation' => $businessQuery['description'] ?? 'Resultado de la consulta',
                'data' => $formattedResult,
                'query_type' => 'business_query',
                'query_id' => $businessQuery['id'] ?? null,
            ];
        } catch (\Exception $e) {
            Yii::error("Error ejecutando business query: " . $e->getMessage(), 'universal-query-agent');
            return [
                'success' => false,
                'error' => 'Error al ejecutar la consulta: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Mapear datos extraídos a parámetros de business query
     */
    private static function mapExtractedDataToQueryParams($businessQuery, $criteria, $userId = null)
    {
        $params = [];
        $extractedData = $criteria['extracted_data'] ?? [];
        $searchKeywords = $criteria['search_keywords'] ?? [];
        
        foreach ($businessQuery['parameters'] ?? [] as $param) {
            $paramName = $param['name'];
            $value = null;
            
            // Mapeo inteligente según nombre del parámetro
            if (stripos($paramName, 'id_efector') !== false || stripos($paramName, 'efector') !== false) {
                // Buscar en extracted_data o usar efector actual del usuario
                $value = $extractedData['id_efector'] ?? Yii::$app->user->getIdEfector();
            } elseif (stripos($paramName, 'especialidad') !== false) {
                // Buscar especialidad en keywords o names extraídos
                // Palabras clave comunes de especialidades
                $especialidadesKeywords = [
                    'odontolog' => 'odontolog',
                    'cardiolog' => 'cardiolog',
                    'pediatra' => 'pediatra',
                    'ginecolog' => 'ginecolog',
                    'traumatolog' => 'traumatolog',
                    'dermatolog' => 'dermatolog',
                    'oftalmolog' => 'oftalmolog',
                    'neurolog' => 'neurolog',
                ];
                
                // Buscar en keywords
                foreach ($searchKeywords as $keyword) {
                    $keywordLower = strtolower($keyword);
                    foreach ($especialidadesKeywords as $espKey => $espValue) {
                        if (stripos($keywordLower, $espKey) !== false) {
                            $value = $espValue;
                            break 2;
                        }
                    }
                }
                
                // Si no se encontró, buscar en names extraídos
                if ($value === null && isset($extractedData['raw']['names'])) {
                    foreach ($extractedData['raw']['names'] as $name) {
                        $nameLower = strtolower($name);
                        foreach ($especialidadesKeywords as $espKey => $espValue) {
                            if (stripos($nameLower, $espKey) !== false) {
                                $value = $espValue;
                                break 2;
                            }
                        }
                    }
                }
            } elseif (stripos($paramName, 'limit') !== false) {
                $value = $param['default'] ?? 10;
            } elseif (stripos($paramName, 'id') !== false) {
                // Buscar IDs en extracted_data
                if (isset($extractedData['dni'])) {
                    $value = $extractedData['dni'];
                } elseif (isset($extractedData['raw']['identifiers'])) {
                    $value = $extractedData['raw']['identifiers'][0] ?? null;
                }
            }
            
            // Si tiene valor por defecto y no se encontró valor, usar el default
            if ($value === null && isset($param['default'])) {
                $value = $param['default'];
            }
            
            // Solo agregar si tiene valor o es requerido
            if ($value !== null || !empty($param['required'])) {
                $params[$paramName] = $value;
            }
        }
        
        return $params;
    }

    /**
     * Fase 2: Buscar acciones usando criterios (búsqueda local inteligente)
     * Devuelve SOLO UNA acción: la que tenga el mejor score
     * @param array $criteria
     * @param int|null $userId
     * @return array|null Una sola acción (la mejor) o null si no hay match. 
     *                    Excepción: si query_type === 'list_all', devuelve array de todas las acciones.
     */
    private static function findActionsByCriteria($criteria, $userId = null)
    {
        // Obtener todas las acciones disponibles para el usuario (ya filtradas por permisos)
        $allActions = ActionMappingService::getAvailableActionsForUser($userId);
        
        // Log para debugging
        $currentUserId = $userId ?? Yii::$app->user->id ?? 'no-autenticado';
        Yii::info("UniversalQueryAgent::findActionsByCriteria - userId: {$currentUserId}, query_type: {$criteria['query_type']}, acciones encontradas: " . count($allActions), 'universal-query-agent');
        
        if (empty($allActions)) {
            Yii::warning("UniversalQueryAgent::findActionsByCriteria - No se encontraron acciones para userId: {$currentUserId}", 'universal-query-agent');
            return null;
        }

        // Caso especial: listar todos los permisos
        if ($criteria['query_type'] === 'list_all') {
            return $allActions;
        }

        // Caso especial: búsqueda por DNI
        if (!empty($criteria['extracted_data']['dni'])) {
            // Buscar acciones relacionadas con búsqueda de personas (devuelve solo la mejor)
            return self::findPersonSearchActions($allActions, $criteria['extracted_data']['dni']);
        }

        // Búsqueda semántica usando scoring mejorado con metadatos
        $scoredActions = [];
        $debugScores = []; // Para debugging
        
        foreach ($allActions as $action) {
            $score = self::calculateSemanticScore($action, $criteria);
            
            // Log detallado para debugging (solo para acciones de turnos)
            if (stripos($action['controller'] ?? '', 'turno') !== false || 
                stripos($action['route'] ?? '', 'turno') !== false) {
                $debugScores[] = [
                    'action_id' => $action['action_id'] ?? 'N/A',
                    'controller' => $action['controller'] ?? 'N/A',
                    'action' => $action['action'] ?? 'N/A',
                    'route' => $action['route'] ?? 'N/A',
                    'entity' => $action['entity'] ?? 'N/A',
                    'tags' => $action['tags'] ?? [],
                    'keywords' => $action['keywords'] ?? [],
                    'score' => $score,
                ];
            }
            
            // Incluir acciones con score > 0, o si tienen coincidencias mínimas
            if ($score > 0) {
                $scoredActions[] = [
                    'action' => $action,
                    'score' => $score,
                ];
            }
        }
        
        // Log de debugging si hay acciones de turnos
        if (!empty($debugScores)) {
            Yii::info("UniversalQueryAgent::findActionsByCriteria - Scores de acciones de turnos: " . json_encode($debugScores, JSON_UNESCAPED_UNICODE), 'universal-query-agent');
        }
        
        // Log del total de acciones con score > 0
        Yii::info("UniversalQueryAgent::findActionsByCriteria - Acciones con score > 0: " . count($scoredActions) . " de " . count($allActions), 'universal-query-agent');

        // Ordenar por score (mayor a menor)
        usort($scoredActions, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Log de la mejor acción seleccionada (después de ordenar)
        if (!empty($scoredActions)) {
            $bestAction = $scoredActions[0];
            Yii::info("UniversalQueryAgent::findActionsByCriteria - Mejor acción seleccionada: {$bestAction['action']['route']} con score: {$bestAction['score']}", 'universal-query-agent');
        } else {
            Yii::info("UniversalQueryAgent::findActionsByCriteria - No se encontró ninguna acción con score > 0", 'universal-query-agent');
        }

        // Retornar SOLO la acción con el mejor score (un solo elemento, no array)
        if (!empty($scoredActions)) {
            return $scoredActions[0]['action'];
        }

        return null;
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
            
            // Bonus si la keyword coincide con el nombre del controlador
            if (stripos($action['controller'], $keyword) !== false) {
                $score += 3.0; // Bonus adicional por coincidencia en controlador
            }
        }

        // Score por entity (muy alto si coincide)
        if (!empty($criteria['entity_type']) && !empty($action['entity'])) {
            if (strtolower($action['entity']) === strtolower($criteria['entity_type'])) {
                $score += 15.0; // Bonus muy alto por coincidencia de entity
            }
        }
        
        // Bonus adicional si el controlador coincide con entity_type
        if (!empty($criteria['entity_type'])) {
            $entityTypeLower = strtolower($criteria['entity_type']);
            $controllerLower = strtolower($action['controller'] ?? '');
            // Si entity_type es "Turnos" y controller es "turnos", dar bonus
            if ($entityTypeLower === $controllerLower || 
                stripos($controllerLower, $entityTypeLower) !== false ||
                stripos($entityTypeLower, $controllerLower) !== false) {
                $score += 12.0; // Bonus alto por coincidencia de controlador
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
            $hint = strtolower(trim($hint));
            if (stripos($actionName, $hint) !== false) {
                $score += 4.0;
            }
            if (stripos($actionText, $hint) !== false) {
                $score += 2.0;
            }
            // También buscar en tags y keywords
            if (!empty($action['tags']) && is_array($action['tags'])) {
                foreach ($action['tags'] as $tag) {
                    if (stripos(strtolower($tag), $hint) !== false) {
                        $score += 3.0;
                        break;
                    }
                }
            }
            if (!empty($action['keywords']) && is_array($action['keywords'])) {
                foreach ($action['keywords'] as $keyword) {
                    if (stripos(strtolower($keyword), $hint) !== false) {
                        $score += 3.0;
                        break;
                    }
                }
            }
        }

        // Bonus por query_type
        $queryType = $criteria['query_type'] ?? 'unknown';
        $queryTypeMapping = [
            'list' => ['index', 'list', 'listar', 'ver todos'],
            'search' => ['search', 'buscar', 'find', 'filter'],
            'create' => ['create', 'crear', 'new', 'nuevo', 'crearmi'],
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
        
        // Bonus adicional por operation_hints (palabras como "crear", "agendar", "solicitar")
        $operationHints = $criteria['operation_hints'] ?? [];
        foreach ($operationHints as $hint) {
            $hint = strtolower(trim($hint));
            if (stripos($actionName, $hint) !== false) {
                $score += 4.0;
            }
            // También buscar en tags y keywords
            if (!empty($action['tags']) && is_array($action['tags'])) {
                foreach ($action['tags'] as $tag) {
                    if (stripos(strtolower($tag), $hint) !== false) {
                        $score += 3.0;
                        break;
                    }
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
    /**
     * Buscar acciones relacionadas con búsqueda de personas por DNI
     * Devuelve SOLO UNA acción: la que tenga el mejor score
     * @param array $allActions
     * @param string $dni
     * @return array|null Una sola acción (la mejor) o null si no hay match
     */
    private static function findPersonSearchActions($allActions, $dni)
    {
        $scoredActions = [];
        
        foreach ($allActions as $action) {
            $score = 0.0;
            $actionText = strtolower(
                $action['display_name'] . ' ' . 
                $action['description'] . ' ' . 
                $action['controller']
            );
            
            // Calcular score para acciones relacionadas con personas
            if (stripos($actionText, 'persona') !== false) {
                $score += 10.0;
            }
            if (stripos($action['controller'], 'persona') !== false) {
                $score += 15.0; // Bonus alto por coincidencia en controlador
            }
            if (!empty($action['entity']) && stripos($action['entity'], 'Pacientes') !== false) {
                $score += 12.0;
            }
            if (stripos($actionText, 'buscar') !== false || stripos($actionText, 'search') !== false) {
                $score += 5.0; // Bonus por ser acción de búsqueda
            }
            
            if ($score > 0) {
                $scoredActions[] = [
                    'action' => $action,
                    'score' => $score,
                ];
            }
        }

        // Ordenar por score (mayor a menor)
        usort($scoredActions, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // Retornar SOLO la acción con el mejor score (un solo elemento, no array)
        if (!empty($scoredActions)) {
            return $scoredActions[0]['action'];
        }

        return null;
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
        
        // Analizar parámetros de la acción principal si existe
        $actionAnalysis = null;
        if (!empty($actions)) {
            $primaryAction = $actions[0];
            $actionAnalysis = ActionParameterAnalyzer::analyzeActionParameters(
                $primaryAction,
                $criteria['extracted_data'] ?? [],
                $userId
            );
        }
        
        $response = [
            'success' => true,
            'explanation' => $parsed['explanation'] ?? 'Encontré ' . count($actions) . ' acciones relacionadas con tu consulta.',
            'actions' => $formattedActions,
            'count' => $parsed['count'] ?? count($actions),
            'query_type' => $criteria['query_type'] ?? 'unknown',
        ];
        
        // Agregar análisis de parámetros si existe
        if ($actionAnalysis) {
            $response['action_analysis'] = $actionAnalysis;
            $response['needs_user_input'] = !$actionAnalysis['ready_to_execute'];
        }
        
        return $response;
    }

    /**
     * Buscar acción por action_id
     * @param string $actionId
     * @param int|null $userId
     * @return array|null
     */
    private static function findActionById($actionId, $userId = null)
    {
        // Obtener todas las acciones disponibles para el usuario
        $allActions = ActionMappingService::getAvailableActionsForUser($userId);
        
        foreach ($allActions as $action) {
            if (($action['action_id'] ?? '') === $actionId) {
                return $action;
            }
        }
        
        return null;
    }

    /**
     * Intentar encontrar acción por matching semántico (ANTES del LLM)
     * @param string $userQuery
     * @param int|null $userId
     * @return array|null Acción encontrada o null
     */
    private static function findActionBySemanticMatch($userQuery, $userId = null)
    {
        if (empty($userQuery)) {
            return null;
        }

        $queryLower = strtolower(trim($userQuery));
        
        // Obtener todas las acciones disponibles para el usuario
        $allActions = ActionMappingService::getAvailableActionsForUser($userId);
        
        $bestMatch = null;
        $bestScore = 0;
        
        foreach ($allActions as $action) {
            $score = 0;
            
            // Matching exacto en keywords
            if (!empty($action['keywords'])) {
                foreach ($action['keywords'] as $keyword) {
                    $keywordLower = strtolower(trim($keyword));
                    if ($queryLower === $keywordLower) {
                        $score += 20; // Coincidencia exacta
                    } elseif (stripos($queryLower, $keywordLower) !== false) {
                        $score += 10; // Coincidencia parcial
                    }
                }
            }
            
            // Matching en synonyms
            if (!empty($action['synonyms'])) {
                foreach ($action['synonyms'] as $synonym) {
                    $synonymLower = strtolower(trim($synonym));
                    if ($queryLower === $synonymLower) {
                        $score += 15;
                    } elseif (stripos($queryLower, $synonymLower) !== false) {
                        $score += 8;
                    }
                }
            }
            
            // Matching en tags
            if (!empty($action['tags'])) {
                foreach ($action['tags'] as $tag) {
                    $tagLower = strtolower(trim($tag));
                    if (stripos($queryLower, $tagLower) !== false) {
                        $score += 5;
                    }
                }
            }
            
            // Matching en display_name y description
            $displayNameLower = strtolower($action['display_name'] ?? '');
            $descriptionLower = strtolower($action['description'] ?? '');
            if (stripos($displayNameLower, $queryLower) !== false || 
                stripos($queryLower, $displayNameLower) !== false) {
                $score += 12;
            }
            if (stripos($descriptionLower, $queryLower) !== false) {
                $score += 6;
            }
            
            // Matching en action_id (si el query contiene el action_id)
            $actionId = strtolower($action['action_id'] ?? '');
            if ($actionId && stripos($queryLower, $actionId) !== false) {
                $score += 25; // Muy alto si coincide con action_id
            }
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $action;
            }
        }
        
        // Solo retornar si el score es suficientemente alto (umbral: 15)
        if ($bestScore >= 15) {
            return $bestMatch;
        }
        
        return null;
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
                    'entity' => $actions['entity'] ?? null,
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
                    'entity' => $action['entity'] ?? null,
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
     * Ejecutar una acción directamente y devolver su resultado
     * @param array $action
     * @param array $params
     * @param int|null $userId
     * @return array
     */
    private static function executeActionDirectly($action, $params, $userId)
    {
        $route = $action['route'] ?? null;
        
        // Si no tiene ruta, es una acción especial del sistema
        if (empty($route)) {
            return [
                'success' => false,
                'error' => 'Esta acción no puede ejecutarse directamente',
            ];
        }
        
        // Parsear ruta: /frontend/efectores/indexuserefector
        $routeParts = explode('/', trim($route, '/'));
        
        // Obtener controlador y acción
        $controllerName = null;
        $actionName = null;
        
        if (count($routeParts) >= 3) {
            // Formato: /frontend/efectores/indexuserefector
            $controllerName = $routeParts[count($routeParts) - 2];
            $actionName = $routeParts[count($routeParts) - 1];
        } elseif (count($routeParts) >= 2) {
            // Formato: /efectores/indexuserefector
            $controllerName = $routeParts[0];
            $actionName = $routeParts[1];
        }
        
        if (!$controllerName || !$actionName) {
            return [
                'success' => false,
                'error' => 'Ruta inválida: ' . $route,
            ];
        }
        
        // Crear instancia del controlador
        $controllerClass = 'frontend\\controllers\\' . ucfirst($controllerName) . 'Controller';
        
        if (!class_exists($controllerClass)) {
            return [
                'success' => false,
                'error' => 'Controlador no encontrado: ' . $controllerClass,
            ];
        }
        
        try {
            // Crear instancia del controlador
            $controller = new $controllerClass('api', Yii::$app);
            
            // Verificar que el método existe
            $methodName = 'action' . ucfirst($actionName);
            if (!method_exists($controller, $methodName)) {
                return [
                    'success' => false,
                    'error' => 'Método no encontrado: ' . $methodName,
                ];
            }
            
            // Usar reflexión para ejecutar el método y capturar variables locales
            $reflection = new \ReflectionMethod($controller, $methodName);
            $reflection->setAccessible(true);
            
            // Ejecutar la acción y capturar el resultado
            ob_start();
            $result = $reflection->invokeArgs($controller, $params);
            $output = ob_get_clean();
            
            // Si la acción retorna un array, devolverlo directamente
            if (is_array($result)) {
                return [
                    'success' => true,
                    'data' => $result,
                ];
            }
            
            // Si retorna una vista (string), intentar extraer datos ejecutando la lógica nuevamente
            if (is_string($result)) {
                // Ejecutar la lógica del controlador pero interceptar el DataProvider
                // Usar una variable estática temporal para capturar el dataProvider
                $capturedDataProvider = null;
                
                try {
                    // Crear una nueva instancia y ejecutar la lógica
                    $tempController = new $controllerClass('api', Yii::$app);
                    
                    // Interceptar el método render para capturar el dataProvider
                    // Esto es complejo, así que mejor ejecutar la lógica directamente
                    
                    // Para acciones específicas conocidas, ejecutar la lógica directamente
                    if ($controllerName === 'efectores' && $actionName === 'indexuserefector') {
                        $searchModel = new \common\models\busquedas\EfectorBusqueda();
                        $array_efectores = Yii::$app->user->getEfectores() ?? [];
                        $dataProvider = $searchModel->search(['EfectorBusqueda' => ['efectores' => array_keys($array_efectores)]]);
                        
                        // Extraer datos del DataProvider
                        $models = $dataProvider->getModels();
                        $totalCount = $dataProvider->getTotalCount();
                        
                        $formattedData = [];
                        foreach ($models as $model) {
                            $formattedData[] = [
                                'id_efector' => $model->id_efector,
                                'nombre' => $model->nombre,
                                'codigo_sisa' => $model->codigo_sisa,
                                'domicilio' => $model->domicilio,
                                'telefono' => $model->telefono,
                                'tipologia' => $model->tipologia,
                                'dependencia' => $model->dependencia,
                            ];
                        }
                        
                        return [
                            'success' => true,
                            'data' => [
                                'efectores' => $formattedData,
                                'total' => $totalCount,
                            ],
                        ];
                    }
                    
                } catch (\Exception $e) {
                    Yii::error("Error extrayendo datos de DataProvider: " . $e->getMessage(), 'universal-query-agent');
                }
                
                // Si no se pudo extraer datos, devolver el HTML
                return [
                    'success' => true,
                    'data' => [
                        'html' => $result,
                        'output' => $output,
                        'message' => 'La acción retornó una vista HTML. Los datos no pudieron extraerse automáticamente.',
                    ],
                ];
            }
            
            // Si no retorna nada o retorna algo inesperado
            return [
                'success' => true,
                'data' => [
                    'message' => 'Acción ejecutada correctamente',
                    'output' => $output,
                ],
            ];
            
        } catch (\Exception $e) {
            Yii::error("Error ejecutando acción {$action['action_id']}: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'universal-query-agent');
            return [
                'success' => false,
                'error' => 'Error al ejecutar la acción: ' . $e->getMessage(),
            ];
        }
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
                    'entity' => 'Personas',
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
     * Obtener entidades disponibles desde acciones descubiertas
     * @return array
     */
    private static function getAvailableEntities()
    {
        $actions = ActionDiscoveryService::discoverAllActions();
        $entities = [];
        
        foreach ($actions as $action) {
            if (!empty($action['entity']) && is_string($action['entity'])) {
                $entities[$action['entity']] = true;
            }
        }
        
        return array_keys($entities);
    }

    /**
     * Obtener metadatos del sistema de forma dinámica
     * @return string
     */
    private static function getSystemMetadata()
    {
        try {
            // Obtener información sobre modelos disponibles
            $models = ModelDiscoveryService::discoverAllModels();
            
            // Extraer tipos de entidades comunes
            $entityTypes = [];
            $commonAttributes = [];
            
            foreach (array_slice($models, 0, 30) as $model) { // Limitar para no saturar
                $entityTypes[] = $model['name'];
                
                // Agregar atributos comunes
                foreach (array_slice($model['attributes'] ?? [], 0, 5) as $attr) {
                    if (is_array($attr) && isset($attr['name']) && !in_array($attr['name'], $commonAttributes)) {
                        $commonAttributes[] = $attr['name'];
                    }
                }
            }
            
            $metadata = "Tipos de entidades disponibles: " . implode(', ', array_slice($entityTypes, 0, 20)) . "\n";
            $metadata .= "Atributos comunes: " . implode(', ', array_slice($commonAttributes, 0, 15));
            
            return $metadata;
        } catch (\Exception $e) {
            Yii::error("Error obteniendo metadatos del sistema: " . $e->getMessage(), 'universal-query-agent');
            return "Sistema de gestión de salud con múltiples entidades y relaciones.";
        }
    }

    /**
     * Obtener información de business queries disponibles
     * @return string
     */
    private static function getBusinessQueriesInfo()
    {
        try {
            $queries = \common\queries\BusinessQueryRegistry::getAllQueries();
            
            if (empty($queries)) {
                return '';
            }
            
            $info = "\nConsultas de negocio disponibles (ranking, métricas, agregaciones):\n";
            
            foreach (array_slice($queries, 0, 10) as $query) { // Limitar para no saturar
                if (!($query['active'] ?? true)) {
                    continue;
                }
                
                $keywords = implode(', ', array_slice($query['keywords'] ?? [], 0, 5));
                $info .= "- {$query['description']} (keywords: {$keywords})\n";
            }
            
            return $info;
        } catch (\Exception $e) {
            Yii::error("Error obteniendo business queries: " . $e->getMessage(), 'universal-query-agent');
            return '';
        }
    }

    /**
     * Normalizar datos extraídos para compatibilidad con código existente
     * @param array $extractedData
     * @return array
     */
    private static function normalizeExtractedData($extractedData)
    {
        $normalized = [];
        
        // Si viene en formato antiguo (dni, fecha, nombre), mantenerlo
        if (isset($extractedData['dni']) || isset($extractedData['fecha']) || isset($extractedData['nombre'])) {
            $normalized = $extractedData;
        } else {
            // Mapear identificadores a DNI si parece ser un documento
            if (isset($extractedData['identifiers']) && is_array($extractedData['identifiers'])) {
                foreach ($extractedData['identifiers'] as $identifier) {
                    // Si es un número de 7-8 dígitos, probablemente es un DNI
                    if (is_numeric($identifier) && strlen((string)$identifier) >= 7 && strlen((string)$identifier) <= 8) {
                        $normalized['dni'] = $identifier;
                        break;
                    }
                }
            }
            
            // Mapear fechas
            if (isset($extractedData['dates']) && is_array($extractedData['dates']) && !empty($extractedData['dates'])) {
                $normalized['fecha'] = $extractedData['dates'][0];
            }
            
            // Mapear nombres
            if (isset($extractedData['names']) && is_array($extractedData['names']) && !empty($extractedData['names'])) {
                $normalized['nombre'] = implode(' ', $extractedData['names']);
            }
            
            // Mantener datos originales también para referencia
            $normalized['raw'] = $extractedData;
        }
        
        return $normalized;
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

    /**
     * Validar si hay id_servicio en los criterios y si es válido
     * @param array $criteria
     * @return array
     */
    private static function validateServicioInCriteria($criteria)
    {
        $result = [
            'has_servicio' => false,
            'id_servicio' => null,
            'servicio_name' => null,
            'is_valid' => false,
        ];

        // Buscar id_servicio en extracted_data
        $extractedData = $criteria['extracted_data'] ?? [];
        
        // Buscar id_servicio directamente
        if (isset($extractedData['id_servicio'])) {
            $idServicio = $extractedData['id_servicio'];
            if (is_numeric($idServicio)) {
                $result['has_servicio'] = true;
                $result['id_servicio'] = (int)$idServicio;
                $result['is_valid'] = self::validateServicioId($result['id_servicio']);
                if ($result['is_valid']) {
                    $servicio = \common\models\Servicio::findOne($result['id_servicio']);
                    if ($servicio) {
                        $result['servicio_name'] = $servicio->nombre;
                    }
                }
            }
        }
        
        // Buscar servicio por nombre y convertirlo a id_servicio
        if (!$result['has_servicio']) {
            $servicioName = null;
            
            // Buscar en extracted_data
            if (isset($extractedData['servicio'])) {
                $servicioName = $extractedData['servicio'];
            } elseif (isset($extractedData['servicio_actual'])) {
                $servicioName = $extractedData['servicio_actual'];
            } elseif (isset($extractedData['raw']['servicio'])) {
                $servicioName = $extractedData['raw']['servicio'];
            } elseif (isset($extractedData['raw']['names'])) {
                // Buscar nombres que puedan ser servicios
                foreach ($extractedData['raw']['names'] as $name) {
                    $servicioId = self::findServicioByName($name);
                    if ($servicioId !== null) {
                        $result['has_servicio'] = true;
                        $result['id_servicio'] = $servicioId;
                        $result['is_valid'] = true;
                        $servicio = \common\models\Servicio::findOne($servicioId);
                        if ($servicio) {
                            $result['servicio_name'] = $servicio->nombre;
                        }
                        break;
                    }
                }
            }
            
            // Si encontramos un nombre de servicio, buscar su ID
            if (!$result['has_servicio'] && $servicioName !== null) {
                if (is_numeric($servicioName)) {
                    $result['has_servicio'] = true;
                    $result['id_servicio'] = (int)$servicioName;
                    $result['is_valid'] = self::validateServicioId($result['id_servicio']);
                } else {
                    $servicioId = self::findServicioByName($servicioName);
                    if ($servicioId !== null) {
                        $result['has_servicio'] = true;
                        $result['id_servicio'] = $servicioId;
                        $result['is_valid'] = true;
                        $servicio = \common\models\Servicio::findOne($servicioId);
                        if ($servicio) {
                            $result['servicio_name'] = $servicio->nombre;
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Validar si un id_servicio existe en la base de datos
     * @param int $idServicio
     * @return bool
     */
    private static function validateServicioId($idServicio)
    {
        try {
            $servicio = \common\models\Servicio::findOne($idServicio);
            return $servicio !== null;
        } catch (\Exception $e) {
            Yii::error("Error validando id_servicio {$idServicio}: " . $e->getMessage(), 'universal-query-agent');
            return false;
        }
    }

    /**
     * Buscar servicio por nombre (similar a ActionParameterAnalyzer)
     * @param string $nombre
     * @return int|null
     */
    private static function findServicioByName($nombre)
    {
        if (empty($nombre) || !is_string($nombre)) {
            return null;
        }

        try {
            // Normalizar nombre
            $nombreNormalizado = trim($nombre);
            
            // Buscar en la base de datos
            $servicio = \common\models\Servicio::find()
                ->where(['nombre' => $nombreNormalizado])
                ->one();
            
            if ($servicio) {
                return (int)$servicio->id_servicio;
            }
            
            // Intentar búsqueda con LIKE (case insensitive)
            $servicio = \common\models\Servicio::find()
                ->where(['LIKE', 'nombre', $nombreNormalizado])
                ->one();
            
            if ($servicio) {
                return (int)$servicio->id_servicio;
            }
        } catch (\Exception $e) {
            Yii::error("Error buscando servicio por nombre '{$nombre}': " . $e->getMessage(), 'universal-query-agent');
        }

        return null;
    }

    /**
     * Verificar si una acción requiere id_servicio como parámetro
     * @param array $action
     * @return bool
     */
    private static function actionRequiresServicio($action)
    {
        $parameters = $action['parameters'] ?? [];
        
        foreach ($parameters as $param) {
            $paramName = strtolower($param['name'] ?? '');
            // Verificar si el parámetro es id_servicio, servicio_actual, o similar
            if (stripos($paramName, 'servicio') !== false || 
                stripos($paramName, 'id_servicio') !== false) {
                // Si es requerido, definitivamente necesita servicio
                if (!empty($param['required'])) {
                    return true;
                }
                // Si no es requerido pero está presente, también considerar
                return true;
            }
        }
        
        return false;
    }
}
