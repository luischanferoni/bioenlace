<?php

namespace common\components;

use Yii;
use yii\httpclient\Client;

/**
 * Extensión de IAManager para procesar consultas administrativas
 * Utiliza el sistema de acciones dinámicas para proporcionar contexto a la IA
 */
class AdminQueryIAManager
{
    /**
     * Procesar consulta administrativa con contexto de acciones disponibles
     * @param string $userQuery Consulta del usuario
     * @param int|null $userId ID del usuario (null para usuario actual)
     * @return array Respuesta con explicación y acciones sugeridas
     */
    public static function processAdminQuery($userQuery, $userId = null)
    {
        if (empty($userQuery)) {
            return [
                'success' => false,
                'error' => 'La consulta no puede estar vacía',
            ];
        }

        try {
            // Obtener acciones disponibles para el usuario
            $availableActions = ActionMappingService::getAvailableActionsForUser($userId);
            
            // Generar descripción de acciones para el prompt
            $actionsDescription = ActionMappingService::generateActionsDescriptionForIA($availableActions);
            
            // Construir prompt para la IA
            $prompt = self::buildPrompt($userQuery, $actionsDescription, $availableActions);
            
            // Llamar a IAManager para obtener respuesta
            $iaResponse = self::callIA($prompt);
            
            // Parsear respuesta de la IA
            $parsedResponse = self::parseIAResponse($iaResponse, $availableActions);
            
            return [
                'success' => true,
                'explanation' => $parsedResponse['explanation'],
                'actions' => $parsedResponse['actions'],
            ];
            
        } catch (\Exception $e) {
            Yii::error("Error procesando consulta administrativa: " . $e->getMessage(), 'admin-query-ia');
            return [
                'success' => false,
                'error' => 'Error al procesar la consulta. Por favor, intente nuevamente.',
            ];
        }
    }

    /**
     * Construir prompt para la IA
     * @param string $userQuery
     * @param string $actionsDescription
     * @param array $availableActions
     * @return string
     */
    private static function buildPrompt($userQuery, $actionsDescription, $availableActions)
    {
        $actionsJSON = json_encode(ActionMappingService::generateActionsJSONForIA($availableActions), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $prompt = <<<PROMPT
Eres un asistente virtual para un sistema de gestión hospitalaria. Tu tarea es ayudar a usuarios administrativos a encontrar las acciones correctas según sus consultas.

{$actionsDescription}

Consulta del usuario: "{$userQuery}"

Instrucciones:
1. Analiza la consulta del usuario y determina qué acción(es) del sistema sería(n) más apropiada(s) para resolver su necesidad.
2. Proporciona una explicación breve y clara (2-3 oraciones) de cómo proceder.
3. Identifica las acciones relevantes de la lista anterior y proporciona sus rutas exactas.

Responde ÚNICAMENTE con un JSON válido en el siguiente formato:
{
  "explanation": "Explicación breve de cómo proceder",
  "actions": [
    {
      "route": "ruta/exacta/de/la/accion",
      "name": "Nombre descriptivo de la acción",
      "description": "Por qué esta acción es relevante"
    }
  ]
}

IMPORTANTE:
- Solo incluye acciones que estén en la lista de acciones disponibles.
- Usa las rutas exactas como aparecen en la lista.
- Si ninguna acción es relevante, devuelve un array vacío en "actions".
- La explicación debe ser clara y en español.
PROMPT;

        return $prompt;
    }

    /**
     * Llamar a la IA usando IAManager
     * @param string $prompt
     * @return string|null
     */
    private static function callIA($prompt)
    {
        try {
            // Obtener configuración del proveedor
            $proveedorIA = IAManager::getProveedorIA();
            
            // Asignar el prompt
            IAManager::asignarPromptAConfiguracion($proveedorIA, $prompt);
            
            // Realizar la petición
            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($proveedorIA['endpoint'])
                ->addHeaders($proveedorIA['headers'])
                ->setContent(json_encode($proveedorIA['payload']))
                ->send();

            if ($response->isOk) {
                $responseData = IAManager::procesarRespuestaProveedor($response, $proveedorIA['tipo']);
                return $responseData;
            } else {
                Yii::error("Error en respuesta de IA: " . $response->getStatusCode(), 'admin-query-ia');
                return null;
            }
        } catch (\Exception $e) {
            Yii::error("Error llamando a IA: " . $e->getMessage(), 'admin-query-ia');
            return null;
        }
    }

    /**
     * Parsear respuesta de la IA
     * @param string|null $iaResponse
     * @param array $availableActions
     * @return array
     */
    private static function parseIAResponse($iaResponse, $availableActions)
    {
        if (empty($iaResponse)) {
            return [
                'explanation' => 'No se pudo procesar la consulta. Por favor, intente reformular su pregunta.',
                'actions' => [],
            ];
        }

        // Intentar extraer JSON de la respuesta
        $json = self::extractJSONFromResponse($iaResponse);
        
        if (!$json) {
            // Si no hay JSON válido, intentar interpretar la respuesta como texto
            return [
                'explanation' => $iaResponse,
                'actions' => self::extractActionsFromText($iaResponse, $availableActions),
            ];
        }

        // Validar estructura del JSON
        if (!isset($json['explanation'])) {
            $json['explanation'] = 'Respuesta procesada correctamente.';
        }

        if (!isset($json['actions']) || !is_array($json['actions'])) {
            $json['actions'] = [];
        }

        // Validar y filtrar acciones sugeridas
        $validatedActions = [];
        foreach ($json['actions'] as $suggestedAction) {
            if (isset($suggestedAction['route'])) {
                // Verificar que la ruta existe en las acciones disponibles
                $found = false;
                foreach ($availableActions as $availableAction) {
                    if ($availableAction['route'] === $suggestedAction['route']) {
                        $validatedActions[] = [
                            'route' => $availableAction['route'],
                            'name' => $suggestedAction['name'] ?? $availableAction['display_name'],
                            'description' => $suggestedAction['description'] ?? $availableAction['description'],
                        ];
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    Yii::warning("IA sugirió ruta no válida: " . $suggestedAction['route'], 'admin-query-ia');
                }
            }
        }

        return [
            'explanation' => $json['explanation'],
            'actions' => $validatedActions,
        ];
    }

    /**
     * Extraer JSON de la respuesta de la IA
     * @param string $response
     * @return array|null
     */
    private static function extractJSONFromResponse($response)
    {
        // Buscar JSON en la respuesta (puede estar envuelto en texto)
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $jsonStr = $matches[0];
            $json = json_decode($jsonStr, true);
            
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
     * Extraer acciones desde texto (fallback)
     * @param string $text
     * @param array $availableActions
     * @return array
     */
    private static function extractActionsFromText($text, $availableActions)
    {
        $actions = [];
        
        // Buscar rutas mencionadas en el texto
        foreach ($availableActions as $action) {
            if (stripos($text, $action['route']) !== false || 
                stripos($text, $action['controller']) !== false ||
                stripos($text, $action['display_name']) !== false) {
                $actions[] = [
                    'route' => $action['route'],
                    'name' => $action['display_name'],
                    'description' => $action['description'],
                ];
            }
        }

        return $actions;
    }
}

