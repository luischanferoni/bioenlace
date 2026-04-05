<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\components\Actions\UniversalQueryAgent;
use frontend\modules\api\v1\controllers\BaseController;
use yii\helpers\Inflector;

class CrudController extends BaseController
{
    public $enableCsrfValidation = false; // Deshabilitar CSRF para API

    /**
     * AutenticaciÃ³n centralizada en JsonHttpBearerAuth; process-query y execute-action requieren Bearer.
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['options'];
        return $behaviors;
    }

    /**
     * Procesar interacción del usuario en lenguaje natural usando UniversalQueryAgent
     * 
     * Este endpoint procesa interacciones del usuario en lenguaje natural y devuelve acciones relevantes
     * del sistema que el usuario tiene permitido realizar.
     * 
     * Ejemplos de interacciones:
     * - "listame mis licencias"
     * - "29486884" (bÃºsqueda por DNI)
     * - "cuÃ¡ntas consultas voy atendiendo este mes?"
     * - "quÃ© puedo hacer?"
     * 
     * @return array Respuesta con acciones encontradas o error
     */
    public function actionProcesarInteraccion()
    {
        $userId = Yii::$app->user->id;

        $interaccionUsuario = Yii::$app->request->post('interaccion_usuario');
        $texto = null;
        if (is_array($interaccionUsuario)) {
            $texto = $interaccionUsuario['texto'] ?? null;
        }
        $actionId = Yii::$app->request->post('action_id'); // Opcional: para bÃºsqueda directa por ID
        
        if (($texto === null || trim((string) $texto) === '') && empty($actionId)) {
            return $this->error('interaccion_usuario.texto o action_id es requerido', null, 400);
        }

        try {
            // Procesar consulta usando UniversalQueryAgent (implementaciÃ³n genÃ©rica y mejorada)
            // Si viene action_id, se buscarÃ¡ primero por ID, luego por matching semÃ¡ntico, y finalmente por LLM
            $result = UniversalQueryAgent::processQuery($texto, $userId, $actionId);
            
            // Asegurar que el resultado tenga el formato correcto
            if (isset($result['success'])) {
                return $result;
            }
            
            // Si no tiene formato estÃ¡ndar, envolverlo
            return $this->success($result);
        } catch (\Exception $e) {
            Yii::error("Error procesando consulta: " . $e->getMessage(), 'api-crud-controller');
            return $this->error('Error al procesar la consulta. Por favor, intente nuevamente.', null, 500);
        }
    }

    /**
     * Ejecutar una acciÃ³n especÃ­fica por su action_id
     * 
     * GET: Devuelve el form_config (wizard) para la acciÃ³n sin ejecutarla
     * POST: Ejecuta la acciÃ³n con los parÃ¡metros proporcionados
     * 
     * GET /api/v1/crud/ejecutar-accion?action_id=...&param1=value1&param2=value2
     * POST /api/v1/crud/ejecutar-accion
     * Body: {
     *   "action_id": "efectores.indexuserefector",
     *   "params": {} // opcional
     * }
     * 
     * @return array
     */
    public function actionEjecutarAccion()
    {
        $userId = Yii::$app->user->id;

        // Obtener action_id y params segÃºn el mÃ©todo HTTP
        $isGet = Yii::$app->request->isGet;
        if ($isGet) {
            $actionId = Yii::$app->request->get('action_id');
            // Obtener todos los parÃ¡metros de la query string excepto action_id
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
            // Buscar la acciÃ³n por action_id
            // findActionById ya filtra las acciones por permisos del usuario usando
            // ActionMappingService::getAvailableActionsForUser que verifica RBAC
            $action = $this->findActionById($actionId, $userId);
            
            if (!$action) {
                // La acciÃ³n no existe o el usuario no tiene permisos para ejecutarla
                return $this->error('AcciÃ³n no encontrada o no tienes permisos para ejecutarla segÃºn tu rol', null, 403);
            }
            
            // Si es GET, devolver el form_config (wizard) sin ejecutar
            if ($isGet) {
                return $this->getActionFormConfig($action, $params, $userId);
            }
            
            // Si es POST, ejecutar la acciÃ³n
            // Si la acciÃ³n fue encontrada, significa que el usuario tiene permisos
            // (ya fue validado por ActionMappingService::getAvailableActionsForUser)
            return $this->executeAction($action, $params, $userId);
            
        } catch (\Exception $e) {
            Yii::error("Error ejecutando acciÃ³n: " . $e->getMessage(), 'api-execute-action');
            return $this->error('Error al ejecutar la acciÃ³n: ' . $e->getMessage(), null, 500);
        }
    }
    
    /**
     * Obtener configuraciÃ³n del formulario/wizard para una acciÃ³n
     * @param array $action
     * @param array $params ParÃ¡metros ya proporcionados
     * @param int|null $userId
     * @return array
     */
    private function getActionFormConfig($action, $params, $userId)
    {
        try {
            // Intentar obtener wizard_config llamando al mÃ©todo con GET
            $controllerClass = $this->resolveControllerClassByAction($action);
            $actionName = $action['action'];
            $actionCamelCase = Inflector::id2camel($actionName, '-');
            $methodName = 'action' . $actionCamelCase;
            
            // Para acciones API de alta de turno, no ejecutar el método POST en GET.
            if (($action['controller'] ?? '') === 'turnos' && in_array($actionName, ['crear-como-paciente', 'crear-para-paciente'], true)) {
                return $this->buildTurnosWizardFromTemplate($action, $params);
            }
            
            if (class_exists($controllerClass) && method_exists($controllerClass, $methodName)) {
                $user = Yii::$app->user->identity;
                if ($user) {
                    // Guardar el estado original del usuario para restaurarlo despuÃ©s
                    $originalUserIdentity = Yii::$app->user->identity;
                    
                    // Establecer la identidad del usuario sin iniciar sesiÃ³n (API stateless con JWT)
                    Yii::$app->user->setIdentity($user);
                    
                    // Actualizar permisos y rutas en la sesiÃ³n para que los controladores puedan verificar acceso
                    \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions(Yii::$app->user);
                    \common\components\Actions\AllowedRoutesResolver::markSessionRoutesOwner((int) Yii::$app->user->id);
                    
                    try {
                        // Crear instancia temporal
                        $controller = $this->createControllerInstance($controllerClass, $action['controller']);
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
                        $actionId = $action['action_id'] ?? null;
                        $_GET = array_merge(['action_id' => $actionId], $params);
                        $_SERVER['REQUEST_METHOD'] = 'GET';
                        Yii::$app->request->setQueryParams($_GET);
                        
                        // Forzar que Yii::$app->request->isGet devuelva true
                        // Esto es necesario porque setQueryParams no actualiza isGet automÃ¡ticamente
                        $reflectionRequest = new \ReflectionClass(Yii::$app->request);
                        if ($reflectionRequest->hasProperty('_method')) {
                            $methodProperty = $reflectionRequest->getProperty('_method');
                            $methodProperty->setAccessible(true);
                            $methodProperty->setValue(Yii::$app->request, 'GET');
                        }
                        
                        try {
                            // Guardar el formato original y deshabilitar el envÃ­o automÃ¡tico de respuesta
                            $originalFormat = Yii::$app->response->format;
                            $originalData = Yii::$app->response->data;
                            
                            // Configurar response format como JSON pero sin enviar
                            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                            
                            // Capturar cualquier salida que el mÃ©todo pueda generar
                            ob_start();
                            
                            try {
                                // Usar reflexiÃ³n para llamar al mÃ©todo directamente y capturar el resultado
                                // Esto evita problemas con runAction cuando hay FORMAT_JSON
                                $reflection = new \ReflectionMethod($controller, $methodName);
                                $reflection->setAccessible(true);
                                
                                // Llamar al mÃ©todo directamente
                                $result = $reflection->invoke($controller);
                                
                                // Limpiar cualquier salida capturada
                                $output = ob_get_clean();
                                
                                // Si hay salida pero no hay resultado, podrÃ­a ser que Yii2 enviÃ³ la respuesta
                                if (!empty($output) && $result === null) {
                                    Yii::warning("El mÃ©todo {$methodName} generÃ³ salida pero no retornÃ³ valor. Output: " . substr($output, 0, 200), 'api-execute-action');
                                }
                                
                                // Verificar si el resultado es vÃ¡lido
                                if ($result === null) {
                                    $errorMsg = "El mÃ©todo {$methodName} devolviÃ³ null.";
                                    Yii::error($errorMsg, 'api-execute-action');
                                    throw new \yii\web\ServerErrorHttpException(
                                        "No se pudo obtener la configuraciÃ³n del formulario. Por favor, contacte al administrador."
                                    );
                                }
                                
                                if (!is_array($result)) {
                                    $errorMsg = "El mÃ©todo {$methodName} devolviÃ³ un tipo invÃ¡lido: " . gettype($result) . ". Se esperaba un array.";
                                    Yii::error($errorMsg, 'api-execute-action');
                                    throw new \yii\web\ServerErrorHttpException(
                                        "No se pudo obtener la configuraciÃ³n del formulario. Por favor, contacte al administrador."
                                    );
                                }
                                
                                // Validar que wizard_config no estÃ© vacÃ­o
                                if (isset($result['wizard_config'])) {
                                    $wizardConfig = $result['wizard_config'];
                                    $hasSteps = !empty($wizardConfig['steps'] ?? []);
                                    $hasFields = !empty($wizardConfig['fields'] ?? []);
                                    
                                    if (!$hasSteps && !$hasFields) {
                                        $errorMsg = "El mÃ©todo {$methodName} devolviÃ³ wizard_config vacÃ­o (sin steps ni fields).";
                                        Yii::error($errorMsg, 'api-execute-action');
                                        return $this->error(
                                            'No se pudo obtener la configuraciÃ³n del formulario. Por favor, intente nuevamente mÃ¡s tarde.',
                                            null,
                                            500
                                        );
                                    }
                                    
                                    // Transformar wizard_config a la estructura que espera la app mÃ³vil
                                    $formConfig = [
                                        'fields' => $wizardConfig['fields'] ?? [],
                                    ];
                                    
                                    // Incluir metadata si existe
                                    if (isset($wizardConfig['navigation'])) {
                                        $formConfig['navigation'] = $wizardConfig['navigation'];
                                    }
                                    if (isset($wizardConfig['validation'])) {
                                        $formConfig['validation'] = $wizardConfig['validation'];
                                    }
                                    if (isset($wizardConfig['ui'])) {
                                        $formConfig['ui'] = $wizardConfig['ui'];
                                    }
                                    
                                    // Expandir nombres de campos en steps a objetos completos
                                    $wizardSteps = $this->expandStepFields(
                                        $wizardConfig['steps'] ?? [],
                                        $wizardConfig['fields'] ?? []
                                    );
                                    
                                    $data = [
                                        'form_config' => $formConfig,
                                        'wizard_steps' => $wizardSteps,
                                        'initial_step' => $wizardConfig['initial_step'] ?? 0,
                                        'action_id' => $actionId,
                                        'action_name' => $action['action_name'] ?? $action['display_name'] ?? 'Completa la informaciÃ³n',
                                    ];
                                    if (isset($result['kind'])) {
                                        $data['kind'] = $result['kind'];
                                    }
                                    if (isset($result['ui_type'])) {
                                        $data['ui_type'] = $result['ui_type'];
                                    }
                                    if (isset($result['compatibility'])) {
                                        $data['compatibility'] = $result['compatibility'];
                                    }

                                    return [
                                        'success' => true,
                                        'data' => $data,
                                    ];
                                } elseif (isset($result['steps']) || isset($result['fields'])) {
                                    // Si tiene steps/fields directamente (sin wizard_config)
                                    $hasSteps = !empty($result['steps'] ?? []);
                                    $hasFields = !empty($result['fields'] ?? []);
                                    
                                    if (!$hasSteps && !$hasFields) {
                                        $errorMsg = "El mÃ©todo {$methodName} devolviÃ³ steps y fields vacÃ­os.";
                                        Yii::error($errorMsg, 'api-execute-action');
                                        return $this->error(
                                            'No se pudo obtener la configuraciÃ³n del formulario. Por favor, intente nuevamente mÃ¡s tarde.',
                                            null,
                                            500
                                        );
                                    }
                                    
                                    // Transformar a la estructura esperada
                                    $formConfig = [
                                        'fields' => $result['fields'] ?? [],
                                    ];
                                    
                                    $wizardSteps = $this->expandStepFields(
                                        $result['steps'] ?? [],
                                        $result['fields'] ?? []
                                    );
                                    
                                    $data = [
                                        'form_config' => $formConfig,
                                        'wizard_steps' => $wizardSteps,
                                        'initial_step' => $result['initial_step'] ?? 0,
                                        'action_id' => $actionId,
                                        'action_name' => $action['action_name'] ?? $action['display_name'] ?? 'Completa la informaciÃ³n',
                                    ];
                                    if (isset($result['kind'])) {
                                        $data['kind'] = $result['kind'];
                                    }
                                    if (isset($result['ui_type'])) {
                                        $data['ui_type'] = $result['ui_type'];
                                    }
                                    if (isset($result['compatibility'])) {
                                        $data['compatibility'] = $result['compatibility'];
                                    }

                                    return [
                                        'success' => true,
                                        'data' => $data,
                                    ];
                                }
                                
                                // Si no tiene wizard_config ni steps/fields, devolver error
                                $errorMsg = "El mÃ©todo {$methodName} no devolviÃ³ wizard_config, steps ni fields.";
                                Yii::error($errorMsg, 'api-execute-action');
                                return $this->error(
                                    'No se pudo obtener la configuraciÃ³n del formulario. Por favor, intente nuevamente mÃ¡s tarde.',
                                    null,
                                    500
                                );
                            } catch (\yii\web\ForbiddenHttpException $e) {
                                // Re-lanzar excepciones de acceso
                                throw $e;
                            } catch (\yii\web\BadRequestHttpException $e) {
                                // Re-lanzar excepciones de parÃ¡metros
                                throw $e;
                            } catch (\yii\web\HttpException $e) {
                                // Re-lanzar excepciones HTTP
                                throw $e;
                            } catch (\Exception $e) {
                                // Registrar error tÃ©cnico en el log
                                Yii::error("Error al llamar mÃ©todo {$methodName}: " . $e->getMessage() . " (" . get_class($e) . "). Trace: " . $e->getTraceAsString(), 'api-execute-action');
                                
                                // Re-lanzar excepciones HTTP con mensajes amigables
                                if ($e instanceof \yii\web\HttpException) {
                                    // Para excepciones HTTP, mantener el mensaje original si es amigable
                                    // o usar uno genÃ©rico si es tÃ©cnico
                                    $userMessage = $e->getMessage();
                                    if (strpos($userMessage, 'wizard_config') !== false || 
                                        strpos($userMessage, 'mÃ©todo') !== false ||
                                        strpos($userMessage, 'null') !== false) {
                                        $userMessage = "No se pudo obtener la configuraciÃ³n del formulario. Por favor, contacte al administrador.";
                                    }
                                    throw new \yii\web\ServerErrorHttpException($userMessage, $e->getCode(), $e);
                                }
                                
                                // Para otras excepciones, usar mensaje genÃ©rico
                                throw new \yii\web\ServerErrorHttpException(
                                    "No se pudo obtener la configuraciÃ³n del formulario. Por favor, contacte al administrador.",
                                    0,
                                    $e
                                );
                            } finally {
                                // Limpiar buffer de salida si quedÃ³ algo
                                if (ob_get_level() > 0) {
                                    ob_end_clean();
                                }
                            }
                            
                            // Restaurar formato original y data
                            Yii::$app->response->format = $originalFormat;
                            Yii::$app->response->data = $originalData;
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
            
            // Si el mÃ©todo no existe, lanzar excepciÃ³n
            $errorMsg = "El mÃ©todo {$methodName} no existe en {$controllerClass}.";
            Yii::error($errorMsg, 'api-execute-action');
            throw new \yii\web\ServerErrorHttpException(
                "No se pudo obtener la configuraciÃ³n del formulario. Por favor, contacte al administrador."
            );
        } catch (\yii\web\ForbiddenHttpException $e) {
            // Excepciones de acceso: mantener el mensaje original
            Yii::error("Error de acceso obteniendo form_config: " . $e->getMessage(), 'api-execute-action');
            return $this->error($e->getMessage(), null, $e->statusCode);
        } catch (\yii\web\BadRequestHttpException $e) {
            // Excepciones de parÃ¡metros: mantener el mensaje original
            Yii::error("Error de parÃ¡metros obteniendo form_config: " . $e->getMessage(), 'api-execute-action');
            return $this->error($e->getMessage(), null, $e->statusCode);
        } catch (\yii\web\HttpException $e) {
            // Otras excepciones HTTP: mantener el mensaje original si es amigable
            $userMessage = $e->getMessage();
            if (strpos($userMessage, 'wizard_config') !== false || 
                strpos($userMessage, 'mÃ©todo') !== false ||
                strpos($userMessage, 'null') !== false) {
                $userMessage = "No se pudo obtener la configuraciÃ³n del formulario. Por favor, contacte al administrador.";
            }
            Yii::error("Error HTTP obteniendo form_config: " . $e->getMessage() . " (cÃ³digo: {$e->statusCode}). Trace: " . $e->getTraceAsString(), 'api-execute-action');
            return $this->error($userMessage, null, $e->statusCode);
        } catch (\Exception $e) {
            // Excepciones genÃ©ricas: mensaje amigable al usuario, detalles tÃ©cnicos en el log
            Yii::error("Error obteniendo form_config: " . $e->getMessage() . " (" . get_class($e) . "). Trace: " . $e->getTraceAsString(), 'api-execute-action');
            return $this->error('No se pudo obtener la configuraciÃ³n del formulario. Por favor, contacte al administrador.', null, 500);
        }
    }
    
    /**
     * Calcular el paso inicial del wizard basÃ¡ndose en los pasos y parÃ¡metros proporcionados
     * 
     * @param array $wizardSteps Array de pasos del wizard
     * @param array $fieldsConfig ConfiguraciÃ³n de todos los campos (para verificar required)
     * @param array $providedParams ParÃ¡metros ya proporcionados
     * @return int Ãndice del paso inicial (0-based)
     */
    private function calculateInitialStep($wizardSteps, $fieldsConfig, $providedParams)
    {
        if (empty($wizardSteps)) {
            return 0;
        }
        
        // Si no hay parÃ¡metros proporcionados, empezar desde el primer paso
        if (empty($providedParams)) {
            return 0;
        }
        
        // Crear un mapa de configuraciÃ³n de campos por nombre para acceso rÃ¡pido
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
                
                // Obtener configuraciÃ³n del campo para verificar si es requerido
                $fieldConfig = $fieldsMap[$fieldName] ?? null;
                
                // Si el campo es requerido y no tiene valor, el paso no estÃ¡ completo
                $isRequired = $fieldConfig['required'] ?? false;
                $hasValue = isset($providedParams[$fieldName]) && 
                           $providedParams[$fieldName] !== null && 
                           $providedParams[$fieldName] !== '';
                
                if ($isRequired && !$hasValue) {
                    $stepComplete = false;
                    break;
                }
            }
            
            // Si este paso no estÃ¡ completo, este es el paso inicial
            if (!$stepComplete) {
                return $stepIndex;
            }
        }
        
        // Si todos los pasos estÃ¡n completos, mostrar el Ãºltimo paso (confirmaciÃ³n)
        return count($wizardSteps) - 1;
    }
    
    /**
     * Expandir nombres de campos en steps
     * Por defecto mantiene solo referencias (strings) para evitar duplicaciÃ³n
     * La app mÃ³vil puede buscar los campos completos en form_config.fields
     * @param array $steps Array de steps con fields como nombres (strings) o objetos
     * @param array $fieldsConfig Array completo de configuraciÃ³n de campos
     * @return array Steps con fields como referencias (strings) para evitar duplicaciÃ³n
     */
    private function expandStepFields($steps, $fieldsConfig)
    {
        // Expandir campos en cada step, manteniendo solo referencias
        $expandedSteps = [];
        foreach ($steps as $step) {
            $expandedStep = $step;
            $stepFields = $step['fields'] ?? [];
            $expandedFields = [];
            
            foreach ($stepFields as $field) {
                // Si es string (nombre), mantenerlo como referencia
                if (is_string($field)) {
                    $expandedFields[] = $field;
                } else {
                    // Si es un objeto, extraer solo el nombre para evitar duplicaciÃ³n
                    // La informaciÃ³n completa estÃ¡ en form_config.fields
                    $fieldName = $field['name'] ?? null;
                    if ($fieldName) {
                        $expandedFields[] = $fieldName;
                    } else {
                        // Si no tiene name, mantener el objeto (caso especial)
                        $expandedFields[] = $field;
                    }
                }
            }
            
            $expandedStep['fields'] = $expandedFields;
            $expandedSteps[] = $expandedStep;
        }
        
        return $expandedSteps;
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
        
        // Verificar si todos los campos tienen valores (confirmaciÃ³n)
        $allFieldsHaveValues = true;
        foreach ($fields as $field) {
            if (empty($field['value']) && $field['required'] ?? false) {
                $allFieldsHaveValues = false;
                break;
            }
        }
        
        // Si todos los campos tienen valores, crear un solo paso de confirmaciÃ³n
        if ($allFieldsHaveValues) {
            $steps[] = [
                'step' => 0,
                'title' => "ConfirmaciÃ³n",
                'fields' => $fields,
            ];
            return $steps;
        }
        
        // Si faltan campos, agrupar en pasos lÃ³gicos
        $currentStep = 0;
        $currentStepFields = [];
        
        foreach ($fields as $field) {
            // Si el campo tiene depends_on y ya hay campos en el paso actual,
            // podrÃ­a ser un nuevo paso
            if (!empty($currentStepFields) && isset($field['depends_on'])) {
                // Si hay dependencia, podrÃ­a ser un nuevo paso
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
        
        // Agregar el Ãºltimo paso si tiene campos
        if (!empty($currentStepFields)) {
            $steps[] = [
                'step' => $currentStep,
                'title' => $currentStep === 0 ? "InformaciÃ³n bÃ¡sica" : "Paso " . ($currentStep + 1),
                'fields' => $currentStepFields,
            ];
        }
        
        return $steps;
    }

    /**
     * Buscar acciÃ³n por action_id
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
     * Inyectar parÃ¡metros en el request object
     * @param array $params ParÃ¡metros a inyectar
     * @return array Estado original del request para restaurar despuÃ©s
     */
    private function injectParamsIntoRequest($params)
    {
        $originalState = [
            'bodyParams' => Yii::$app->request->bodyParams,
            'requestMethod' => $_SERVER['REQUEST_METHOD'] ?? null,
        ];
        
        if (!empty($params)) {
            // Inyectar parÃ¡metros en bodyParams
            $currentBodyParams = Yii::$app->request->bodyParams ?? [];
            Yii::$app->request->bodyParams = array_merge($currentBodyParams, $params);
            
            // Establecer el mÃ©todo como POST temporalmente en $_SERVER
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
     * Ejecutar una acciÃ³n
     * @param array $action
     * @param array $params
     * @param int|null $userId
     * @return array
     */
    private function executeAction($action, $params, $userId)
    {
        $route = $action['route'] ?? null;
        
        // Si no tiene ruta, es una acciÃ³n especial del sistema
        if (empty($route)) {
            return [
                'success' => false,
                'error' => 'Esta acciÃ³n no puede ejecutarse directamente',
                'action_id' => $action['action_id'] ?? null,
            ];
        }
        
        // Parsear ruta: /frontend/efectores/indexuserefector
        $routeParts = explode('/', trim($route, '/'));
        
        // Obtener controlador y acciÃ³n
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
                'error' => 'Ruta invÃ¡lida: ' . $route,
                'action_id' => $action['action_id'] ?? null,
            ];
        }
        
        // Crear instancia del controlador
        $controllerClass = $this->resolveControllerClassByRoute($route, $controllerName);
        
        if (!class_exists($controllerClass)) {
            return [
                'success' => false,
                'error' => 'Controlador no encontrado: ' . $controllerClass,
                'action_id' => $action['action_id'] ?? null,
            ];
        }
        
        try {
            // Inyectar parÃ¡metros en el request y guardar estado original
            $originalState = $this->injectParamsIntoRequest($params);
            
            $user = Yii::$app->user->identity;
            
            // Guardar el estado original del usuario para restaurarlo despuÃ©s
            $originalUserIdentity = Yii::$app->user->identity;
            
            // Establecer la identidad del usuario sin iniciar sesiÃ³n (API stateless con JWT)
            // El rol "paciente" se asigna automÃ¡ticamente por BioenlaceDbManager::getRolesByUser()
            Yii::$app->user->setIdentity($user);
            
            // Actualizar permisos y rutas en la sesiÃ³n para que los controladores puedan verificar acceso
            // Esto es necesario porque setIdentity() no actualiza automÃ¡ticamente los permisos
            \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions(Yii::$app->user);
            \common\components\Actions\AllowedRoutesResolver::markSessionRoutesOwner((int) Yii::$app->user->id);
            
            try {
                // Crear instancia del controlador sin especificar mÃ³dulo
                // El controlador estÃ¡ en frontend\controllers, no en un mÃ³dulo
                $controller = $this->createControllerInstance($controllerClass, $controllerName);
                
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
                
                // Deshabilitar validaciÃ³n CSRF ya que estamos ejecutando desde la API
                // y la autenticaciÃ³n se maneja con JWT
                $controller->enableCsrfValidation = false;
                
                // Convertir nombre de acciÃ³n de kebab-case (crear-mi-turno) a camelCase (crearMiTurno)
                // usando Inflector de Yii2
                $actionCamelCase = Inflector::id2camel($actionName, '-');
                $methodName = 'action' . $actionCamelCase;
                
                if (!method_exists($controller, $methodName)) {
                    return [
                        'success' => false,
                        'error' => 'MÃ©todo no encontrado: ' . $methodName,
                        'action_id' => $action['action_id'],
                    ];
                }
                
                // Ejecutar la acciÃ³n (sin pasar params directamente, ya estÃ¡n en request)
                $result = $controller->runAction($actionName, []);
                
                // Validar que retorne JSON (array)
                if (!is_array($result)) {
                    return [
                        'success' => false,
                        'error' => 'La acciÃ³n debe retornar JSON (array). RetornÃ³: ' . gettype($result),
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
            Yii::error("Error ejecutando acciÃ³n {$action['action_id']}: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'api-execute-action');
            return [
                'success' => false,
                'error' => 'Error al ejecutar la acciÃ³n: ' . $e->getMessage(),
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
     * Construye form_config/wizard para alta de turnos desde template UI,
     * evitando ejecutar actionCrearComoPaciente() en modo GET.
     *
     * @param array $action
     * @param array $params
     * @return array
     */
    private function buildTurnosWizardFromTemplate(array $action, array $params): array
    {
        try {
            $templateParams = array_merge([
                'today' => date('Y-m-d'),
            ], $params);

            $config = \common\components\UiDefinitionTemplateManager::render(
                'turnos',
                'crear-mi-turno',
                $templateParams
            );

            $wizardConfig = $config['wizard_config'] ?? [];
            $hasSteps = !empty($wizardConfig['steps'] ?? []);
            $hasFields = !empty($wizardConfig['fields'] ?? []);
            if (!$hasSteps && !$hasFields) {
                return $this->error(
                    'No se pudo obtener la configuracion del formulario. Por favor, intente nuevamente mas tarde.',
                    null,
                    500
                );
            }

            $formConfig = [
                'fields' => $wizardConfig['fields'] ?? [],
            ];
            if (isset($wizardConfig['navigation'])) {
                $formConfig['navigation'] = $wizardConfig['navigation'];
            }
            if (isset($wizardConfig['validation'])) {
                $formConfig['validation'] = $wizardConfig['validation'];
            }
            if (isset($wizardConfig['ui'])) {
                $formConfig['ui'] = $wizardConfig['ui'];
            }

            $wizardSteps = $this->expandStepFields(
                $wizardConfig['steps'] ?? [],
                $wizardConfig['fields'] ?? []
            );

            $data = [
                'form_config' => $formConfig,
                'wizard_steps' => $wizardSteps,
                'initial_step' => $wizardConfig['initial_step'] ?? 0,
                'action_id' => $action['action_id'] ?? null,
                'action_name' => $action['action_name'] ?? $action['display_name'] ?? 'Completa la informacion',
                'kind' => 'ui_definition',
                'ui_type' => 'wizard',
            ];
            if (isset($config['compatibility'])) {
                $data['compatibility'] = $config['compatibility'];
            }

            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            Yii::error("Error buildTurnosWizardFromTemplate: " . $e->getMessage(), 'api-execute-action');
            return $this->error(
                'No se pudo obtener la configuracion del formulario. Por favor, contacte al administrador.',
                null,
                500
            );
        }
    }


    /**
     * Resuelve la clase de controlador según route/controller de la acción descubierta.
     * Prioriza controladores API v1 cuando la ruta inicia con /api/.
     *
     * @param array $action
     * @return string
     */
    private function resolveControllerClassByAction(array $action)
    {
        $controllerName = (string)($action['controller'] ?? '');
        $route = (string)($action['route'] ?? '');

        return $this->resolveControllerClassByRoute($route, $controllerName);
    }

    /**
     * @param string $route
     * @param string $controllerName
     * @return string
     */
    private function resolveControllerClassByRoute($route, $controllerName)
    {
        $controllerStudly = ucfirst((string)$controllerName);
        $apiClass = 'frontend\\modules\\api\\v1\\controllers\\' . $controllerStudly . 'Controller';
        $webClass = 'frontend\\controllers\\' . $controllerStudly . 'Controller';

        if (strpos((string)$route, '/api/') === 0 && class_exists($apiClass)) {
            return $apiClass;
        }
        if (class_exists($webClass)) {
            return $webClass;
        }

        return $apiClass;
    }

    /**
     * @param string $controllerClass
     * @param string $controllerId
     * @return \yii\base\Controller
     */
    private function createControllerInstance($controllerClass, $controllerId)
    {
        $module = Yii::$app;
        if (strpos((string)$controllerClass, 'frontend\\modules\\api\\v1\\controllers\\') === 0) {
            $module = Yii::$app->getModule('v1') ?: Yii::$app;
        }

        return new $controllerClass($controllerId, $module);
    }
    /**
     * Deshabilitar acciones por defecto de ActiveController
     */
    public function actions()
    {
        return [];
    }
}


