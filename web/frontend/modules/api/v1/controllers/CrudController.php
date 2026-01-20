<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use frontend\modules\api\v1\controllers\BaseController;
use yii\helpers\Inflector;

class CrudController extends BaseController
{
    public $modelClass = ''; // No usamos ActiveController para este endpoint
    public $enableCsrfValidation = false; // Deshabilitar CSRF para API

    /**
     * Configurar behaviors para permitir sesiones web además de Bearer token
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        // Permitir acceso sin autenticación Bearer si hay sesión activa
        // Los métodos actionProcessQuery y actionExecuteAction verificarán manualmente la autenticación
        $behaviors['authenticator']['except'] = ['options', 'process-query', 'execute-action'];
        
        // CORS ya está configurado en BaseController, no es necesario redefinirlo
        
        return $behaviors;
    }

    /**
     * Procesar consulta en lenguaje natural usando UniversalQueryAgent
     * 
     * Este endpoint procesa consultas en lenguaje natural y devuelve acciones relevantes
     * del sistema que el usuario tiene permitido realizar.
     * 
     * Ejemplos de consultas:
     * - "listame mis licencias"
     * - "29486884" (búsqueda por DNI)
     * - "cuántos consultas voy atendiendo este mes?"
     * - "qué puedo hacer?"
     * 
     * @return array Respuesta con acciones encontradas o error
     */
    public function actionProcessQuery()
    {
        // Verificar autenticación usando el método de BaseController
        $authError = $this->requerirAutenticacion();
        if ($authError !== null) {
            return $authError;
        }
        
        // Obtener el userId usando el método de BaseController
        $auth = $this->verificarAutenticacion();
        $userId = $auth['userId'];

        $query = Yii::$app->request->post('query');
        $actionId = Yii::$app->request->post('action_id'); // Opcional: para búsqueda directa por ID
        
        if (empty($query) && empty($actionId)) {
            return $this->error('La consulta o action_id es requerido', null, 400);
        }

        try {
            // Procesar consulta usando UniversalQueryAgent (implementación genérica y mejorada)
            // Si viene action_id, se buscará primero por ID, luego por matching semántico, y finalmente por LLM
            $result = \common\components\UniversalQueryAgent::processQuery($query, $userId, $actionId);
            
            // Asegurar que el resultado tenga el formato correcto
            if (isset($result['success'])) {
                return $result;
            }
            
            // Si no tiene formato estándar, envolverlo
            return $this->success($result);
        } catch (\Exception $e) {
            Yii::error("Error procesando consulta: " . $e->getMessage(), 'api-crud-controller');
            return $this->error('Error al procesar la consulta. Por favor, intente nuevamente.', null, 500);
        }
    }

    /**
     * Ejecutar una acción específica por su action_id
     * 
     * GET: Devuelve el form_config (wizard) para la acción sin ejecutarla
     * POST: Ejecuta la acción con los parámetros proporcionados
     * 
     * GET /api/v1/crud/execute-action?action_id=...&param1=value1&param2=value2
     * POST /api/v1/crud/execute-action
     * Body: {
     *   "action_id": "efectores.indexuserefector",
     *   "params": {} // opcional
     * }
     * 
     * @return array
     */
    public function actionExecuteAction()
    {
        // Verificar autenticación
        $authError = $this->requerirAutenticacion();
        if ($authError !== null) {
            return $authError;
        }
        
        $auth = $this->verificarAutenticacion();
        $userId = $auth['userId'];
        
        // Obtener action_id y params según el método HTTP
        $isGet = Yii::$app->request->isGet;
        if ($isGet) {
            $actionId = Yii::$app->request->get('action_id');
            // Obtener todos los parámetros de la query string excepto action_id
            $params = Yii::$app->request->get();
            unset($params['action_id']);
            
            // Log para debug (solo en desarrollo)
            Yii::info("GET execute-action - action_id: {$actionId}, params: " . json_encode($params), 'api-execute-action');
        } else {
            $actionId = Yii::$app->request->post('action_id');
            $params = Yii::$app->request->post('params', []);
        }
        
        if (empty($actionId)) {
            return $this->error('action_id es requerido', null, 400);
        }
        
        try {
            // Buscar la acción por action_id
            // findActionById ya filtra las acciones por permisos del usuario usando
            // ActionMappingService::getAvailableActionsForUser que verifica RBAC
            $action = $this->findActionById($actionId, $userId);
            
            if (!$action) {
                // La acción no existe o el usuario no tiene permisos para ejecutarla
                return $this->error('Acción no encontrada o no tienes permisos para ejecutarla según tu rol', null, 403);
            }
            
            // Si es GET, devolver el form_config (wizard) sin ejecutar
            if ($isGet) {
                return $this->getActionFormConfig($action, $params, $userId);
            }
            
            // Si es POST, ejecutar la acción
            // Si la acción fue encontrada, significa que el usuario tiene permisos
            // (ya fue validado por ActionMappingService::getAvailableActionsForUser)
            return $this->executeAction($action, $params, $userId);
            
        } catch (\Exception $e) {
            Yii::error("Error ejecutando acción: " . $e->getMessage(), 'api-execute-action');
            return $this->error('Error al ejecutar la acción: ' . $e->getMessage(), null, 500);
        }
    }
    
    /**
     * Obtener configuración del formulario/wizard para una acción
     * @param array $action
     * @param array $params Parámetros ya proporcionados
     * @param int|null $userId
     * @return array
     */
    private function getActionFormConfig($action, $params, $userId)
    {
        try {
            $wizardConfig = null;
            $wizardSteps = [];
            $fieldsConfig = [];
            $actionName = null;
            $actionId = $action['action_id'] ?? null;
            
            // Intentar obtener wizard_config llamando al método con GET
            $controllerClass = 'frontend\\controllers\\' . ucfirst($action['controller']) . 'Controller';
            $actionName = $action['action'];
            $actionCamelCase = Inflector::id2camel($actionName, '-');
            $methodName = 'action' . $actionCamelCase;
            
            if (class_exists($controllerClass) && method_exists($controllerClass, $methodName)) {
                // Establecer la identidad del usuario antes de ejecutar la acción
                // El usuario ya está autenticado (verificado en actionExecuteAction)
                $user = Yii::$app->user->identity;
                if (!$user) {
                    // Si no hay identidad establecida, buscarla
                    $user = \webvimark\modules\UserManagement\models\User::findOne($userId);
                    if (!$user) {
                        // Si no hay usuario, continuar con análisis automático
                        Yii::warning("Usuario no encontrado para userId: {$userId}, usando análisis automático", 'api-execute-action');
                    }
                }
                
                if ($user) {
                    // Guardar el estado original del usuario para restaurarlo después
                    $originalUserIdentity = Yii::$app->user->identity;
                    
                    // Establecer la identidad del usuario sin iniciar sesión (API stateless con JWT)
                    Yii::$app->user->setIdentity($user);
                    
                    // Actualizar permisos y rutas en la sesión para que los controladores puedan verificar acceso
                    \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions(Yii::$app->user);
                    
                    try {
                        // Crear instancia temporal
                        $controller = new $controllerClass($action['controller'], Yii::$app);
                        $controller->enableCsrfValidation = false;
                        
                        // Deshabilitar temporalmente el behavior ghost-access ya que los permisos
                        // ya fueron verificados en findActionById usando ActionMappingService
                        $originalBehaviors = $controller->behaviors();
                        $controller->detachBehaviors();
                        
                        // Reagregar solo los behaviors que no sean ghost-access
                        foreach ($originalBehaviors as $name => $behavior) {
                            if ($name !== 'ghost-access') {
                                $controller->attachBehavior($name, $behavior);
                            }
                        }
                        
                        // Simular GET request
                        $originalGet = $_GET;
                        $originalMethod = $_SERVER['REQUEST_METHOD'] ?? 'POST';
                        $_GET = array_merge(['action_id' => $actionId], $params);
                        $_SERVER['REQUEST_METHOD'] = 'GET';
                        Yii::$app->request->setQueryParams($_GET);
                        
                        try {
                            // Configurar response format como JSON
                            $originalFormat = Yii::$app->response->format;
                            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                            
                            try {
                                $result = $controller->runAction($actionName, []);
                                
                                // Si retorna wizard_config, usarlo directamente
                                if (is_array($result) && isset($result['wizard_config'])) {
                                    $wizardConfig = $result['wizard_config'];
                                    $wizardSteps = $wizardConfig['steps'] ?? [];
                                    $fieldsConfig = $wizardConfig['fields'] ?? [];
                                }
                            } catch (\yii\web\ForbiddenHttpException $e) {
                                // Si hay error de acceso, continuar con análisis automático
                                Yii::info("Error de acceso al llamar método {$methodName}: " . $e->getMessage() . ", usando análisis automático", 'api-execute-action');
                            } catch (\yii\web\BadRequestHttpException $e) {
                                // Si hay error de parámetros (ej: Login Requerido), continuar con análisis automático
                                Yii::info("Error de parámetros al llamar método {$methodName}: " . $e->getMessage() . ", usando análisis automático", 'api-execute-action');
                            } catch (\yii\web\HttpException $e) {
                                // Capturar cualquier excepción HTTP (incluyendo UnauthorizedHttpException)
                                Yii::info("Error HTTP al llamar método {$methodName}: " . $e->getMessage() . " (código: {$e->statusCode}), usando análisis automático", 'api-execute-action');
                            } catch (\Exception $e) {
                                // Cualquier otro error, continuar con análisis automático
                                Yii::warning("Error al llamar método {$methodName}: " . $e->getMessage() . " (" . get_class($e) . "), usando análisis automático", 'api-execute-action');
                            }
                            
                            // Restaurar formato original
                            Yii::$app->response->format = $originalFormat;
                        } finally {
                            $_GET = $originalGet;
                            $_SERVER['REQUEST_METHOD'] = $originalMethod;
                        }
                    } finally {
                        // Restaurar el estado original del usuario
                        Yii::$app->user->setIdentity($originalUserIdentity);
                    }
                }
            }
            
            // Si no hay wizard_config del método, usar análisis automático
            if (empty($wizardSteps)) {
                $actionAnalysis = \common\components\ActionParameterAnalyzer::analyzeActionParameters(
                    $action,
                    $params, // Los params de GET se pasan como extractedData
                    $userId
                );
                
                $fieldsConfig = $actionAnalysis['form_config']['fields'] ?? [];
                $wizardSteps = $this->generateWizardSteps($fieldsConfig);
                $actionName = $actionAnalysis['action_name'] ?? $action['display_name'] ?? 'Completa la información';
                $actionId = $actionAnalysis['action_id'] ?? $actionId;
            } else {
                // Si hay wizard_config, usar el action_name del action original
                $actionName = $action['action_name'] ?? $action['display_name'] ?? 'Completa la información';
            }
            
            // Calcular paso inicial de forma genérica
            $initialStep = $this->calculateInitialStep($wizardSteps, $fieldsConfig, $params);
            
            // Preparar form_config para compatibilidad
            $formConfig = [
                'fields' => $fieldsConfig,
            ];
            
            // Si hay wizard_config del método, incluir metadata adicional
            if ($wizardConfig !== null) {
                $formConfig['navigation'] = $wizardConfig['navigation'] ?? [];
            }
            
            // Determinar si está listo para ejecutar (todos los campos requeridos tienen valores)
            $readyToExecute = true;
            foreach ($fieldsConfig as $field) {
                if (($field['required'] ?? false) && 
                    (!isset($params[$field['name']]) || 
                     $params[$field['name']] === null || 
                     $params[$field['name']] === '')) {
                    $readyToExecute = false;
                    break;
                }
            }
            
            // Preparar parámetros para la respuesta
            $providedParams = [];
            $missingParams = [];
            foreach ($fieldsConfig as $field) {
                $fieldName = $field['name'] ?? null;
                if (empty($fieldName)) {
                    continue;
                }
                
                if (isset($params[$fieldName]) && $params[$fieldName] !== null && $params[$fieldName] !== '') {
                    $providedParams[$fieldName] = $params[$fieldName];
                } elseif ($field['required'] ?? false) {
                    $missingParams[] = $field;
                }
            }
            
            return [
                'success' => true,
                'data' => [
                    'action_id' => $actionId,
                    'action_name' => $actionName,
                    'form_config' => $formConfig,
                    'parameters' => [
                        'provided' => $providedParams,
                        'missing' => $missingParams,
                    ],
                    'ready_to_execute' => $readyToExecute,
                    'initial_step' => $initialStep,
                    'wizard_steps' => $wizardSteps,
                ],
            ];
        } catch (\Exception $e) {
            Yii::error("Error obteniendo form_config: " . $e->getMessage(), 'api-execute-action');
            return $this->error('Error al obtener configuración del formulario: ' . $e->getMessage(), null, 500);
        }
    }
    
    /**
     * Calcular el paso inicial del wizard basándose en los pasos y parámetros proporcionados
     * 
     * @param array $wizardSteps Array de pasos del wizard
     * @param array $fieldsConfig Configuración de todos los campos (para verificar required)
     * @param array $providedParams Parámetros ya proporcionados
     * @return int Índice del paso inicial (0-based)
     */
    private function calculateInitialStep($wizardSteps, $fieldsConfig, $providedParams)
    {
        if (empty($wizardSteps)) {
            return 0;
        }
        
        // Si no hay parámetros proporcionados, empezar desde el primer paso
        if (empty($providedParams)) {
            return 0;
        }
        
        // Crear un mapa de configuración de campos por nombre para acceso rápido
        $fieldsMap = [];
        foreach ($fieldsConfig as $field) {
            $fieldName = $field['name'] ?? null;
            if (!empty($fieldName)) {
                $fieldsMap[$fieldName] = $field;
            }
        }
        
        // Verificar cada paso en orden para encontrar el primero con campos incompletos
        foreach ($wizardSteps as $stepIndex => $step) {
            $stepFields = $step['fields'] ?? [];
            $stepComplete = true;
            
            // Verificar si todos los campos requeridos de este paso tienen valores
            foreach ($stepFields as $field) {
                // El campo puede ser un string (nombre) o un array con 'name'
                $fieldName = is_array($field) ? ($field['name'] ?? null) : $field;
                
                if (empty($fieldName)) {
                    continue;
                }
                
                // Obtener configuración del campo para verificar si es requerido
                $fieldConfig = $fieldsMap[$fieldName] ?? null;
                
                // Si el campo es requerido y no tiene valor, el paso no está completo
                $isRequired = $fieldConfig['required'] ?? false;
                $hasValue = isset($providedParams[$fieldName]) && 
                           $providedParams[$fieldName] !== null && 
                           $providedParams[$fieldName] !== '';
                
                if ($isRequired && !$hasValue) {
                    $stepComplete = false;
                    break;
                }
            }
            
            // Si este paso no está completo, este es el paso inicial
            if (!$stepComplete) {
                return $stepIndex;
            }
        }
        
        // Si todos los pasos están completos, mostrar el último paso (confirmación)
        return count($wizardSteps) - 1;
    }
    
    /**
     * Generar pasos del wizard basado en los campos del formulario
     * @param array $fields
     * @return array
     */
    private function generateWizardSteps($fields)
    {
        $steps = [];
        
        if (empty($fields)) {
            return $steps;
        }
        
        // Verificar si todos los campos tienen valores (confirmación)
        $allFieldsHaveValues = true;
        foreach ($fields as $field) {
            if (empty($field['value']) && $field['required'] ?? false) {
                $allFieldsHaveValues = false;
                break;
            }
        }
        
        // Si todos los campos tienen valores, crear un solo paso de confirmación
        if ($allFieldsHaveValues) {
            $steps[] = [
                'step' => 0,
                'title' => "Confirmación",
                'fields' => $fields,
            ];
            return $steps;
        }
        
        // Si faltan campos, agrupar en pasos lógicos
        $currentStep = 0;
        $currentStepFields = [];
        
        foreach ($fields as $field) {
            // Si el campo tiene depends_on y ya hay campos en el paso actual,
            // podría ser un nuevo paso
            if (!empty($currentStepFields) && isset($field['depends_on'])) {
                // Si hay dependencia, podría ser un nuevo paso
                // Por simplicidad, agrupamos todos en un paso a menos que haya muchos campos
                if (count($currentStepFields) >= 3) {
                    $steps[] = [
                        'step' => $currentStep,
                        'title' => "Paso " . ($currentStep + 1),
                        'fields' => $currentStepFields,
                    ];
                    $currentStep++;
                    $currentStepFields = [];
                }
            }
            
            $currentStepFields[] = $field;
        }
        
        // Agregar el último paso si tiene campos
        if (!empty($currentStepFields)) {
            $steps[] = [
                'step' => $currentStep,
                'title' => $currentStep === 0 ? "Información básica" : "Paso " . ($currentStep + 1),
                'fields' => $currentStepFields,
            ];
        }
        
        return $steps;
    }

    /**
     * Buscar acción por action_id
     * @param string $actionId
     * @param int|null $userId
     * @return array|null
     */
    private function findActionById($actionId, $userId = null)
    {
        // Obtener todas las acciones disponibles para el usuario
        $allActions = \common\components\ActionMappingService::getAvailableActionsForUser($userId);
        
        foreach ($allActions as $action) {
            if (($action['action_id'] ?? '') === $actionId) {
                return $action;
            }
        }
        
        return null;
    }

    /**
     * Inyectar parámetros en el request object
     * @param array $params Parámetros a inyectar
     * @return array Estado original del request para restaurar después
     */
    private function injectParamsIntoRequest($params)
    {
        $originalState = [
            'bodyParams' => Yii::$app->request->bodyParams,
            'requestMethod' => $_SERVER['REQUEST_METHOD'] ?? null,
        ];
        
        if (!empty($params)) {
            // Inyectar parámetros en bodyParams
            $currentBodyParams = Yii::$app->request->bodyParams ?? [];
            Yii::$app->request->bodyParams = array_merge($currentBodyParams, $params);
            
            // Establecer el método como POST temporalmente en $_SERVER
            // Esto es necesario para que UserRequest::requireUserParam() funcione correctamente
            // ya que verifica Yii::$app->request->isPost que lee de $_SERVER['REQUEST_METHOD']
            $_SERVER['REQUEST_METHOD'] = 'POST';
        }
        
        return $originalState;
    }
    
    /**
     * Restaurar el estado original del request
     * @param array $originalState Estado original guardado
     */
    private function restoreRequestState($originalState)
    {
        if ($originalState) {
            Yii::$app->request->bodyParams = $originalState['bodyParams'];
            if ($originalState['requestMethod'] !== null) {
                $_SERVER['REQUEST_METHOD'] = $originalState['requestMethod'];
            } else {
                unset($_SERVER['REQUEST_METHOD']);
            }
        }
    }

    /**
     * Ejecutar una acción
     * @param array $action
     * @param array $params
     * @param int|null $userId
     * @return array
     */
    private function executeAction($action, $params, $userId)
    {
        $route = $action['route'] ?? null;
        
        // Si no tiene ruta, es una acción especial del sistema
        if (empty($route)) {
            return [
                'success' => false,
                'error' => 'Esta acción no puede ejecutarse directamente',
                'action_id' => $action['action_id'] ?? null,
            ];
        }
        
        // Parsear ruta: /frontend/efectores/indexuserefector
        $routeParts = explode('/', trim($route, '/'));
        
        // Obtener controlador y acción
        // Formato esperado: /frontend/efectores/indexuserefector
        // O: /efectores/indexuserefector
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
                'action_id' => $action['action_id'] ?? null,
            ];
        }
        
        // Crear instancia del controlador
        $controllerClass = 'frontend\\controllers\\' . ucfirst($controllerName) . 'Controller';
        
        if (!class_exists($controllerClass)) {
            return [
                'success' => false,
                'error' => 'Controlador no encontrado: ' . $controllerClass,
                'action_id' => $action['action_id'] ?? null,
            ];
        }
        
        try {
            // Inyectar parámetros en el request y guardar estado original
            $originalState = $this->injectParamsIntoRequest($params);
            
            // Establecer la identidad del usuario antes de ejecutar la acción
            // El usuario ya está autenticado (verificado en actionExecuteAction)
            // Intentar usar la identidad ya establecida por JsonHttpBearerAuth si está disponible
            // Solo buscar el usuario si no está disponible (endpoints excluidos del authenticator)
            $user = Yii::$app->user->identity;
            if (!$user) {
                // Si no hay identidad establecida, buscarla (endpoints excluidos del authenticator)
                $user = \webvimark\modules\UserManagement\models\User::findOne($userId);
                if (!$user) {
                    return [
                        'success' => false,
                        'error' => 'Usuario no encontrado',
                        'action_id' => $action['action_id'],
                    ];
                }
            }
            
            // Guardar el estado original del usuario para restaurarlo después
            $originalUserIdentity = Yii::$app->user->identity;
            
            // Establecer la identidad del usuario sin iniciar sesión (API stateless con JWT)
            // El rol "paciente" se asigna automáticamente por SisseDbManager::getRolesByUser()
            Yii::$app->user->setIdentity($user);
            
            // Actualizar permisos y rutas en la sesión para que los controladores puedan verificar acceso
            // Esto es necesario porque setIdentity() no actualiza automáticamente los permisos
            \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions(Yii::$app->user);
            
            try {
                // Crear instancia del controlador sin especificar módulo
                // El controlador está en frontend\controllers, no en un módulo
                $controller = new $controllerClass($controllerName, Yii::$app);
                
                // Deshabilitar temporalmente el behavior ghost-access ya que los permisos
                // ya fueron verificados en findActionById usando ActionMappingService
                $originalBehaviors = $controller->behaviors();
                $controller->detachBehaviors();
                
                // Reagregar solo los behaviors que no sean ghost-access
                foreach ($originalBehaviors as $name => $behavior) {
                    if ($name !== 'ghost-access') {
                        $controller->attachBehavior($name, $behavior);
                    }
                }
                
                // Deshabilitar validación CSRF ya que estamos ejecutando desde la API
                // y la autenticación se maneja con JWT
                $controller->enableCsrfValidation = false;
                
                // Convertir nombre de acción de kebab-case (crear-mi-turno) a camelCase (crearMiTurno)
                // usando Inflector de Yii2
                $actionCamelCase = Inflector::id2camel($actionName, '-');
                $methodName = 'action' . $actionCamelCase;
                
                if (!method_exists($controller, $methodName)) {
                    return [
                        'success' => false,
                        'error' => 'Método no encontrado: ' . $methodName,
                        'action_id' => $action['action_id'],
                    ];
                }
                
                // Configurar response format como JSON
                Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                
                // Ejecutar la acción (sin pasar params directamente, ya están en request)
                $result = $controller->runAction($actionName, []);
                
                // Validar que retorne JSON (array)
                if (!is_array($result)) {
                    return [
                        'success' => false,
                        'error' => 'La acción debe retornar JSON (array). Retornó: ' . gettype($result),
                        'action_id' => $action['action_id'],
                    ];
                }
                
                // Devolver resultado
                return [
                    'success' => true,
                    'data' => $result,
                    'action_id' => $action['action_id'],
                ];
                
            } finally {
                // Restaurar el estado original del usuario
                Yii::$app->user->setIdentity($originalUserIdentity);
            }
            
        } catch (\yii\web\BadRequestHttpException $e) {
            // Manejar excepciones de UserRequest::requireUserParam
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'action_id' => $action['action_id'],
            ];
        } catch (\Exception $e) {
            Yii::error("Error ejecutando acción {$action['action_id']}: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'api-execute-action');
            return [
                'success' => false,
                'error' => 'Error al ejecutar la acción: ' . $e->getMessage(),
                'action_id' => $action['action_id'],
            ];
        } finally {
            // Restaurar estado original del request
            if (isset($originalState)) {
                $this->restoreRequestState($originalState);
            }
        }
    }

    /**
     * Deshabilitar acciones por defecto de ActiveController
     */
    public function actions()
    {
        return [];
    }
}

