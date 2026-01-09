<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use frontend\modules\api\v1\controllers\BaseController;

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
        
        if (empty($query)) {
            return $this->error('La consulta no puede estar vacía', null, 400);
        }

        try {
            // Procesar consulta usando UniversalQueryAgent (implementación genérica y mejorada)
            $result = \common\components\UniversalQueryAgent::processQuery($query, $userId);
            
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
     * Este endpoint recibe un action_id y valida permisos antes de ejecutar
     * 
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
        
        $actionId = Yii::$app->request->post('action_id');
        $params = Yii::$app->request->post('params', []);
        
        if (empty($actionId)) {
            return $this->error('action_id es requerido', null, 400);
        }
        
        try {
            // Buscar la acción por action_id
            $action = $this->findActionById($actionId, $userId);
            
            if (!$action) {
                return $this->error('Acción no encontrada o no tienes permisos', null, 404);
            }
            
            // Validar permisos nuevamente (seguridad)
            if (!empty($action['route']) && !$this->userCanAccessRoute($userId, $action['route'])) {
                return $this->error('No tienes permisos para ejecutar esta acción', null, 403);
            }
            
            // Ejecutar la acción
            return $this->executeAction($action, $params, $userId);
            
        } catch (\Exception $e) {
            Yii::error("Error ejecutando acción: " . $e->getMessage(), 'api-execute-action');
            return $this->error('Error al ejecutar la acción: ' . $e->getMessage(), null, 500);
        }
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
     * Verificar si el usuario puede acceder a una ruta
     * @param int $userId
     * @param string $route
     * @return bool
     */
    private function userCanAccessRoute($userId, $route)
    {
        $user = \webvimark\modules\UserManagement\models\User::findOne($userId);
        
        if (!$user) {
            return false;
        }
        
        // Superadmin tiene acceso a todo
        if ($user->superadmin == 1) {
            return true;
        }

        try {
            // Verificar si la ruta es de acceso libre
            if (\webvimark\modules\UserManagement\models\rbacDB\Route::isFreeAccess($route)) {
                return true;
            }

            // Obtener rutas permitidas para el usuario desde RBAC
            $authManager = Yii::$app->authManager;
            $permissions = $authManager->getPermissionsByUser($userId);
            
            // Verificar si alguna permiso coincide con la ruta
            foreach ($permissions as $permission) {
                if ($permission->name === $route) {
                    return true;
                }
                // Verificar rutas con sub-rutas (ej: /site/* incluye /site/index)
                if (strpos($route, $permission->name) === 0) {
                    return true;
                }
            }

            // Verificar roles del usuario y sus permisos
            $roles = $authManager->getRolesByUser($userId);
            foreach ($roles as $role) {
                $rolePermissions = $authManager->getPermissionsByRole($role->name);
                foreach ($rolePermissions as $permission) {
                    if ($permission->name === $route) {
                        return true;
                    }
                    if (strpos($route, $permission->name) === 0) {
                        return true;
                    }
                }
            }

            return false;
        } catch (\Exception $e) {
            Yii::error("Error verificando acceso a ruta {$route}: " . $e->getMessage(), 'api-execute-action');
            return false;
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
            
            // Convertir nombre de acción: indexuserefector -> indexuserefector (ya está en formato correcto)
            // Pero Yii2 espera: actionIndexuserefector
            $methodName = 'action' . ucfirst($actionName);
            
            if (!method_exists($controller, $methodName)) {
                return [
                    'success' => false,
                    'error' => 'Método no encontrado: ' . $methodName,
                ];
            }
            
            // Ejecutar la acción
            // Para acciones que retornan vistas, necesitamos capturar la salida
            ob_start();
            $result = $controller->runAction($actionName, $params);
            $output = ob_get_clean();
            
            // Si la acción retorna un array o string, devolverlo
            if (is_array($result)) {
                return [
                    'success' => true,
                    'data' => $result,
                    'action_id' => $action['action_id'],
                ];
            }
            
            // Si retorna una vista (string), devolverla
            if (is_string($result)) {
                return [
                    'success' => true,
                    'data' => [
                        'html' => $result,
                        'output' => $output,
                    ],
                    'action_id' => $action['action_id'],
                ];
            }
            
            // Si no retorna nada o retorna algo inesperado
            return [
                'success' => true,
                'data' => [
                    'message' => 'Acción ejecutada correctamente',
                    'output' => $output,
                ],
                'action_id' => $action['action_id'],
            ];
            
        } catch (\Exception $e) {
            Yii::error("Error ejecutando acción {$action['action_id']}: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'api-execute-action');
            return [
                'success' => false,
                'error' => 'Error al ejecutar la acción: ' . $e->getMessage(),
            ];
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

