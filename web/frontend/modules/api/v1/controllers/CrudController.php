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
        // El método actionProcessQuery verificará manualmente la autenticación
        $behaviors['authenticator']['except'] = ['options', 'process-query'];
        
        // Configurar CORS específico para este controlador
        // Usar la configuración centralizada del módulo
        $behaviors['corsFilter'] = [
            'class' => \yii\filters\Cors::class,
            'cors' => [
                'Origin' => \frontend\modules\api\v1\Module::getAllowedOrigins(),
                'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Allow-Credentials' => true,
                'Access-Control-Max-Age' => 86400,
            ],
        ];
        
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
        // Verificar autenticación: 
        // 1. Intentar con Bearer token (para móvil)
        // 2. Si no hay Bearer, verificar sesión web (para web)
        $isAuthenticated = false;
        $userId = null;
        
        // Verificar Bearer token primero (el BaseController maneja esto)
        if (!Yii::$app->user->isGuest) {
            $isAuthenticated = true;
            $userId = Yii::$app->user->id;
        } 
        // Si no hay Bearer token, verificar sesión web de frontend
        else {
            // El módulo API cambia el componente user a ApiUser (sin sesión habilitada)
            // Pero la sesión de Yii2 sigue funcionando, solo necesitamos verificar directamente
            $session = Yii::$app->session;
            
            // Asegurar que la sesión esté iniciada
            if (!$session->isActive) {
                $session->open();
            }
            
            // Verificar si hay identidad de usuario en la sesión
            // Yii2 guarda la identidad del usuario en '__identity' y el ID en '__id'
            $identityId = $session->get('__id');
            $identity = $session->get('__identity');
            
            // Obtener el ID del usuario desde la identidad o desde __id
            if ($identity !== null) {
                if (is_object($identity)) {
                    // Si la identidad es un objeto, obtener el ID
                    if (method_exists($identity, 'getId')) {
                        $userId = $identity->getId();
                    } elseif (isset($identity->id)) {
                        $userId = $identity->id;
                    } elseif (!empty($identityId)) {
                        $userId = $identityId;
                    }
                } else {
                    $userId = $identityId;
                }
                
                if (!empty($userId)) {
                    $isAuthenticated = true;
                }
            } elseif (!empty($identityId)) {
                // Si solo tenemos el ID pero no la identidad completa
                $userId = $identityId;
                $isAuthenticated = true;
            } 
            // Verificar también por datos específicos de la aplicación (idPersona)
            elseif ($session->has('idPersona')) {
                // Si tenemos idPersona, buscar el usuario correspondiente
                $idPersona = $session->get('idPersona');
                $persona = \common\models\Persona::findOne(['id_persona' => $idPersona]);
                if ($persona && !empty($persona->id_user)) {
                    $userId = $persona->id_user;
                    $isAuthenticated = true;
                }
            }
        }
        
        if (!$isAuthenticated) {
            return $this->error('Debe estar autenticado para usar esta funcionalidad', null, 401);
        }

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
     * Deshabilitar acciones por defecto de ActiveController
     */
    public function actions()
    {
        return [];
    }
}

